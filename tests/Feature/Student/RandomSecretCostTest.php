<?php

namespace Tests\Feature\Student;

use App\Models\QrToken;
use App\Models\Student;
use App\Models\User;
use App\Services\Student\RandomSecretHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// DEPLOY-IMPORT-001 (D-36, Opsi 1, diputuskan Pemilik 16 Nov 2026): nilai ACAK berentropi tinggi buatan SISTEM
// (placeholder token QR saat create/import Student, dan token QR hasil regenerate) di-hash dengan bcrypt round rendah
// lewat RandomSecretHasher (cost = min(4, round konfigurasi)). Token lama (hash round penuh) tetap valid tanpa migrasi.
//
// Desain test: phpunit.xml memaksa BCRYPT_ROUNDS=4, sehingga round rendah dan penuh tidak bisa dibedakan di lingkungan
// test bawaan. setUp() menaikkan round konfigurasi ke 10 dan membuang cache driver hash (pola InitialPasswordCostTest),
// supaya cost di string hash ($2y$04$ vs $2y$10$) benar-benar membuktikan titik mana yang diturunkan.
//
// Yang TIDAK diuji di sini: durasi nyata di Vercel (TEST-001 di Preview tanpa BCRYPT_ROUNDS=4, diukur Orang 2).
// Test ini membuktikan cost hash dan bahwa 150 dan 200 baris import tetap berhasil.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat team-log DEPLOY-IMPORT-001.

class RandomSecretCostTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const FULL = 10;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hashing.bcrypt.rounds' => self::FULL]);
        Hash::forgetDrivers();
    }

    protected function cost(string $hash): int
    {
        return password_get_info($hash)['options']['cost'];
    }

    protected function headers(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->fxToken($user)];
    }

    /** Meniru QrTokenService::signPayload() (protected): base64(json) + "." + HMAC-SHA256(APP_KEY). */
    protected function signedPayload(int $studentId, string $rawToken): string
    {
        $payload = base64_encode(json_encode(['student_id' => $studentId, 'token' => $rawToken]));

        return $payload . '.' . hash_hmac('sha256', $payload, config('app.key'));
    }

    protected function makeXlsx(array $rows): UploadedFile
    {
        $export = new class($rows) implements FromArray, WithHeadings {
            public function __construct(private array $rows) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return ['Nama Lengkap', 'NISN', 'Gender']; }
        };

        $path = 'test-imports/' . uniqid('d36_', true) . '.xlsx';
        Excel::store($export, $path, 'local');

        return new UploadedFile(storage_path('app/' . $path), 'import.xlsx', null, null, true);
    }

    /** @return array{0: User, 1: \App\Models\Rombel, 2: \App\Models\School} [$teacherUser, $rombel, $school] */
    protected function teacherScenario(): array
    {
        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        return [$teacher, $rombel, $school];
    }

    // ---------- helper ----------

    /** @test */
    public function helper_memakai_round_rendah_walau_round_konfigurasi_penuh_dan_hash_tetap_valid(): void
    {
        $hash = RandomSecretHasher::make('nilai-acak');

        $this->assertSame(RandomSecretHasher::ROUNDS, $this->cost($hash));
        $this->assertNotSame(self::FULL, $this->cost($hash));
        $this->assertTrue(Hash::check('nilai-acak', $hash));
        $this->assertFalse(Hash::check('nilai-lain', $hash));
    }

    /** @test */
    public function helper_tidak_pernah_melebihi_round_konfigurasi(): void
    {
        config(['hashing.bcrypt.rounds' => RandomSecretHasher::ROUNDS]);
        Hash::forgetDrivers();

        $hash = RandomSecretHasher::make('nilai-acak');

        $this->assertSame(RandomSecretHasher::ROUNDS, $this->cost($hash));
        $this->assertTrue(Hash::check('nilai-acak', $hash));
    }

    // ---------- create / import: placeholder token QR round rendah ----------

    /** @test */
    public function create_student_menyimpan_placeholder_token_qr_dengan_round_rendah(): void
    {
        [$teacher, $rombel] = $this->teacherScenario();

        $this->postJson('/api/v1/students', [
            'rombel_id' => $rombel->id, 'name' => 'Siswa Baru', 'nisn' => '0051119901', 'gender' => 'laki-laki',
        ], $this->headers($teacher))->assertStatus(201);

        $studentId = Student::where('nisn', '0051119901')->value('id');
        $tokenHash = QrToken::where('student_id', $studentId)->value('token_hash');

        $this->assertNotNull($tokenHash);
        $this->assertSame(RandomSecretHasher::ROUNDS, $this->cost($tokenHash));
    }

    /** @test */
    public function import_150_baris_berhasil_dan_semua_token_qr_round_rendah(): void
    {
        $this->assertImportRows(150);
    }

    /** @test */
    public function import_200_baris_batas_maksimum_berhasil_dan_semua_token_qr_round_rendah(): void
    {
        $this->assertImportRows(200);
    }

    protected function assertImportRows(int $count): void
    {
        [$teacher, $rombel, $school] = $this->teacherScenario();

        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['Siswa Impor ' . $i, '00511' . str_pad((string) $i, 5, '0', STR_PAD_LEFT), $i % 2 ? 'laki-laki' : 'perempuan'];
        }

        $this->withHeaders($this->headers($teacher) + ['Accept' => 'application/json'])
            ->post('/api/v1/student-imports/commit', ['file' => $this->makeXlsx($rows), 'rombel_id' => $rombel->id])
            ->assertStatus(200)
            ->assertJsonPath('data.total_rows', $count)
            ->assertJsonPath('data.successful_rows', $count)
            ->assertJsonPath('data.rejected_rows', 0);

        $studentIds = Student::where('school_id', $school->id)->pluck('id');
        $hashes = QrToken::whereIn('student_id', $studentIds)->pluck('token_hash');

        $this->assertCount($count, $hashes);
        foreach ($hashes as $hash) {
            $this->assertSame(RandomSecretHasher::ROUNDS, $this->cost($hash));
        }
    }

    // ---------- regenerate dan login QR ----------

    /** @test */
    public function regenerate_menyimpan_token_round_rendah_dan_login_qr_dengan_payload_baru_berhasil(): void
    {
        [$teacher, $rombel, $school] = $this->teacherScenario();
        $studentUser = $this->fxStudent($school, '0051119911');
        $student = $studentUser->student;
        $this->fxStudentAssignment($student, $rombel);
        QrToken::create(['student_id' => $student->id, 'token_hash' => RandomSecretHasher::make((string) Str::uuid())]);

        $response = $this->postJson("/api/v1/qr-tokens/{$student->id}/regenerate", [], $this->headers($teacher))
            ->assertStatus(200);

        $this->assertSame(
            RandomSecretHasher::ROUNDS,
            $this->cost(QrToken::where('student_id', $student->id)->value('token_hash')),
        );

        $this->postJson('/api/v1/auth/login/student/qr', ['qr_payload' => $response->json('data.qr_payload')])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user']]);
    }

    /** @test */
    public function login_qr_dengan_token_hash_lama_round_penuh_tetap_lolos_tanpa_migrasi(): void
    {
        [, $rombel, $school] = $this->teacherScenario();
        $studentUser = $this->fxStudent($school, '0051119921');
        $student = $studentUser->student;
        $this->fxStudentAssignment($student, $rombel);

        // Meniru baris lama dari sebelum D-36: hash bcrypt round penuh (10 di test ini, 12 di production).
        $rawToken = Str::random(64);
        QrToken::create(['student_id' => $student->id, 'token_hash' => Hash::make($rawToken, ['rounds' => self::FULL])]);
        $this->assertSame(self::FULL, $this->cost(QrToken::where('student_id', $student->id)->value('token_hash')));

        $this->postJson('/api/v1/auth/login/student/qr', ['qr_payload' => $this->signedPayload($student->id, $rawToken)])
            ->assertStatus(200);

        $this->postJson('/api/v1/auth/login/student/qr', ['qr_payload' => $this->signedPayload($student->id, 'token-salah')])
            ->assertStatus(401);
    }
}
