<?php

namespace Tests\Feature\School;

use App\Models\Headmaster;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-13 - NIP yang bentrok dengan users.username akun role lain harus 422 (import: error baris),
// bukan 500 / `unexpected_error`. Satu akar masalah, tiga pemanggil: Create Headmaster,
// Create Teacher, Import Teacher.
//
// Akar masalah: users.username UNIQUE lintas role (username = NIP untuk Headmaster/Teacher,
// NISN untuk Student, bebas untuk Super Admin), sedangkan request hanya mengecek unique per
// tabel (headmasters.nip / teachers.nip). NIP yang sama dengan username role lain lolos
// validasi lalu gagal saat User::create() -> 500 (HTTP) atau `unexpected_error` generik (import).
//
// SoT: API Contract v1.2 §12/§15 (422 untuk NIP duplikat); DB Design §5.1 (users.username
// UNIQUE, username = NIP/NISN); Matrix F-13 (AC: 422 / error baris, test tiap pemanggil).
//
// Di luar scope: F-35 (NIP kembar ANTAR-baris dalam satu file) tetap seperti sebelumnya dan
// sengaja tidak dikunci di sini.
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class NipUsernameCollisionTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const SA_USERNAME = 'adm-9001';
    private const HM_NIP = '1001';
    private const TEACHER_NIP = '2001';
    private const STUDENT_NISN = '0051110001';

    /** Satu sekolah berisi Super Admin, Headmaster, Teacher, dan Student; semuanya punya users.username. */
    protected function world(): array
    {
        $school = $this->fxSchool();

        return [
            'school'  => $school,
            'sa'      => $this->fxSuperAdmin(self::SA_USERNAME),
            'hm'      => $this->fxHeadmaster($school, self::HM_NIP),
            'teacher' => $this->fxTeacher($school, self::TEACHER_NIP),
            'student' => $this->fxStudent($school, self::STUDENT_NISN),
        ];
    }

    protected function headmasterBody(int $schoolId, string $nip): array
    {
        return ['school_id' => $schoolId, 'name' => 'Kepsek Baru', 'nip' => $nip, 'gender' => 'perempuan'];
    }

    protected function teacherBody(int $schoolId, string $nip): array
    {
        return ['school_id' => $schoolId, 'name' => 'Guru Baru', 'nip' => $nip, 'gender' => 'laki-laki'];
    }

    protected function assertNipRejected($response): void
    {
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.0.field', 'nip');
    }

    protected function makeXlsx(array $rows): UploadedFile
    {
        $export = new class($rows) implements FromArray, WithHeadings {
            public function __construct(private array $rows) {}
            public function array(): array { return $this->rows; }
            public function headings(): array { return ['Nama Lengkap', 'NIP', 'Gender']; }
        };

        $path = 'test-imports/' . uniqid('f13_', true) . '.xlsx';
        Excel::store($export, $path, 'local');

        return new UploadedFile(storage_path('app/' . $path), 'guru.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // ---------- 1. Create Headmaster ----------

    /** @test */
    public function create_headmaster_dengan_nip_sama_dengan_username_role_lain_422_dan_hm_lama_tidak_tersuksesi(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['sa']);
        $usersBefore = User::count();

        foreach ([self::SA_USERNAME, self::TEACHER_NIP, self::STUDENT_NISN] as $collidingNip) {
            $this->assertNipRejected(
                $this->apiPost('/api/v1/headmasters', $token, $this->headmasterBody($w['school']->id, $collidingNip))
            );

            $this->assertSame(1, Headmaster::count(), "tidak ada Headmaster baru untuk NIP {$collidingNip}");
            $this->assertSame($usersBefore, User::count(), "tidak ada user baru untuk NIP {$collidingNip}");
            $this->assertDatabaseHas('headmasters', ['user_id' => $w['hm']->id, 'status' => 'active']); // suksesi tidak terjadi
        }
    }

    // ---------- 2. Create Teacher ----------

    /** @test */
    public function create_teacher_dengan_nip_sama_dengan_username_role_lain_422(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['hm']);
        $usersBefore = User::count();

        foreach ([self::SA_USERNAME, self::HM_NIP, self::STUDENT_NISN] as $collidingNip) {
            $this->assertNipRejected(
                $this->apiPost('/api/v1/teachers', $token, $this->teacherBody($w['school']->id, $collidingNip))
            );

            $this->assertSame(1, Teacher::count(), "tidak ada Teacher baru untuk NIP {$collidingNip}");
            $this->assertSame($usersBefore, User::count(), "tidak ada user baru untuk NIP {$collidingNip}");
        }
    }

    // ---------- 3. Import Teacher ----------

    /** @test */
    public function import_teacher_nip_sama_dengan_username_role_lain_jadi_error_baris_bukan_unexpected_error(): void
    {
        $w = $this->world();
        $usersBefore = User::count();

        $file = $this->makeXlsx([
            ['Guru Valid Satu', '199501012024121001', 'laki-laki'],   // baris 2
            ['Guru Bentrok HM', self::HM_NIP, 'perempuan'],           // baris 3: = username Headmaster
            ['Guru Bentrok SA', self::SA_USERNAME, 'laki-laki'],      // baris 4: = username Super Admin
            ['Guru Valid Dua', '199501012024121003', 'perempuan'],    // baris 5
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->fxToken($w['hm'])])
            ->post('/api/v1/teachers/import', ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('data.successful_rows'));
        $this->assertSame(2, $response->json('data.rejected_rows'));

        $errors = collect($response->json('data.errors'));
        $this->assertCount(2, $errors);
        $this->assertSame([3, 4], $errors->pluck('row')->all());
        $this->assertSame(['nip', 'nip'], $errors->pluck('field')->all());
        $this->assertSame(['validation_failed', 'validation_failed'], $errors->pluck('code')->all());
        $this->assertNotContains('unexpected_error', $errors->pluck('code')->all());

        // baris valid tetap masuk (partial processing); baris bentrok tidak membuat akun apa pun
        $this->assertDatabaseHas('teachers', ['nip' => '199501012024121001']);
        $this->assertDatabaseHas('teachers', ['nip' => '199501012024121003']);
        $this->assertSame($usersBefore + 2, User::count());
        $this->assertSame(0, Teacher::whereIn('nip', [self::HM_NIP, self::SA_USERNAME])->count());
    }

    // ---------- 4. Regresi: perilaku yang sudah benar tidak berubah ----------

    /** @test */
    public function nip_duplikat_per_tabel_tetap_satu_error_tanpa_pesan_ganda(): void
    {
        $w = $this->world();

        $headmasterResponse = $this->apiPost('/api/v1/headmasters', $this->fxToken($w['sa']), $this->headmasterBody($w['school']->id, self::HM_NIP));
        $this->assertNipRejected($headmasterResponse);
        $this->assertCount(1, $headmasterResponse->json('error.details'));

        $teacherResponse = $this->apiPost('/api/v1/teachers', $this->fxToken($w['hm']), $this->teacherBody($w['school']->id, self::TEACHER_NIP));
        $this->assertNipRejected($teacherResponse);
        $this->assertCount(1, $teacherResponse->json('error.details'));
    }

    /** @test */
    public function create_headmaster_dan_teacher_dengan_nip_baru_tetap_201_dan_suksesi_berjalan(): void
    {
        $w = $this->world();
        $hmToken = $this->fxToken($w['hm']);
        $saToken = $this->fxToken($w['sa']);

        // Teacher dulu: setelah suksesi token Headmaster lama sengaja ditolak (gate Stage 0-B).
        $this->apiPost('/api/v1/teachers', $hmToken, $this->teacherBody($w['school']->id, '2002'))->assertStatus(201);
        $this->assertDatabaseHas('teachers', ['nip' => '2002', 'school_id' => $w['school']->id, 'status' => 'active']);
        $this->assertDatabaseHas('users', ['username' => '2002', 'role' => 'teacher']);

        $this->apiPost('/api/v1/headmasters', $saToken, $this->headmasterBody($w['school']->id, '1002'))->assertStatus(201);
        $this->assertDatabaseHas('headmasters', ['nip' => '1002', 'status' => 'active']);
        $this->assertDatabaseHas('headmasters', ['user_id' => $w['hm']->id, 'status' => 'inactive']);
    }

    // ---------- 5. Fallback balapan (check-then-insert) ----------

    /**
     * Mensimulasikan request konkuren: setelah validasi request lolos, baris users dengan username
     * yang sama muncul tepat sebelum INSERT milik service (listener `creating` memasukkannya lewat
     * query builder di koneksi/transaksi yang sama). Insert service lalu melanggar unique constraint.
     */
    protected function simulateConcurrentUsernameInsert(string $username): void
    {
        User::creating(function (User $user) use ($username) {
            if ($user->username === $username) {
                DB::table('users')->insert([
                    'role' => 'super_admin', 'username' => $username, 'password' => 'x',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    /** @test */
    public function create_headmaster_balapan_unique_violation_jadi_422_dan_suksesi_di_rollback(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['sa']);
        $this->simulateConcurrentUsernameInsert('1099');

        $this->assertNipRejected(
            $this->apiPost('/api/v1/headmasters', $token, $this->headmasterBody($w['school']->id, '1099'))
        );

        $this->assertSame(1, Headmaster::count());
        $this->assertDatabaseHas('headmasters', ['user_id' => $w['hm']->id, 'status' => 'active']); // transaksi suksesi di-rollback
        $this->assertDatabaseMissing('users', ['username' => '1099']);
    }

    /** @test */
    public function create_teacher_balapan_unique_violation_jadi_422(): void
    {
        $w = $this->world();
        $token = $this->fxToken($w['hm']);
        $this->simulateConcurrentUsernameInsert('2099');

        $this->assertNipRejected(
            $this->apiPost('/api/v1/teachers', $token, $this->teacherBody($w['school']->id, '2099'))
        );

        $this->assertSame(1, Teacher::count());
        $this->assertDatabaseMissing('users', ['username' => '2099']);
    }
}
