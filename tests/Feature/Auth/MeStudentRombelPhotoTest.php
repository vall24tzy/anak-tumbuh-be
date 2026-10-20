<?php

namespace Tests\Feature\Auth;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\Concerns\MakesImageUploads;
use Tests\TestCase;

// INTEG-ME-001 - GET /auth/me dan GET /dashboard/student memuat `rombel` {id,name}|null dan `photo`
// (URL terproteksi|null), HANYA untuk Student. SoT: Dokumen Keputusan v4.0 (amandemen D-01), Matrix
// v5.3 #67, keputusan D-08, Task Board INTEG v1.5. Photo = URL endpoint terproteksi pola F-06
// (StudentResource), BUKAN path berkas dan BUKAN URL storage publik. Rombel = assignment aktif pada
// Academic Year yang sedang active (resolver activeAssignmentInCurrentAcademicYear, DB Design §5.9).
//
// Yang dikunci:
//   - Student: rombel + photo di kedua endpoint, nilainya sama; field lama tidak berubah
//   - photo bukan path/URL publik; URL-nya bisa dibuka dengan Authorization siswa pemilik, 401 tanpa token
//   - tanpa foto -> photo null; tanpa assignment aktif -> rombel null (tidak error)
//   - assignment aktif di dua tahun ajaran -> rombel dari tahun yang active (assignment tahun lain dibuat
//     LEBIH DULU supaya pemilihan "first()" tanpa filter tahun akan salah)
//   - assignment ended tidak dipakai
//   - Student hanya melihat data dirinya sendiri (tidak ada rombel/foto Student lain)
//   - role lain: bentuk /auth/me tidak berubah (tanpa rombel/photo)
//
// STATUS EKSEKUSI: ditulis tanpa menjalankan PHP; jalankan php artisan test lalu laporkan hasilnya.

class MeStudentRombelPhotoTest extends TestCase
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

    /** Sekolah dengan satu tahun ajaran aktif dan rombel VII-A; mengembalikan [school, year, rombel]. */
    protected function world(): array
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');

        return [$school, $year, $rombel];
    }

    /** Taruh foto asli di disk privat dan set photo_path (mengikuti hasil upload nyata). */
    protected function givePhoto(Student $student): string
    {
        $bytes = $this->imageBytes('jpeg', 40, 40);
        $path = "students/{$student->id}.jpg";
        Storage::disk('private')->put($path, $bytes);
        $student->update(['photo_path' => $path]);

        return $bytes;
    }

    // ---------- nilai: rombel + photo ----------

    /** @test */
    public function student_melihat_rombel_dan_photo_di_auth_me_dan_dashboard_dengan_nilai_sama(): void
    {
        [$school, , $rombel] = $this->world();
        $user = $this->fxStudent($school, '0051110001');
        $student = $user->student;
        $this->fxStudentAssignment($student, $rombel);
        $this->givePhoto($student);

        $token = $this->fxToken($user);
        $expectedPhoto = route('students.photo', ['id' => $student->id]);

        $me = $this->apiGet('/api/v1/auth/me', $token)->assertStatus(200);
        $dashboard = $this->apiGet('/api/v1/dashboard/student', $token)->assertStatus(200);

        $me->assertJsonPath('data.rombel', ['id' => $rombel->id, 'name' => 'VII-A']);
        $me->assertJsonPath('data.photo', $expectedPhoto);
        $dashboard->assertJsonPath('data.rombel', ['id' => $rombel->id, 'name' => 'VII-A']);
        $dashboard->assertJsonPath('data.photo', $expectedPhoto);

        $this->assertSame($me->json('data.rombel'), $dashboard->json('data.rombel'));
        $this->assertSame($me->json('data.photo'), $dashboard->json('data.photo'));
    }

    /** @test */
    public function field_lama_auth_me_student_tidak_berubah_dan_field_baru_ditambahkan_di_akhir(): void
    {
        [$school, , $rombel] = $this->world();
        $user = $this->fxStudent($school, '0051110002');
        $this->fxStudentAssignment($user->student, $rombel);

        $me = $this->apiGet('/api/v1/auth/me', $this->fxToken($user))->assertStatus(200);

        $this->assertSame(['id', 'role', 'name', 'nisn', 'school', 'rombel', 'photo'], array_keys($me->json('data')));
        $me->assertJsonPath('data.id', $user->id) // F-26: users.id, bukan students.id
            ->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.name', 'Siswa 0051110002')
            ->assertJsonPath('data.nisn', '0051110002')
            ->assertJsonPath('data.school', ['id' => $school->id, 'name' => 'SD Satu']);
    }

    /** @test */
    public function field_lama_dashboard_student_tidak_berubah_dan_field_baru_ditambahkan_di_akhir(): void
    {
        [$school, , $rombel] = $this->world();
        $user = $this->fxStudent($school, '0051110003');
        $this->fxStudentAssignment($user->student, $rombel);

        $data = $this->apiGet('/api/v1/dashboard/student', $this->fxToken($user))
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(
            ['wali_kelas', 'habit_summary_today', 'poin', 'gamification_level', 'rombel', 'photo'],
            array_keys($data)
        );
    }

    // ---------- photo: URL terproteksi, bukan path / URL publik ----------

    /** @test */
    public function photo_adalah_url_terproteksi_bukan_path_berkas_atau_url_publik(): void
    {
        [$school, , $rombel] = $this->world();
        $user = $this->fxStudent($school, '0051110004');
        $student = $user->student;
        $this->fxStudentAssignment($student, $rombel);
        $this->givePhoto($student);

        $token = $this->fxToken($user);

        foreach (['/api/v1/auth/me', '/api/v1/dashboard/student'] as $uri) {
            $photo = $this->apiGet($uri, $token)->assertStatus(200)->json('data.photo');

            $this->assertStringEndsWith("/api/v1/students/{$student->id}/photo", $photo, $uri);
            $this->assertStringNotContainsString('/storage/', $photo, $uri);
            $this->assertStringNotContainsString('.jpg', $photo, $uri);
            $this->assertNotSame($student->photo_path, $photo, $uri);
        }
    }

    /** @test */
    public function url_photo_bisa_dibuka_siswa_pemilik_dan_ditolak_tanpa_token(): void
    {
        [$school, , $rombel] = $this->world();
        $user = $this->fxStudent($school, '0051110005');
        $student = $user->student;
        $this->fxStudentAssignment($student, $rombel);
        $bytes = $this->givePhoto($student);

        $token = $this->fxToken($user);
        $photoUrl = $this->apiGet('/api/v1/auth/me', $token)->json('data.photo');
        $path = parse_url($photoUrl, PHP_URL_PATH);

        $with = $this->apiGet($path, $token);
        $with->assertStatus(200);
        $this->assertSame($bytes, $with->streamedContent());

        $this->apiGet($path)->assertStatus(401);
    }

    /** @test */
    public function student_tanpa_foto_mendapat_photo_null_di_kedua_endpoint(): void
    {
        [$school, , $rombel] = $this->world();
        $user = $this->fxStudent($school, '0051110006');
        $this->fxStudentAssignment($user->student, $rombel);

        $token = $this->fxToken($user);

        $this->apiGet('/api/v1/auth/me', $token)->assertStatus(200)->assertJsonPath('data.photo', null);
        $this->apiGet('/api/v1/dashboard/student', $token)->assertStatus(200)->assertJsonPath('data.photo', null);
    }

    // ---------- rombel null ----------

    /** @test */
    public function student_tanpa_assignment_aktif_mendapat_rombel_null_tanpa_error(): void
    {
        [$school] = $this->world();
        $user = $this->fxStudent($school, '0051110007');

        $token = $this->fxToken($user);

        $this->apiGet('/api/v1/auth/me', $token)->assertStatus(200)->assertJsonPath('data.rombel', null);
        $this->apiGet('/api/v1/dashboard/student', $token)->assertStatus(200)->assertJsonPath('data.rombel', null);
    }

    /** @test */
    public function assignment_ended_tidak_dipakai_sebagai_rombel(): void
    {
        [$school, , $rombel] = $this->world();
        $user = $this->fxStudent($school, '0051110008');
        $assignment = $this->fxStudentAssignment($user->student, $rombel);
        $assignment->update(['status' => 'ended']);

        $token = $this->fxToken($user);

        $this->apiGet('/api/v1/auth/me', $token)->assertJsonPath('data.rombel', null);
        $this->apiGet('/api/v1/dashboard/student', $token)->assertJsonPath('data.rombel', null);
    }

    /** @test */
    public function tanpa_tahun_ajaran_aktif_rombel_null(): void
    {
        [$school, $year, $rombel] = $this->world();
        $user = $this->fxStudent($school, '0051110009');
        $this->fxStudentAssignment($user->student, $rombel);
        $year->update(['is_active' => false]);

        $token = $this->fxToken($user);

        $this->apiGet('/api/v1/auth/me', $token)->assertStatus(200)->assertJsonPath('data.rombel', null);
        $this->apiGet('/api/v1/dashboard/student', $token)->assertStatus(200)->assertJsonPath('data.rombel', null);
    }

    // ---------- dua assignment aktif lintas tahun ajaran (DB Design §5.9) ----------

    /** @test */
    public function dua_assignment_aktif_di_dua_tahun_ajaran_memilih_rombel_tahun_yang_active(): void
    {
        [$school, $activeYear, $rombelActive] = $this->world();

        // Tahun ajaran lama (TIDAK active) dengan rombelnya sendiri.
        $oldYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2025/2026',
            'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => false,
        ]);
        $rombelOld = $this->fxRombel($school, $oldYear, 'VI-A');

        $user = $this->fxStudent($school, '0051110010');

        // Assignment tahun lama dibuat LEBIH DULU (id lebih kecil) dan sama-sama status active.
        $this->fxStudentAssignment($user->student, $rombelOld);
        $this->fxStudentAssignment($user->student, $rombelActive);

        $this->assertSame(
            2,
            StudentClassAssignment::where('student_id', $user->student->id)->where('status', 'active')->count()
        );

        $token = $this->fxToken($user);

        $this->apiGet('/api/v1/auth/me', $token)
            ->assertStatus(200)
            ->assertJsonPath('data.rombel', ['id' => $rombelActive->id, 'name' => 'VII-A']);
        $this->apiGet('/api/v1/dashboard/student', $token)
            ->assertStatus(200)
            ->assertJsonPath('data.rombel', ['id' => $rombelActive->id, 'name' => 'VII-A']);
    }

    /** @test */
    public function rombel_mengikuti_tahun_ajaran_yang_active_bila_pergantian_tahun_terjadi(): void
    {
        [$school, $year, $rombelA] = $this->world();

        $nextYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2027/2028',
            'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_active' => false,
        ]);
        $rombelNext = $this->fxRombel($school, $nextYear, 'VIII-A');

        $user = $this->fxStudent($school, '0051110011');
        $this->fxStudentAssignment($user->student, $rombelA);
        $this->fxStudentAssignment($user->student, $rombelNext);

        $token = $this->fxToken($user);

        $this->apiGet('/api/v1/auth/me', $token)->assertJsonPath('data.rombel.id', $rombelA->id);

        // Satu active per sekolah (partial unique index): nonaktifkan dulu, baru aktifkan tahun berikutnya.
        $year->update(['is_active' => false]);
        $nextYear->update(['is_active' => true]);

        $this->apiGet('/api/v1/auth/me', $token)->assertJsonPath('data.rombel.id', $rombelNext->id);
        $this->apiGet('/api/v1/dashboard/student', $token)->assertJsonPath('data.rombel.id', $rombelNext->id);
    }

    // ---------- own data saja ----------

    /** @test */
    public function student_hanya_melihat_rombel_dan_photo_diri_sendiri(): void
    {
        [$school, $year, $rombelA] = $this->world();
        $rombelB = $this->fxRombel($school, $year, 'VII-B');

        $userA = $this->fxStudent($school, '0051110012');
        $this->fxStudentAssignment($userA->student, $rombelA);
        $this->givePhoto($userA->student);

        $userB = $this->fxStudent($school, '0051110013');
        $this->fxStudentAssignment($userB->student, $rombelB);

        $meA = $this->apiGet('/api/v1/auth/me', $this->fxToken($userA))->assertStatus(200);
        $meB = $this->apiGet('/api/v1/auth/me', $this->fxToken($userB))->assertStatus(200);

        $meA->assertJsonPath('data.rombel.id', $rombelA->id);
        $meA->assertJsonPath('data.photo', route('students.photo', ['id' => $userA->student->id]));
        $meB->assertJsonPath('data.rombel.id', $rombelB->id);
        $meB->assertJsonPath('data.photo', null); // foto A tidak bocor ke B
    }

    // ---------- role lain tidak berubah ----------

    /** @test */
    public function auth_me_role_lain_tidak_memuat_rombel_atau_photo(): void
    {
        [$school, , $rombel] = $this->world();
        $sa = $this->fxSuperAdmin('sa_integ_me');
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        $this->assertSame(
            ['id', 'role', 'name'],
            array_keys($this->apiGet('/api/v1/auth/me', $this->fxToken($sa))->assertStatus(200)->json('data'))
        );
        $this->assertSame(
            ['id', 'role', 'name', 'nip', 'school'],
            array_keys($this->apiGet('/api/v1/auth/me', $this->fxToken($hm))->assertStatus(200)->json('data'))
        );
        $this->assertSame(
            ['id', 'role', 'name', 'nip', 'school'],
            array_keys($this->apiGet('/api/v1/auth/me', $this->fxToken($teacher))->assertStatus(200)->json('data'))
        );
    }

    /** @test */
    public function dashboard_student_tetap_ditolak_untuk_role_lain_dan_tanpa_token(): void
    {
        [$school, , $rombel] = $this->world();
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        $this->apiGet('/api/v1/dashboard/student', $this->fxToken($teacher))->assertStatus(403);
        $this->apiGet('/api/v1/dashboard/student')->assertStatus(401);
    }
}
