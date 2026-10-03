<?php

namespace Tests\Feature\School;

use App\Models\Headmaster;
use App\Models\School;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// F-49 (MASTER_AUDIT_REMEDIATION_MATRIX v3.1, keputusan FINAL): cross-school
// pada POST /teachers/{id}/inactive = 404, sama dengan "tidak ada" (§26,
// keberadaan Teacher di sekolah lain tidak bocor). API Contract v1.2 §15
// memang kontradiktif untuk skenario ini (404 dan 403); F-49 memutuskan 404.
// inactive() memakai show() yang menyaring school_id sebelum find(). Sempat
// diputuskan 403 di commit e58c6e1 (docs/authorization-matrix.md Temuan #3),
// lalu direkonsiliasi balik ke 404 mengikuti F-49. resetPassword() tetap 403
// (TeacherResetPasswordScopeTest) dan tidak digeneralisasi (F-49).
//
// Setup pakai Model::create() berantai, konsisten dengan
// tests/Feature/School/TeacherResetPasswordScopeTest.php.
//
// STATUS EKSEKUSI: belum dijalankan di sandbox ini (tidak ada Postgres live).
// Jalankan manual: php artisan test --filter=TeacherLifecycleScopeTest

class TeacherLifecycleScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function createHeadmaster(School $school, string $nip): User
    {
        $user = User::create([
            'role'     => 'headmaster',
            'username' => $nip,
            'password' => 'secret',
        ]);

        Headmaster::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Kepala Sekolah ' . $nip,
            'nip'       => $nip,
            'gender'    => 'laki-laki',
            'status'    => 'active',
        ]);

        return $user;
    }

    protected function createTeacher(School $school, string $nip): Teacher
    {
        $user = User::create([
            'role'     => 'teacher',
            'username' => $nip,
            'password' => 'secret',
        ]);

        return Teacher::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Guru ' . $nip,
            'nip'       => $nip,
            'gender'    => 'perempuan',
            'status'    => 'active',
        ]);
    }

    /** @test */
    public function headmaster_inactive_teacher_sekolah_sendiri_berhasil_200(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '3001');
        $teacher = $this->createTeacher($school, '4001');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/teachers/{$teacher->id}/inactive");

        $response->assertStatus(200);
        $this->assertSame('inactive', $response->json('data.status'));
    }

    /**
     * Kasus inti F-49: Teacher ADA di database (bukan id ngasal), tapi di
     * sekolah LAIN dari Headmaster yang menonaktifkan. Harus 404, sama dengan
     * id yang tidak ada (keberadaannya tidak boleh bocor).
     *
     * @test
     */
    public function headmaster_inactive_teacher_sekolah_lain_tetap_404_sesuai_f49(): void
    {
        $schoolA = School::create(['name' => 'SD Satu']);
        $schoolB = School::create(['name' => 'SD Dua']);

        $hmUserA = $this->createHeadmaster($schoolA, '3002');
        $teacherB = $this->createTeacher($schoolB, '4002');

        $token = auth('api')->login($hmUserA);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/teachers/{$teacherB->id}/inactive");

        $response->assertStatus(404);

        // Teacher B TIDAK boleh ikut berubah status-nya hanya karena ditolak scope.
        $this->assertDatabaseHas('teachers', ['id' => $teacherB->id, 'status' => 'active']);
    }

    /**
     * Beda kasus dari di atas: id benar-benar TIDAK EKSIS di database
     * manapun — ini tetap 404 (bukan 403), tidak tersentuh keputusan tim
     * soal cross-school.
     *
     * @test
     */
    public function headmaster_inactive_teacher_id_tidak_ada_tetap_404(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '3003');

        $token = auth('api')->login($hmUser);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/teachers/999999/inactive');

        $response->assertStatus(404);
    }

    /** @test */
    public function cascade_tutup_teacher_class_assignment_aktif_tetap_jalan_setelah_fix(): void
    {
        $school = School::create(['name' => 'SD Satu']);
        $hmUser = $this->createHeadmaster($school, '3004');
        $teacher = $this->createTeacher($school, '4004');

        $assignment = TeacherClassAssignment::create([
            'teacher_id'       => $teacher->id,
            'school_id'        => $school->id,
            'academic_year_id' => \App\Models\AcademicYear::create([
                'school_id' => $school->id, 'name' => '2026/2027',
                'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true,
            ])->id,
            'rombel_id' => \App\Models\Rombel::create([
                'school_id' => $school->id,
                'academic_year_id' => \App\Models\AcademicYear::where('school_id', $school->id)->value('id'),
                'name' => 'VII-A', 'grade_level' => 7,
            ])->id,
            'is_active' => true,
        ]);

        $token = auth('api')->login($hmUser);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/teachers/{$teacher->id}/inactive")
            ->assertStatus(200);

        $this->assertDatabaseHas('teacher_class_assignments', ['id' => $assignment->id, 'is_active' => false]);
    }
}
