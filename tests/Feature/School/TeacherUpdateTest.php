<?php

namespace Tests\Feature\School;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// INTEG-TEACHER-001 - PATCH /api/v1/teachers/{id}: ubah name (kolom fullname) dan gender guru, Headmaster own
// school. SoT: Dokumen Keputusan v4.0 (amandemen D-01), keputusan D-02 / D-03 / D-04 / D-14 / D-15 / D-16 FINAL,
// Matrix v5.3 #66 (merujuk #61), API Contract §15 (direvisi di addendum), Task Board INTEG v1.5.
//
// Yang dikunci:
//   - Headmaster sekolah sendiri: 200, name/gender berubah, nip/username/password/status/school_id tidak
//     tersentuh, bentuk respons = TeacherResource yang ada; guru masih bisa memakai token dan login lama
//   - Teacher/Student/Super Admin -> 403, tanpa token -> 401, id tidak ada -> 404
//   - guru sekolah lain -> 404 (D-02), data tidak berubah, tanpa audit
//   - guru inactive -> 409 teacher_inactive_readonly; lintas sekolah tetap menang (404)
//   - Headmaster inactive -> 403 (F-14)
//   - nip/username/status/school_id/password/user_id/id -> 422 protected_field (sendiri maupun bersama name
//     valid), data tidak berubah; gender di luar laki-laki/perempuan, name kosong/null/151 -> 422
//   - D-03/D-14/D-15: audit_logs (actor_user_id = Headmaster, action `teacher_update`, target_user_id = user
//     guru) hanya bila ada nilai berubah; nilai sama / sama setelah trim / body kosong = 200 tanpa audit;
//     beda huruf besar/kecil = berubah + satu audit; request gagal = nol baris audit APA PUN
//   - D-04: tidak ada rate limit baru (12 PATCH berturut-turut tidak ada yang 429)
//   - endpoint lama tidak berubah: GET /teachers/{id} dan GET /teachers memuat nilai baru
//
// STATUS EKSEKUSI: ditulis tanpa menjalankan PHP; jalankan php artisan test lalu laporkan hasilnya.

class TeacherUpdateTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const ACTION = 'teacher_update'; // D-15 FINAL

    protected function apiPatch(string $uri, ?string $token = null, array $body = [])
    {
        return $this->patchJson($uri, $body, $token ? ['Authorization' => "Bearer {$token}"] : []);
    }

    /** Sekolah + Headmaster + satu guru aktif (nip 2001, nama "Guru 2001", perempuan). */
    protected function world(): array
    {
        $school = $this->fxSchool('SD Satu');
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');

        return [$school, $hm, $teacher];
    }

    protected function uri($teacherUser): string
    {
        return '/api/v1/teachers/' . $teacherUser->teacher->id;
    }

    // ---------- Headmaster: berhasil ----------

    /** @test */
    public function headmaster_mengubah_name_dan_gender_200_dan_field_lain_tidak_tersentuh(): void
    {
        [$school, $hm, $teacher] = $this->world();
        $passwordBefore = $teacher->fresh()->password;

        $response = $this->apiPatch($this->uri($teacher), $this->fxToken($hm), [
            'name' => 'Guru Baru', 'gender' => 'laki-laki',
        ])->assertStatus(200);

        $this->assertSame(['id', 'school_id', 'name', 'nip', 'gender', 'status'], array_keys($response->json('data')));
        $response->assertJsonPath('data.id', $teacher->teacher->id)
            ->assertJsonPath('data.school_id', $school->id)
            ->assertJsonPath('data.name', 'Guru Baru')
            ->assertJsonPath('data.gender', 'laki-laki')
            ->assertJsonPath('data.nip', '2001')
            ->assertJsonPath('data.status', 'active');

        $fresh = $teacher->teacher->fresh();
        $this->assertSame('Guru Baru', $fresh->fullname);
        $this->assertSame('laki-laki', $fresh->gender);
        $this->assertSame('2001', $fresh->nip);
        $this->assertSame('active', $fresh->status);

        // users.username (= nip) dan password tidak berubah.
        $this->assertSame('2001', $teacher->fresh()->username);
        $this->assertSame($passwordBefore, $teacher->fresh()->password);
    }

    /** @test */
    public function hanya_name_atau_hanya_gender_juga_diterima_dan_field_satunya_tetap(): void
    {
        [, $hm, $teacher] = $this->world();
        $token = $this->fxToken($hm);

        $this->apiPatch($this->uri($teacher), $token, ['name' => 'Hanya Nama'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Hanya Nama')
            ->assertJsonPath('data.gender', 'perempuan');

        $this->apiPatch($this->uri($teacher), $token, ['gender' => 'laki-laki'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Hanya Nama')
            ->assertJsonPath('data.gender', 'laki-laki');
    }

    /** @test */
    public function guru_tetap_bisa_memakai_token_dan_login_lama_setelah_diubah(): void
    {
        [, $hm, $teacher] = $this->world();
        $teacherToken = $this->fxToken($teacher);

        $this->apiPatch($this->uri($teacher), $this->fxToken($hm), ['name' => 'Guru Baru'])->assertStatus(200);

        // Token lama tidak dicabut (tidak ada perubahan kredensial) dan nama baru tampil di /auth/me.
        $this->apiGet('/api/v1/auth/me', $teacherToken)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Guru Baru');

        // Login dengan username/password yang sama tetap berhasil.
        $this->postJson('/api/v1/auth/login/staff', ['username' => '2001', 'password' => 'secret'])
            ->assertStatus(200);
    }

    /** @test */
    public function endpoint_lama_get_show_dan_list_memuat_nilai_baru(): void
    {
        [, $hm, $teacher] = $this->world();
        $token = $this->fxToken($hm);

        $this->apiPatch($this->uri($teacher), $token, ['name' => 'Guru Baru'])->assertStatus(200);

        $this->apiGet($this->uri($teacher), $token)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Guru Baru');

        $names = collect($this->apiGet('/api/v1/teachers', $token)->assertStatus(200)->json('data'))->pluck('name')->all();
        $this->assertContains('Guru Baru', $names);
    }

    // ---------- otorisasi ----------

    /** @test */
    public function teacher_student_dan_super_admin_403_tanpa_perubahan_dan_tanpa_audit(): void
    {
        [$school, , $teacher] = $this->world();
        $otherTeacher = $this->fxTeacher($school, '2002');
        $student = $this->fxStudent($school, '0051110001');
        $sa = $this->fxSuperAdmin('sa_teacher_upd');

        foreach ([$otherTeacher, $student, $sa] as $user) {
            $this->apiPatch($this->uri($teacher), $this->fxToken($user), ['name' => 'Diretas'])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden');
        }

        $this->assertSame('Guru 2001', $teacher->teacher->fresh()->fullname);
        $this->assertSame(0, AuditLog::count());
    }

    /** @test */
    public function tanpa_token_401(): void
    {
        [, , $teacher] = $this->world();

        $this->apiPatch($this->uri($teacher), null, ['name' => 'Diretas'])->assertStatus(401);

        $this->assertSame('Guru 2001', $teacher->teacher->fresh()->fullname);
    }

    /** @test */
    public function headmaster_inactive_403(): void
    {
        [, $hm, $teacher] = $this->world();
        $token = $this->fxToken($hm);
        $hm->headmaster->update(['status' => 'inactive']);

        $this->apiPatch($this->uri($teacher), $token, ['name' => 'Diretas'])->assertStatus(403);

        $this->assertSame('Guru 2001', $teacher->teacher->fresh()->fullname);
        $this->assertSame(0, AuditLog::count());
    }

    // ---------- scope: lintas sekolah dan id tidak ada = 404 (D-02) ----------

    /** @test */
    public function guru_sekolah_lain_404_tanpa_perubahan_dan_tanpa_audit(): void
    {
        [, $hmA] = $this->world();
        $schoolB = $this->fxSchool('SD Dua');
        $teacherB = $this->fxTeacher($schoolB, '3001');

        $this->apiPatch($this->uri($teacherB), $this->fxToken($hmA), ['name' => 'Diretas', 'gender' => 'laki-laki'])
            ->assertStatus(404);

        $fresh = $teacherB->teacher->fresh();
        $this->assertSame('Guru 3001', $fresh->fullname);
        $this->assertSame('perempuan', $fresh->gender);
        $this->assertSame(0, AuditLog::count());
    }

    /** @test */
    public function id_tidak_ada_404(): void
    {
        [, $hm] = $this->world();

        $this->apiPatch('/api/v1/teachers/999999', $this->fxToken($hm), ['name' => 'Tidak Ada'])->assertStatus(404);

        $this->assertSame(0, AuditLog::count());
    }

    // ---------- guru inactive ----------

    /** @test */
    public function guru_inactive_ditolak_409_teacher_inactive_readonly_tanpa_perubahan_dan_tanpa_audit(): void
    {
        [, $hm, $teacher] = $this->world();
        $teacher->teacher->update(['status' => 'inactive']);

        $this->apiPatch($this->uri($teacher), $this->fxToken($hm), ['name' => 'Diubah'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'teacher_inactive_readonly');

        $this->assertSame('Guru 2001', $teacher->teacher->fresh()->fullname);
        $this->assertSame('inactive', $teacher->teacher->fresh()->status); // tidak ada reaktivasi (Matrix #61)
        $this->assertSame(0, AuditLog::count());
    }

    /** @test */
    public function guru_inactive_di_sekolah_lain_tetap_404_bukan_409(): void
    {
        [, $hmA] = $this->world();
        $schoolB = $this->fxSchool('SD Dua');
        $teacherB = $this->fxTeacher($schoolB, '3001');
        $teacherB->teacher->update(['status' => 'inactive']);

        $this->apiPatch($this->uri($teacherB), $this->fxToken($hmA), ['name' => 'Diubah'])->assertStatus(404);
    }

    // ---------- protected_field ----------

    /** @test */
    public function field_terlarang_422_protected_field_tanpa_perubahan_data(): void
    {
        [$school, $hm, $teacher] = $this->world();
        $token = $this->fxToken($hm);
        $passwordBefore = $teacher->fresh()->password;

        $extras = [
            'nip'       => '9999',
            'username'  => 'diretas',
            'status'    => 'inactive',
            'school_id' => $school->id + 100,
            'password'  => 'passwordbaru123',
            'user_id'   => 1,
            'id'        => 999,
        ];

        foreach ($extras as $field => $value) {
            // Dikirim sendirian dan bersama name valid: keduanya ditolak seluruhnya.
            foreach ([[$field => $value], ['name' => 'Tidak Boleh Tersimpan', $field => $value]] as $body) {
                $response = $this->apiPatch($this->uri($teacher), $token, $body)
                    ->assertStatus(422)
                    ->assertJsonPath('error.code', 'protected_field');

                $this->assertSame($field, $response->json('error.details.0.field'), $field);
            }
        }

        $fresh = $teacher->teacher->fresh();
        $this->assertSame('Guru 2001', $fresh->fullname);
        $this->assertSame('2001', $fresh->nip);
        $this->assertSame('active', $fresh->status);
        $this->assertSame($school->id, $fresh->school_id);
        $this->assertSame('2001', $teacher->fresh()->username);
        $this->assertSame($passwordBefore, $teacher->fresh()->password);
        $this->assertSame(0, AuditLog::count());
    }

    // ---------- validasi ----------

    /** @test */
    public function gender_tidak_valid_name_kosong_null_atau_151_karakter_422_tanpa_perubahan(): void
    {
        [, $hm, $teacher] = $this->world();
        $token = $this->fxToken($hm);

        $bodies = [
            'gender salah' => ['gender' => 'pria'],
            'gender kosong' => ['gender' => ''],
            'name kosong'  => ['name' => ''],
            'name null'    => ['name' => null],
            'name 151'     => ['name' => str_repeat('a', 151)],
        ];

        foreach ($bodies as $label => $body) {
            $this->apiPatch($this->uri($teacher), $token, $body)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed');
        }

        $fresh = $teacher->teacher->fresh();
        $this->assertSame('Guru 2001', $fresh->fullname);
        $this->assertSame('perempuan', $fresh->gender);
        $this->assertSame(0, AuditLog::count());
    }

    /** @test */
    public function name_150_karakter_diterima(): void
    {
        [, $hm, $teacher] = $this->world();
        $name = str_repeat('a', 150);

        $this->apiPatch($this->uri($teacher), $this->fxToken($hm), ['name' => $name])
            ->assertStatus(200)
            ->assertJsonPath('data.name', $name);
    }

    /** @test */
    public function name_hanya_spasi_422_dan_nilai_tidak_berubah(): void
    {
        // INTEG-TEST-002: spasi di tepi dipangkas (TrimStrings), "   " menjadi kosong -> gagal `required`.
        [, $hm, $teacher] = $this->world();

        $this->apiPatch($this->uri($teacher), $this->fxToken($hm), ['name' => '     '])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame('Guru 2001', $teacher->teacher->fresh()->fullname);
        $this->assertSame(0, AuditLog::count());
    }

    // ---------- audit_logs (D-03, D-14, D-15) ----------

    /** @test */
    public function perubahan_tercatat_satu_baris_audit_dengan_actor_action_dan_target_user_guru(): void
    {
        [, $hm, $teacher] = $this->world();

        $this->apiPatch($this->uri($teacher), $this->fxToken($hm), ['name' => 'Guru Baru', 'gender' => 'laki-laki'])
            ->assertStatus(200);

        // Satu PATCH yang mengubah dua field = SATU baris audit.
        $this->assertSame(1, AuditLog::count());

        $log = AuditLog::first();
        $this->assertSame($hm->id, $log->actor_user_id);
        $this->assertSame(self::ACTION, $log->action);
        $this->assertSame($teacher->id, $log->target_user_id); // users.id guru, bukan teachers.id
    }

    /** @test */
    public function nilai_sama_sama_setelah_trim_atau_body_kosong_200_tanpa_baris_audit(): void
    {
        [, $hm, $teacher] = $this->world();
        $token = $this->fxToken($hm);

        foreach ([
            ['name' => 'Guru 2001'],
            ['name' => '  Guru 2001  '],        // sama setelah trim (TrimStrings)
            ['gender' => 'perempuan'],
            ['name' => 'Guru 2001', 'gender' => 'perempuan'],
            [],                                  // tanpa perubahan apa pun
        ] as $body) {
            $this->apiPatch($this->uri($teacher), $token, $body)
                ->assertStatus(200)
                ->assertJsonPath('data.name', 'Guru 2001')
                ->assertJsonPath('data.gender', 'perempuan');
        }

        $this->assertSame('Guru 2001', $teacher->teacher->fresh()->fullname);
        $this->assertSame(0, AuditLog::count());
    }

    /** @test */
    public function beda_huruf_besar_kecil_saja_dihitung_berubah_dan_tercatat_satu_baris_audit(): void
    {
        [, $hm, $teacher] = $this->world();

        $this->apiPatch($this->uri($teacher), $this->fxToken($hm), ['name' => 'guru 2001'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'guru 2001');

        $this->assertSame('guru 2001', $teacher->teacher->fresh()->fullname);
        $this->assertSame(1, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $hm->id, 'action' => self::ACTION, 'target_user_id' => $teacher->id,
        ]);
    }

    /** @test */
    public function dua_perubahan_berurutan_menghasilkan_dua_baris_audit(): void
    {
        [, $hm, $teacher] = $this->world();
        $token = $this->fxToken($hm);

        $this->apiPatch($this->uri($teacher), $token, ['name' => 'Guru Satu'])->assertStatus(200);
        $this->apiPatch($this->uri($teacher), $token, ['gender' => 'laki-laki'])->assertStatus(200);

        $this->assertSame(2, AuditLog::where('actor_user_id', $hm->id)->where('action', self::ACTION)->count());
    }

    // ---------- D-04: tanpa rate limit baru ----------

    /** @test */
    public function tidak_ada_rate_limit_baru_pada_patch_guru(): void
    {
        [, $hm, $teacher] = $this->world();
        $token = $this->fxToken($hm);

        for ($i = 1; $i <= 12; $i++) {
            $status = $this->apiPatch($this->uri($teacher), $token, ['name' => "Guru Versi {$i}"])->getStatusCode();

            $this->assertSame(200, $status, "request ke-{$i}");
        }
    }
}
