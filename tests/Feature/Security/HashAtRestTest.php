<?php

namespace Tests\Feature\Security;

use App\Models\QrToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// SECURITY-001 butir 7 - password dan token_hash QR tidak pernah tersimpan plaintext, dan tidak ada
// respons create yang membocorkannya. Akun dibuat lewat jalur produksi (HTTP) supaya hash awal
// (InitialPasswordHasher, F-55) dan token QR (StudentService/QrTokenService) benar-benar terpakai.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; jalankan `php artisan test --filter=HashAtRestTest`.

class HashAtRestTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const BCRYPT = '/^\$2y\$\d{2}\$.{53}$/';

    protected function assertNoSecretKeys($response): void
    {
        $body = $response->getContent();

        $this->assertStringNotContainsString('"password"', $body);
        $this->assertStringNotContainsString('"token_hash"', $body);
    }

    /** @test */
    public function password_semua_akun_dan_token_hash_qr_berupa_bcrypt_bukan_plaintext(): void
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');

        $sa = $this->fxSuperAdmin();

        // Super Admin -> Headmaster (password awal = NIP, hash ringan F-55).
        $hm = $this->apiPost('/api/v1/headmasters', $this->fxToken($sa), [
            'school_id' => $school->id, 'name' => 'Kepsek Baru', 'nip' => '197001012000011001', 'gender' => 'laki-laki',
        ]);
        $hm->assertSuccessful();
        $this->assertNoSecretKeys($hm);

        // Headmaster -> Teacher.
        $hmUser = User::where('username', '197001012000011001')->firstOrFail();
        $tc = $this->apiPost('/api/v1/teachers', $this->fxToken($hmUser), [
            'school_id' => $school->id, 'name' => 'Guru Baru', 'nip' => '198001012010012002', 'gender' => 'perempuan',
        ]);
        $tc->assertSuccessful();
        $this->assertNoSecretKeys($tc);

        // Teacher (fixture beranggota rombel) -> Student, lalu regenerate QR.
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);
        $token = $this->fxToken($teacher);

        $st = $this->apiPost('/api/v1/students', $token, [
            'rombel_id' => $rombel->id, 'name' => 'Siswa Baru', 'nisn' => '0051234567', 'gender' => 'perempuan',
        ]);
        $st->assertStatus(201);
        $this->assertNoSecretKeys($st);

        $studentId = (int) $st->json('data.id');
        $qr = $this->apiPost("/api/v1/qr-tokens/{$studentId}/regenerate", $token);
        $qr->assertStatus(200);
        $this->assertNoSecretKeys($qr);

        // 1) Semua password di DB = bcrypt, tidak sama dengan username/NIP/NISN, dan tidak mengandungnya.
        $users = User::all();
        $this->assertGreaterThanOrEqual(5, $users->count());

        foreach ($users as $user) {
            $stored = $user->getRawOriginal('password');

            $this->assertMatchesRegularExpression(self::BCRYPT, $stored, "password {$user->username} bukan bcrypt");
            $this->assertNotSame($user->username, $stored);
            $this->assertStringNotContainsString($user->username, $stored);
        }

        // 2) Password awal terbukti hasil hash: cocok dengan NIP/NISN lewat Hash::check.
        $this->assertTrue(Hash::check('197001012000011001', User::where('username', '197001012000011001')->value('password')));
        $this->assertTrue(Hash::check('0051234567', User::where('username', '0051234567')->value('password')));

        // 3) Semua token_hash QR (placeholder saat create dan hasil regenerate) = bcrypt.
        $this->assertGreaterThanOrEqual(1, QrToken::count());

        foreach (QrToken::all() as $row) {
            $this->assertMatchesRegularExpression(self::BCRYPT, $row->token_hash);
        }

        // 4) Raw token di qr_payload tidak ada di DB; yang tersimpan hanya hash-nya.
        [$payloadB64] = explode('.', $qr->json('data.qr_payload'));
        $raw = json_decode(base64_decode($payloadB64), true)['token'];

        $stored = QrToken::where('student_id', $studentId)->value('token_hash');
        $this->assertNotSame($raw, $stored);
        $this->assertStringNotContainsString($raw, $stored);
        $this->assertTrue(Hash::check($raw, $stored));
    }

    /** @test */
    public function model_user_menyembunyikan_password_dari_serialisasi(): void
    {
        $user = $this->fxSuperAdmin('sa_hidden');

        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertStringNotContainsString('password', $user->toJson());
    }
}
