<?php

namespace Tests\Feature\School;

use App\Models\AuditLog;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// INTEG-SCHOOL-001 - PATCH /api/v1/schools/{id}: ubah nama sekolah, Super Admin saja, field `name` saja.
// SoT: Dokumen Keputusan v4.0 (amandemen D-01), keputusan D-09 / D-02 / D-03 / D-04 FINAL, Matrix v5.3 #65,
// API Contract §11 (direvisi di addendum), Task Board INTEG v1.5.
//
// Yang dikunci:
//   - Super Admin: 200, nama berubah, field lain (address) tidak tersentuh
//   - Headmaster/Teacher/Student -> 403, tanpa token -> 401, id tidak ada -> 404
//   - name kosong/null/hilang/151 karakter -> 422 (150 karakter diterima); nama milik sekolah lain -> 422;
//     nama sama dengan nama sendiri -> 200 tanpa error
//   - field selain name (address, logo, id, school_id, ...) -> 422 protected_field, termasuk bila name valid
//     ikut dikirim; tidak ada perubahan data
//   - D-03/D-14/D-15: baris audit_logs tercatat (actor_user_id, action `school_name_update`, target_user_id
//     null) hanya bila nama BENAR-BENAR berubah; nama sama (termasuk sama setelah trim) = 200 tanpa baris
//     audit; beda huruf besar/kecil saja = berubah + satu baris audit; request gagal (403/404/422) = nol
//     baris audit APA PUN (AuditLog::count(), bukan hanya action ini)
//   - D-04: tidak ada rate limit baru (12 PATCH berturut-turut tidak ada yang 429)
//   - field/endpoint lama tidak berubah: GET /schools/{id} tetap, nama baru tampil di /auth/me Headmaster
//
// STATUS EKSEKUSI: ditulis tanpa menjalankan PHP; jalankan php artisan test lalu laporkan hasilnya.

class SchoolUpdateTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const ACTION = 'school_name_update'; // D-15 FINAL

    protected function apiPatch(string $uri, ?string $token = null, array $body = [])
    {
        return $this->patchJson($uri, $body, $token ? ['Authorization' => "Bearer {$token}"] : []);
    }

    protected function uri(School $school): string
    {
        return "/api/v1/schools/{$school->id}";
    }

    // ---------- Super Admin: berhasil ----------

    /** @test */
    public function super_admin_mengubah_nama_sekolah_200_dan_field_lain_tidak_tersentuh(): void
    {
        $school = $this->fxSchool('SD Lama');
        $school->update(['address' => 'Jl. Contoh 1']);
        $sa = $this->fxSuperAdmin('sa_school_upd');

        $response = $this->apiPatch($this->uri($school), $this->fxToken($sa), ['name' => 'SD Baru'])
            ->assertStatus(200);

        $response->assertJsonPath('data.id', $school->id)
            ->assertJsonPath('data.name', 'SD Baru')
            ->assertJsonPath('data.address', 'Jl. Contoh 1');

        $fresh = $school->fresh();
        $this->assertSame('SD Baru', $fresh->name);
        $this->assertSame('Jl. Contoh 1', $fresh->address);
    }

    /** @test */
    public function nama_baru_terlihat_di_get_sekolah_dan_di_auth_me_headmaster(): void
    {
        $school = $this->fxSchool('SD Lama');
        $hm = $this->fxHeadmaster($school, '1001');
        $sa = $this->fxSuperAdmin('sa_school_upd');

        $this->apiPatch($this->uri($school), $this->fxToken($sa), ['name' => 'SD Baru'])->assertStatus(200);

        $this->apiGet($this->uri($school), $this->fxToken($sa))
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'SD Baru');

        // D-09: FE kepala sekolah mengambil nama sekolah dari /auth/me.
        $this->apiGet('/api/v1/auth/me', $this->fxToken($hm))
            ->assertStatus(200)
            ->assertJsonPath('data.school.name', 'SD Baru');
    }

    /** @test */
    public function name_150_karakter_diterima(): void
    {
        $school = $this->fxSchool('SD Lama');
        $sa = $this->fxSuperAdmin('sa_school_upd');
        $name = str_repeat('a', 150);

        $this->apiPatch($this->uri($school), $this->fxToken($sa), ['name' => $name])
            ->assertStatus(200)
            ->assertJsonPath('data.name', $name);
    }

    // ---------- otorisasi ----------

    /** @test */
    public function role_selain_super_admin_403_dan_nama_tidak_berubah_tanpa_audit(): void
    {
        $school = $this->fxSchool('SD Lama');
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');
        $student = $this->fxStudent($school, '0051110001');

        foreach ([$hm, $teacher, $student] as $user) {
            $this->apiPatch($this->uri($school), $this->fxToken($user), ['name' => 'Diretas'])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'forbidden');
        }

        $this->assertSame('SD Lama', $school->fresh()->name);
        $this->assertSame(0, AuditLog::count());
    }

    /** @test */
    public function tanpa_token_401(): void
    {
        $school = $this->fxSchool('SD Lama');

        $this->apiPatch($this->uri($school), null, ['name' => 'Diretas'])->assertStatus(401);

        $this->assertSame('SD Lama', $school->fresh()->name);
    }

    /** @test */
    public function id_tidak_ada_404_tanpa_audit(): void
    {
        $sa = $this->fxSuperAdmin('sa_school_upd');

        $this->apiPatch('/api/v1/schools/999999', $this->fxToken($sa), ['name' => 'SD Baru'])
            ->assertStatus(404);

        $this->assertSame(0, AuditLog::count());
    }

    // ---------- validasi name ----------

    /** @test */
    public function name_kosong_null_hilang_atau_lebih_150_karakter_422_tanpa_perubahan_dan_tanpa_audit(): void
    {
        $school = $this->fxSchool('SD Lama');
        $token = $this->fxToken($this->fxSuperAdmin('sa_school_upd'));

        $bodies = [
            'kosong' => ['name' => ''],
            'null'   => ['name' => null],
            'hilang' => [],
            '151'    => ['name' => str_repeat('a', 151)],
        ];

        foreach ($bodies as $label => $body) {
            $this->apiPatch($this->uri($school), $token, $body)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed');
        }

        $this->assertSame('SD Lama', $school->fresh()->name);
        $this->assertSame(0, AuditLog::count());
    }

    /** @test */
    public function nama_milik_sekolah_lain_422_tapi_nama_sendiri_200_tanpa_audit(): void
    {
        $school = $this->fxSchool('SD Satu');
        $this->fxSchool('SD Dua');
        $token = $this->fxToken($this->fxSuperAdmin('sa_school_upd'));

        $this->apiPatch($this->uri($school), $token, ['name' => 'SD Dua'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
        $this->assertSame('SD Satu', $school->fresh()->name);

        // Nama sama dengan nama sendiri: bukan duplikat, no-op, tidak dicatat sebagai perubahan.
        $this->apiPatch($this->uri($school), $token, ['name' => 'SD Satu'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'SD Satu');
        $this->assertSame(0, AuditLog::count());
    }

    // ---------- protected_field ----------

    /** @test */
    public function field_selain_name_ditolak_422_protected_field_tanpa_perubahan_data(): void
    {
        $school = $this->fxSchool('SD Lama');
        $school->update(['address' => 'Jl. Contoh 1']);
        $token = $this->fxToken($this->fxSuperAdmin('sa_school_upd'));

        $extras = [
            'address'        => 'Jl. Diubah 9',
            'logo'           => 'x.png',
            'id'             => 999,
            'school_id'      => 999,
            'logo_path'      => 'logos/x.png',
            'teachers_count' => 5,
        ];

        foreach ($extras as $field => $value) {
            // Dikirim sendirian dan bersama name yang valid: keduanya harus ditolak seluruhnya.
            foreach ([[$field => $value], ['name' => 'SD Baru', $field => $value]] as $body) {
                $response = $this->apiPatch($this->uri($school), $token, $body)
                    ->assertStatus(422)
                    ->assertJsonPath('error.code', 'protected_field');

                $this->assertSame($field, $response->json('error.details.0.field'), $field);
            }
        }

        $fresh = $school->fresh();
        $this->assertSame('SD Lama', $fresh->name);
        $this->assertSame('Jl. Contoh 1', $fresh->address);
        $this->assertNull($fresh->logo_path);
        $this->assertSame(0, AuditLog::count());
    }

    // ---------- audit_logs (D-03) ----------

    /** @test */
    public function perubahan_nama_tercatat_di_audit_logs_dengan_actor_dan_action_benar(): void
    {
        $school = $this->fxSchool('SD Lama');
        $sa = $this->fxSuperAdmin('sa_school_upd');

        $this->apiPatch($this->uri($school), $this->fxToken($sa), ['name' => 'SD Baru'])->assertStatus(200);

        $this->assertSame(1, AuditLog::where('action', self::ACTION)->count());

        $log = AuditLog::where('action', self::ACTION)->first();
        $this->assertSame($sa->id, $log->actor_user_id);
        $this->assertSame(self::ACTION, $log->action);
        $this->assertNull($log->target_user_id); // D-03: target_user_id null diperbolehkan

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $sa->id, 'action' => self::ACTION, 'target_user_id' => null,
        ]);
    }

    /** @test */
    public function dua_perubahan_berurutan_menghasilkan_dua_baris_audit(): void
    {
        $school = $this->fxSchool('SD Lama');
        $sa = $this->fxSuperAdmin('sa_school_upd');
        $token = $this->fxToken($sa);

        $this->apiPatch($this->uri($school), $token, ['name' => 'SD Baru'])->assertStatus(200);
        $this->apiPatch($this->uri($school), $token, ['name' => 'SD Terbaru'])->assertStatus(200);

        $this->assertSame(2, AuditLog::where('actor_user_id', $sa->id)->where('action', self::ACTION)->count());
        $this->assertSame('SD Terbaru', $school->fresh()->name);
    }

    /** @test */
    public function nama_sama_setelah_trim_200_nama_tetap_dan_nol_baris_audit(): void
    {
        $school = $this->fxSchool('SD Satu');
        $token = $this->fxToken($this->fxSuperAdmin('sa_school_upd'));

        // D-14: "sama" dibandingkan setelah normalisasi yang sama dengan saat menyimpan (trim).
        $this->apiPatch($this->uri($school), $token, ['name' => '  SD Satu  '])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'SD Satu');

        $this->assertSame('SD Satu', $school->fresh()->name);
        $this->assertSame(0, AuditLog::count());
    }

    /** @test */
    public function beda_huruf_besar_kecil_saja_dihitung_berubah_dan_tercatat_satu_baris_audit(): void
    {
        $school = $this->fxSchool('SD Satu');
        $sa = $this->fxSuperAdmin('sa_school_upd');

        $this->apiPatch($this->uri($school), $this->fxToken($sa), ['name' => 'sd satu'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'sd satu');

        $this->assertSame('sd satu', $school->fresh()->name);
        $this->assertSame(1, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $sa->id, 'action' => self::ACTION, 'target_user_id' => null,
        ]);
    }

    /** @test */
    public function name_hanya_spasi_422_dan_nama_tidak_berubah(): void
    {
        // INTEG-TEST-002: spasi di tepi dipangkas (TrimStrings), "   " menjadi kosong -> gagal `required`.
        $school = $this->fxSchool('SD Lama');
        $token = $this->fxToken($this->fxSuperAdmin('sa_school_upd'));

        $this->apiPatch($this->uri($school), $token, ['name' => '     '])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertSame('SD Lama', $school->fresh()->name);
        $this->assertSame(0, AuditLog::count());
    }

    // ---------- D-04: tanpa rate limit baru ----------

    /** @test */
    public function tidak_ada_rate_limit_baru_pada_patch_sekolah(): void
    {
        $school = $this->fxSchool('SD Lama');
        $token = $this->fxToken($this->fxSuperAdmin('sa_school_upd'));

        for ($i = 1; $i <= 12; $i++) {
            $status = $this->apiPatch($this->uri($school), $token, ['name' => "SD Versi {$i}"])->getStatusCode();

            $this->assertSame(200, $status, "request ke-{$i}");
        }
    }
}
