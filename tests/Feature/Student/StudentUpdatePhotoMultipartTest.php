<?php

namespace Tests\Feature\Student;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-46 - PATCH /students/{id} via multipart (method-spoofing POST + `_method=PATCH`).
// Sebelum fix, `_method` ikut dihitung sebagai field asing oleh
// UpdateStudentRequest::prepareForValidation() sehingga replace foto selalu
// 422 protected_field. SoT: API Contract v1.2 §17 (photo writable), Matrix #10.
// Test ini butuh ekstensi PHP `gd` (UploadedFile::fake()->image() dan UploadService).
//
// Yang dikunci:
//   - `_method` tidak dianggap protected_field (body maupun query string)
//   - foto valid -> 200, tersimpan, dan replace menghapus file lama
//   - field terlarang lain tetap 422 protected_field; `_method` tidak ikut dilaporkan
//   - rule foto tetap berlaku (bukan gambar -> 422 validation_failed)
//   - scope tidak longgar: Teacher rombel lain 403, Headmaster 403
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class StudentUpdatePhotoMultipartTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** Satu sekolah, dua rombel: Teacher A + Student di rombel A; Teacher B di rombel B. */
    protected function scenario(): array
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombelA = $this->fxRombel($school, $year, 'VII-A');
        $rombelB = $this->fxRombel($school, $year, 'VII-B');

        $teacherA = $this->fxTeacher($school, '2001');
        $teacherB = $this->fxTeacher($school, '2002');
        $this->fxTeacherAssignment($teacherA->teacher, $rombelA);
        $this->fxTeacherAssignment($teacherB->teacher, $rombelB);

        $studentUser = $this->fxStudent($school, '0051234567');
        $this->fxStudentAssignment($studentUser->student, $rombelA);

        $headmaster = $this->fxHeadmaster($school, '1001');

        return [$teacherA, $teacherB, $headmaster, $studentUser->student];
    }

    /** Multipart: POST + `_method=PATCH`, persis pola klien browser/Postman. */
    protected function spoofPatch(Student $student, ?string $token, array $fields = [], string $query = '')
    {
        $headers = ['Accept' => 'application/json'];

        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->post(
            "/api/v1/students/{$student->id}{$query}",
            $query === '' ? array_merge(['_method' => 'PATCH'], $fields) : $fields,
            $headers
        );
    }

    /** @test */
    public function multipart_method_patch_dengan_foto_valid_200_dan_foto_tersimpan(): void
    {
        [$teacherA, , , $student] = $this->scenario();

        $response = $this->spoofPatch($student, $this->fxToken($teacherA), [
            'photo' => UploadedFile::fake()->image('foto.jpg', 100, 100),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonStructure(['data' => ['id', 'fullname', 'nisn', 'gender', 'photo']]);

        $path = $student->fresh()->photo_path;
        $this->assertSame("students/{$student->id}.jpg", $path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($response->json('data.photo'));
    }

    /** @test */
    public function multipart_method_patch_mengganti_foto_lama_dan_menghapus_file_sebelumnya(): void
    {
        [$teacherA, , , $student] = $this->scenario();
        $token = $this->fxToken($teacherA);

        $this->spoofPatch($student, $token, ['photo' => UploadedFile::fake()->image('lama.jpg', 80, 80)])
            ->assertStatus(200);
        $old = $student->fresh()->photo_path;
        Storage::disk('public')->assertExists($old);

        $this->spoofPatch($student, $token, ['photo' => UploadedFile::fake()->image('baru.png', 80, 80)])
            ->assertStatus(200);

        $new = $student->fresh()->photo_path;
        $this->assertNotSame($old, $new);
        $this->assertSame("students/{$student->id}.png", $new);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing($old);
    }

    /** @test */
    public function multipart_method_patch_mengubah_name_tanpa_foto(): void
    {
        [$teacherA, , , $student] = $this->scenario();

        $this->spoofPatch($student, $this->fxToken($teacherA), ['name' => 'Nama Baru', 'gender' => 'perempuan'])
            ->assertStatus(200)
            ->assertJsonPath('data.fullname', 'Nama Baru')
            ->assertJsonPath('data.gender', 'perempuan');

        $this->assertDatabaseHas('students', ['id' => $student->id, 'fullname' => 'Nama Baru', 'gender' => 'perempuan']);
        $this->assertNull($student->fresh()->photo_path);
    }

    /** @test */
    public function method_spoofing_lewat_query_string_juga_tidak_dianggap_field_asing(): void
    {
        [$teacherA, , , $student] = $this->scenario();

        $this->spoofPatch($student, $this->fxToken($teacherA), ['name' => 'Via Query'], '?_method=PATCH')
            ->assertStatus(200)
            ->assertJsonPath('data.fullname', 'Via Query');
    }

    /** @test */
    public function multipart_method_patch_dengan_field_terlarang_tetap_422_dan_method_tidak_dilaporkan(): void
    {
        [$teacherA, , , $student] = $this->scenario();

        $response = $this->spoofPatch($student, $this->fxToken($teacherA), [
            'nisn'      => '9999999999',
            'school_id' => 999,
            'photo'     => UploadedFile::fake()->image('foto.jpg', 50, 50),
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'protected_field');

        $fields = array_column($response->json('error.details'), 'field');
        sort($fields);
        $this->assertSame(['nisn', 'school_id'], $fields, '_method tidak boleh muncul di details');

        $this->assertDatabaseHas('students', ['id' => $student->id, 'nisn' => '0051234567']);
        $this->assertNull($student->fresh()->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** @test */
    public function multipart_method_patch_foto_bukan_gambar_422_validation_failed(): void
    {
        [$teacherA, , , $student] = $this->scenario();

        $response = $this->spoofPatch($student, $this->fxToken($teacherA), [
            'photo' => UploadedFile::fake()->create('catatan.txt', 5, 'text/plain'),
        ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->assertSame('photo', $response->json('error.details.0.field'));
        $this->assertNull($student->fresh()->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** @test */
    public function multipart_method_patch_oleh_teacher_rombel_lain_403_dan_foto_tidak_tersimpan(): void
    {
        [, $teacherB, , $student] = $this->scenario();

        $this->spoofPatch($student, $this->fxToken($teacherB), [
            'photo' => UploadedFile::fake()->image('foto.jpg', 50, 50),
        ])->assertStatus(403);

        $this->assertNull($student->fresh()->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** @test */
    public function multipart_method_patch_oleh_headmaster_403_dan_tanpa_token_401(): void
    {
        [, , $headmaster, $student] = $this->scenario();
        $photo = fn () => UploadedFile::fake()->image('foto.jpg', 50, 50);

        $this->spoofPatch($student, $this->fxToken($headmaster), ['photo' => $photo()])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        $this->spoofPatch($student, null, ['photo' => $photo()])->assertStatus(401);

        $this->assertNull($student->fresh()->photo_path);
    }
}
