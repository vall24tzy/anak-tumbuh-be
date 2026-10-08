<?php

namespace Tests\Feature\Auth;

use App\Models\Headmaster;
use App\Models\User;
use App\Services\User\InitialPasswordHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-55 (keputusan FINAL pemilik): HANYA password awal buatan SISTEM (NIP untuk Headmaster/Teacher, NISN untuk Student; create dan
// import) memakai bcrypt round rendah (InitialPasswordHasher::ROUNDS = 4). Password PILIHAN ACTOR (change-password Super Admin,
// reset-password Headmaster/Teacher/Student, admin:reset-password) tetap round konfigurasi penuh. Round global tidak diubah.
//
// Penting untuk desain test: phpunit.xml memaksa BCRYPT_ROUNDS=4, sehingga "round rendah" dan "round penuh" tidak bisa dibedakan
// di lingkungan test bawaan. setUp() menaikkan round konfigurasi ke 10 (nilai tengah, cepat tetapi jelas beda dari 4) dan
// membuang cache driver hash, supaya cost di string hash ($2y$04$ vs $2y$10$) benar-benar membuktikan titik mana yang diturunkan.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat laporan patch.

class InitialPasswordCostTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const FULL = 10;      // round konfigurasi "penuh" yang dipakai di test ini
    private const NEW_PASSWORD = 'PasswordBaru123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['hashing.bcrypt.rounds' => self::FULL]);
        Hash::forgetDrivers();
    }

    protected function cost(string $username): int
    {
        $hash = User::where('username', $username)->value('password');
        $this->assertNotNull($hash, "akun {$username} tidak ditemukan");

        return password_get_info($hash)['options']['cost'];
    }

    protected function assertInitialCost(string $username): void
    {
        $this->assertSame(InitialPasswordHasher::ROUNDS, $this->cost($username), "password awal {$username} harus round rendah");
        $this->assertNotSame(self::FULL, $this->cost($username));
    }

    protected function assertFullCost(string $username): void
    {
        $this->assertSame(self::FULL, $this->cost($username), "password {$username} harus round konfigurasi penuh, tidak ikut diturunkan");
    }

    protected function loginStaff(string $username, string $password)
    {
        return $this->postJson('/api/v1/auth/login/staff', ['username' => $username, 'password' => $password]);
    }

    protected function headers(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->fxToken($user)];
    }

    // ---------- password awal: round rendah, login tetap normal ----------

    /** @test */
    public function create_teacher_password_awal_round_rendah_dan_login_dengan_nip_berhasil(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');

        $this->postJson('/api/v1/teachers', [
            'school_id' => $school->id, 'name' => 'Guru Baru', 'nip' => '2201', 'gender' => 'laki-laki',
        ], $this->headers($hm))->assertStatus(201);

        $this->assertInitialCost('2201');
        $this->loginStaff('2201', '2201')->assertStatus(200);
        $this->loginStaff('2201', 'salah-total')->assertStatus(401);
    }

    /** @test */
    public function create_headmaster_password_awal_round_rendah_dan_login_dengan_nip_berhasil(): void
    {
        $school = $this->fxSchool();

        $this->postJson('/api/v1/headmasters', [
            'school_id' => $school->id, 'name' => 'Kepsek Baru', 'nip' => '1201', 'gender' => 'perempuan',
        ], $this->headers($this->fxSuperAdmin()))->assertStatus(201);

        $this->assertInitialCost('1201');
        $this->loginStaff('1201', '1201')->assertStatus(200);
    }

    /** @test */
    public function create_student_password_awal_round_rendah_tidak_di_hash_ulang_dan_login_nisn_berhasil(): void
    {
        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        $this->postJson('/api/v1/students', [
            'rombel_id' => $rombel->id, 'name' => 'Siswa Baru', 'nisn' => '0051119901', 'gender' => 'laki-laki',
        ], $this->headers($teacher))->assertStatus(201);

        // Cost 4 membuktikan cast `hashed` User TIDAK meng-hash ulang hash eksplisit ke round konfigurasi (10).
        $this->assertInitialCost('0051119901');
        $this->postJson('/api/v1/auth/login/student/password', ['nisn' => '0051119901', 'password' => '0051119901'])
            ->assertStatus(200);
    }

    protected function makeXlsx(array $rows, array $headings): UploadedFile
    {
        $export = new class($rows, $headings) implements FromArray, WithHeadings {
            public function __construct(private array $rows, private array $headings) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return $this->headings; }
        };

        $path = 'test-imports/' . uniqid('f55_', true) . '.xlsx';
        Excel::store($export, $path, 'local');

        return new UploadedFile(storage_path('app/' . $path), 'import.xlsx', null, null, true);
    }

    /** @test */
    public function import_teacher_dan_import_student_memakai_password_awal_round_rendah(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);

        $this->withHeaders($this->headers($hm) + ['Accept' => 'application/json'])
            ->post('/api/v1/teachers/import', ['file' => $this->makeXlsx([
                ['Guru Impor A', '2301', 'laki-laki'], ['Guru Impor B', '2302', 'perempuan'],
            ], ['Nama Lengkap', 'NIP', 'Gender'])])
            ->assertStatus(200)->assertJsonPath('data.successful_rows', 2);

        $this->withHeaders($this->headers($teacher) + ['Accept' => 'application/json'])
            ->post('/api/v1/student-imports/commit', [
                'file'      => $this->makeXlsx([
                    ['Siswa Impor A', '0051119911', 'laki-laki'], ['Siswa Impor B', '0051119912', 'perempuan'],
                ], ['Nama Lengkap', 'NISN', 'Gender']),
                'rombel_id' => $rombel->id,
            ])
            ->assertStatus(200)->assertJsonPath('data.successful_rows', 2);

        foreach (['2301', '2302', '0051119911', '0051119912'] as $username) {
            $this->assertInitialCost($username);
        }
        $this->loginStaff('2302', '2302')->assertStatus(200);
        $this->postJson('/api/v1/auth/login/student/password', ['nisn' => '0051119912', 'password' => '0051119912'])->assertStatus(200);
    }

    // ---------- password pilihan actor: TETAP round penuh ----------

    /** @test */
    public function reset_password_headmaster_oleh_super_admin_hasilnya_round_penuh_dan_login_berhasil(): void
    {
        $school = $this->fxSchool();
        $this->postJson('/api/v1/headmasters', ['school_id' => $school->id, 'name' => 'K', 'nip' => '1301', 'gender' => 'laki-laki'], $this->headers($this->fxSuperAdmin()))->assertStatus(201);
        $this->assertInitialCost('1301');
        $hmId = Headmaster::where('nip', '1301')->value('id');

        $this->postJson("/api/v1/headmasters/{$hmId}/reset-password", ['new_password' => self::NEW_PASSWORD], $this->headers(User::where('role', 'super_admin')->first()))
            ->assertStatus(200);

        $this->assertFullCost('1301');
        $this->loginStaff('1301', self::NEW_PASSWORD)->assertStatus(200);
        $this->loginStaff('1301', '1301')->assertStatus(401);
    }

    /** @test */
    public function reset_password_teacher_oleh_headmaster_hasilnya_round_penuh_dan_login_berhasil(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $this->postJson('/api/v1/teachers', ['school_id' => $school->id, 'name' => 'G', 'nip' => '2401', 'gender' => 'laki-laki'], $this->headers($hm))->assertStatus(201);
        $this->assertInitialCost('2401');
        $teacherId = \App\Models\Teacher::where('nip', '2401')->value('id');

        $this->postJson("/api/v1/teachers/{$teacherId}/reset-password", ['new_password' => self::NEW_PASSWORD], $this->headers($hm))
            ->assertStatus(200);

        $this->assertFullCost('2401');
        $this->loginStaff('2401', self::NEW_PASSWORD)->assertStatus(200);
    }

    /** @test */
    public function reset_password_student_oleh_teacher_hasilnya_round_penuh_dan_login_berhasil(): void
    {
        $school = $this->fxSchool();
        $rombel = $this->fxRombel($school, $this->fxAcademicYear($school), 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);
        $headers = $this->headers($teacher);

        $this->postJson('/api/v1/students', ['rombel_id' => $rombel->id, 'name' => 'S', 'nisn' => '0051119921', 'gender' => 'laki-laki'], $headers)->assertStatus(201);
        $this->assertInitialCost('0051119921');
        $studentId = \App\Models\Student::where('nisn', '0051119921')->value('id');

        $this->postJson("/api/v1/students/{$studentId}/reset-password", ['new_password' => self::NEW_PASSWORD], $headers)->assertStatus(200);

        $this->assertFullCost('0051119921');
        $this->postJson('/api/v1/auth/login/student/password', ['nisn' => '0051119921', 'password' => self::NEW_PASSWORD])->assertStatus(200);
    }

    /** @test */
    public function change_password_super_admin_dan_cli_admin_reset_password_hasilnya_round_penuh(): void
    {
        $sa = $this->fxSuperAdmin('sa_f55');
        $this->postJson('/api/v1/auth/change-password', ['new_password' => self::NEW_PASSWORD], $this->headers($sa))->assertStatus(200);
        $this->assertFullCost('sa_f55');
        $this->loginStaff('sa_f55', self::NEW_PASSWORD)->assertStatus(200);

        $this->artisan('admin:reset-password', ['username' => 'sa_f55'])
            ->expectsQuestion('Masukkan password baru (min 8 karakter)', 'PasswordCli456')
            ->assertSuccessful();

        $this->assertFullCost('sa_f55');
        $this->loginStaff('sa_f55', 'PasswordCli456')->assertStatus(200);
    }

    /** @test */
    public function initial_password_hasher_tidak_pernah_melebihi_round_konfigurasi(): void
    {
        // Syarat cast `hashed` Laravel 11: cost hash <= round konfigurasi. Di lingkungan yang round-nya sama dengan ROUNDS (phpunit.xml: 4) harus tetap valid.
        config(['hashing.bcrypt.rounds' => InitialPasswordHasher::ROUNDS]);
        Hash::forgetDrivers();

        $hash = InitialPasswordHasher::make('rahasia');

        $this->assertSame(InitialPasswordHasher::ROUNDS, password_get_info($hash)['options']['cost']);
        $this->assertTrue(Hash::check('rahasia', $hash));
    }
}
