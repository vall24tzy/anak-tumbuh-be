<?php

namespace Tests\Feature\Upload;

use App\Models\Student;
use App\Services\Student\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\Concerns\MakesImageUploads;
use Tests\TestCase;

// F-06 - foto Student PRIVATE, diakses lewat GET /api/v1/students/{id}/photo (Opsi A, keputusan
// E-3). Logo School tetap PUBLIC. SoT: Matrix v3.1 F-06 (AC), API Contract §17 dan §26 (GET di
// luar scope = 404), Authorization Matrix (scope data Student).
//
// Otorisasi endpoint foto = StudentService::show() yang sama dengan GET /students/{id} (termasuk
// resolver scope hasil F-14), jadi test paritas membandingkan status foto dengan status show().
//
// Yang dikunci:
//   - foto baru hanya ada di disk 'private'; respons `photo` = URL endpoint, bukan URL storage publik
//   - Student sendiri, Teacher own active rombel, Headmaster own school, Super Admin -> 200 dengan
//     isi berkas dan Content-Type benar, tanpa cache publik
//   - Teacher rombel lain, Teacher/Headmaster sekolah lain, Student lain, id tidak ada -> 404,
//     SAMA dengan show()
//   - tanpa token -> 401; Student tanpa foto dan berkas yang hilang -> 404 (bukan 500)
//   - tidak ada fallback ke disk publik (berkas lama di publik tidak pernah disajikan)
//   - /auth/me dan GET /dashboard/student tidak memuat foto (tidak ada jalur bocor)
//   - logo School tetap URL publik di disk 'public'
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class StudentPhotoPrivacyTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures, MakesImageUploads;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');
    }

    protected function tearDown(): void
    {
        $this->cleanupImageFiles();

        parent::tearDown();
    }

    /** Sekolah A: rombel A (teacherA, student, student2) dan rombel B (teacherB); HM A; sekolah B: HM B + teacher. */
    protected function world(): array
    {
        $schoolA = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($schoolA);
        $rombelA = $this->fxRombel($schoolA, $year, 'VII-A');
        $rombelB = $this->fxRombel($schoolA, $year, 'VII-B');

        $teacherA = $this->fxTeacher($schoolA, '2001');
        $this->fxTeacherAssignment($teacherA->teacher, $rombelA);
        $teacherB = $this->fxTeacher($schoolA, '2002');
        $this->fxTeacherAssignment($teacherB->teacher, $rombelB);

        $studentUser = $this->fxStudent($schoolA, '0051234567');
        $this->fxStudentAssignment($studentUser->student, $rombelA);
        $student2User = $this->fxStudent($schoolA, '0051234568');
        $this->fxStudentAssignment($student2User->student, $rombelA);

        $hmA = $this->fxHeadmaster($schoolA, '1001');
        $sa = $this->fxSuperAdmin('sa_photo');

        $schoolB = $this->fxSchool('SD Dua');
        $yearB = $this->fxAcademicYear($schoolB);
        $rombelOther = $this->fxRombel($schoolB, $yearB, 'VII-A');
        $hmB = $this->fxHeadmaster($schoolB, '1002');
        $teacherOther = $this->fxTeacher($schoolB, '2003');
        $this->fxTeacherAssignment($teacherOther->teacher, $rombelOther);

        $student = $studentUser->student;
        $this->givePhoto($student);

        return compact('schoolA', 'teacherA', 'teacherB', 'studentUser', 'student2User', 'hmA', 'hmB', 'teacherOther', 'sa', 'student');
    }

    /** Taruh foto asli langsung di disk privat dan set photo_path (mengikuti hasil upload nyata). */
    protected function givePhoto(Student $student, string $format = 'jpeg'): string
    {
        $bytes = $this->imageBytes($format, 40, 40);
        $path = "students/{$student->id}." . ($format === 'jpeg' ? 'jpg' : $format);
        Storage::disk('private')->put($path, $bytes);
        $student->update(['photo_path' => $path]);

        return $bytes;
    }

    protected function photoUri(Student $student): string
    {
        return "/api/v1/students/{$student->id}/photo";
    }

    // ---------- penyimpanan dan URL ----------

    /** @test */
    public function upload_foto_student_hanya_di_disk_privat_dan_photo_berupa_url_endpoint(): void
    {
        $w = $this->world();
        $student = $w['student2User']->student;
        $token = $this->fxToken($w['teacherA']);

        $response = $this->post("/api/v1/students/{$student->id}", [
            '_method' => 'PATCH',
            'photo'   => $this->imageUpload('foto.jpg', 'jpeg'),
        ], ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);

        $this->assertSame(["students/{$student->id}.jpg"], collect(Storage::disk('private')->allFiles())->filter(fn ($p) => str_contains($p, (string) $student->id))->values()->all());
        $this->assertSame([], Storage::disk('public')->allFiles(), 'foto Student tidak boleh ada di disk publik');
        $this->assertSame(route('students.photo', ['id' => $student->id]), $response->json('data.photo'));
        $this->assertStringNotContainsString('/storage/', $response->json('data.photo'));
        $this->assertStringEndsWith("/api/v1/students/{$student->id}/photo", $response->json('data.photo'));
    }

    /** @test */
    public function student_tanpa_foto_mendapat_photo_null_di_resource(): void
    {
        $w = $this->world();

        $this->apiGet("/api/v1/students/{$w['student2User']->student->id}", $this->fxToken($w['teacherA']))
            ->assertStatus(200)
            ->assertJsonPath('data.photo', null);
    }

    // ---------- akses berwenang ----------

    /** @test */
    public function student_pemilik_mendapat_200_dengan_isi_berkas_dan_content_type_benar(): void
    {
        $w = $this->world();
        $bytes = Storage::disk('private')->get($w['student']->photo_path);

        $response = $this->apiGet($this->photoUri($w['student']), $this->fxToken($w['studentUser']));

        $response->assertStatus(200);
        $this->assertSame($bytes, $response->streamedContent());
        $this->assertStringStartsWith('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    /** @test */
    public function content_type_mengikuti_berkas_png(): void
    {
        $w = $this->world();
        Storage::disk('private')->delete($w['student']->photo_path);
        $this->givePhoto($w['student'], 'png');

        $this->apiGet($this->photoUri($w['student']), $this->fxToken($w['studentUser']))
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');
    }

    /** @test */
    public function teacher_own_active_rombel_headmaster_own_school_dan_super_admin_mendapat_200(): void
    {
        $w = $this->world();
        $bytes = Storage::disk('private')->get($w['student']->photo_path);

        foreach (['teacherA', 'hmA', 'sa'] as $actor) {
            $response = $this->apiGet($this->photoUri($w['student']), $this->fxToken($w[$actor]));

            $response->assertStatus(200);
            $this->assertSame($bytes, $response->streamedContent(), $actor);
        }
    }

    // ---------- akses di luar scope: sama dengan show() ----------

    /** @test */
    public function di_luar_scope_ditolak_404_dan_sama_persis_dengan_show(): void
    {
        $w = $this->world();

        foreach (['teacherB' => 'Teacher rombel lain', 'hmB' => 'Headmaster sekolah lain', 'teacherOther' => 'Teacher sekolah lain', 'student2User' => 'Student lain'] as $actor => $label) {
            $token = $this->fxToken($w[$actor]);

            $show = $this->apiGet("/api/v1/students/{$w['student']->id}", $token);
            $photo = $this->apiGet($this->photoUri($w['student']), $token);

            $this->assertSame(404, $show->getStatusCode(), "show() {$label}");
            $this->assertSame($show->getStatusCode(), $photo->getStatusCode(), "foto {$label} harus sama dengan show()");
            $this->assertSame('not_found', $photo->json('error.code'), $label);
            $this->assertStringNotContainsString('JFIF', $photo->getContent(), $label);
        }
    }

    /** @test */
    public function student_id_tidak_ada_404_seperti_show(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['teacherA']);

        $this->assertSame(404, $this->apiGet('/api/v1/students/999999', $token)->getStatusCode());
        $this->apiGet('/api/v1/students/999999/photo', $token)->assertStatus(404)->assertJsonPath('error.code', 'not_found');
    }

    /** @test */
    public function tanpa_token_401(): void
    {
        $w = $this->world();

        $this->apiGet($this->photoUri($w['student']))->assertStatus(401);
    }

    // ---------- tanpa foto, berkas hilang, tanpa fallback publik ----------

    /** @test */
    public function student_tanpa_foto_404_bukan_500(): void
    {
        $w = $this->world();

        $this->apiGet($this->photoUri($w['student2User']->student), $this->fxToken($w['teacherA']))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    /** @test */
    public function photo_path_ada_tetapi_berkas_hilang_404_bukan_500(): void
    {
        $w = $this->world();
        Storage::disk('private')->delete($w['student']->photo_path);

        $this->apiGet($this->photoUri($w['student']), $this->fxToken($w['teacherA']))->assertStatus(404);
    }

    /** @test */
    public function berkas_lama_di_disk_publik_tidak_pernah_disajikan_tanpa_fallback(): void
    {
        $w = $this->world();
        Storage::disk('private')->delete($w['student']->photo_path);
        Storage::disk('public')->put($w['student']->photo_path, 'FOTO-LAMA-PUBLIK');

        $response = $this->apiGet($this->photoUri($w['student']), $this->fxToken($w['teacherA']));

        $response->assertStatus(404);
        $this->assertStringNotContainsString('FOTO-LAMA-PUBLIK', $response->getContent());
    }

    // ---------- scope memakai resolver terbaru (F-14) ----------

    /** @test */
    public function headmaster_inactive_ditolak_403_oleh_resolver_scope_f14_bila_service_dipanggil_langsung(): void
    {
        $w = $this->world();
        $w['hmA']->headmaster->update(['status' => 'inactive']);
        auth('api')->setUser($w['hmA']->fresh());

        try {
            app(StudentService::class)->photo($w['student']->id);
            $this->fail('Headmaster inactive seharusnya ditolak');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    // ---------- tidak ada jalur bocor lain ----------

    /** @test */
    public function auth_me_dan_dashboard_student_tidak_memuat_foto(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['studentUser']);

        $me = $this->apiGet('/api/v1/auth/me', $token);
        $dashboard = $this->apiGet('/api/v1/dashboard/student', $token);

        $me->assertStatus(200);
        $dashboard->assertStatus(200);
        $this->assertStringNotContainsString('photo', $me->getContent());
        $this->assertStringNotContainsString('/storage/', $me->getContent());
        $this->assertStringNotContainsString('photo', $dashboard->getContent());
        $this->assertStringNotContainsString('/storage/', $dashboard->getContent());
    }

    /** @test */
    public function daftar_student_juga_memakai_url_endpoint_bukan_url_publik(): void
    {
        $w = $this->world();

        $response = $this->apiGet('/api/v1/students', $this->fxToken($w['teacherA']));

        $response->assertStatus(200);
        $this->assertStringNotContainsString('/storage/', (string) json_encode($response->json('data'), JSON_UNESCAPED_SLASHES));

        $photos = collect($response->json('data'))->pluck('photo', 'id');
        $this->assertSame(route('students.photo', ['id' => $w['student']->id]), $photos[$w['student']->id]);
        $this->assertNull($photos[$w['student2User']->student->id]);
    }

    // ---------- logo School tetap publik ----------

    /** @test */
    public function logo_school_tetap_url_publik_di_disk_public(): void
    {
        $token = $this->fxToken($this->fxSuperAdmin('sa_logo'));

        $response = $this->post('/api/v1/schools', [
            'name'    => 'SD Berlogo',
            'address' => 'Jl. Mawar 2',
            'logo'    => $this->imageUpload('logo.png', 'png'),
        ], ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"]);

        $response->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertStringContainsString("/storage/logos/{$id}.png", $response->json('data.logo'));
        $this->assertSame(["logos/{$id}.png"], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }
}
