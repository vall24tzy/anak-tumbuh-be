<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// STAGE 0-C - menutup sebagian TEST GAP F-27 (`GET /auth/me`, 4 role + 401)
// dan mengunci keputusan F-26 (FINAL, DOC ONLY): `/auth/me.id` = `users.id`,
// BUKAN id profil Headmaster/Teacher/Student. SoT: API Contract v1.2 §10;
// Master Audit Matrix v3 F-26 ("tambahkan test yang mengunci id == users.id").
//
// SENGAJA TIDAK mengunci bentuk respons penuh per role: F-25 (shape resmi
// `/auth/me`) berstatus DECIDED - DOC ONLY dan dokumennya belum diformalkan.
// Test ini hanya menegaskan field yang sudah dipakai Task Board (id, role, name,
// nip/nisn, school) + tidak ada kebocoran password. Bila F-25 memutuskan shape
// lain, sesuaikan test ini di stage F-25, bukan di Stage 0-C.
//
// STATUS EKSEKUSI: belum dijalankan (tidak ada PHP/Postgres di lingkungan
// penyusun patch). Jalankan: php artisan test --filter=AuthMeTest

class AuthMeTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    /** @test */
    public function me_super_admin_200_id_adalah_users_id(): void
    {
        $sa = $this->fxSuperAdmin('sa_me');

        $this->apiGet('/api/v1/auth/me', $this->fxToken($sa))
            ->assertStatus(200)
            ->assertJsonPath('data.id', $sa->id)
            ->assertJsonPath('data.role', 'super_admin')
            ->assertJsonPath('data.name', 'sa_me')
            ->assertJsonMissingPath('data.password');
    }

    /** @test */
    public function me_headmaster_teacher_student_200_dan_id_adalah_users_id_bukan_id_profil(): void
    {
        // Super Admin dibuat DULU supaya urutan users.id selalu lebih besar dari
        // urutan id profil (setiap profil punya 1 user, ditambah user tanpa profil).
        // Tanpa ini id user dan id profil bisa kebetulan sama dan test tidak bermakna.
        $this->fxSuperAdmin('sa_me');

        $school = $this->fxSchool('SD Satu');
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');
        $student = $this->fxStudent($school, '0051110001');

        $cases = [
            [$hm, $hm->headmaster, 'headmaster', 'nip', '1001'],
            [$teacher, $teacher->teacher, 'teacher', 'nip', '2001'],
            [$student, $student->student, 'student', 'nisn', '0051110001'],
        ];

        foreach ($cases as [$user, $profile, $role, $idKey, $idValue]) {
            $this->assertNotSame($profile->id, $user->id, "fixture {$role}: id profil harus beda dari users.id");

            $this->apiGet('/api/v1/auth/me', $this->fxToken($user))
                ->assertStatus(200)
                ->assertJsonPath('data.id', $user->id)
                ->assertJsonPath('data.role', $role)
                ->assertJsonPath("data.{$idKey}", $idValue)
                ->assertJsonPath('data.school.id', $school->id)
                ->assertJsonPath('data.school.name', 'SD Satu')
                ->assertJsonMissingPath('data.password');
        }
    }

    /** @test */
    public function me_tanpa_token_dan_token_invalid_401(): void
    {
        $this->apiGet('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized');

        $this->apiGet('/api/v1/auth/me', 'bukan.token.valid')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized');
    }
}
