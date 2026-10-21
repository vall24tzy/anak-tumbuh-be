<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\Habit;
use App\Models\Headmaster;
use App\Models\Indicator;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\StudentHabit;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Database\Seeders\HabitIndicatorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// INTEG-REKAP-001 — Matrix #62, Dokumen Keputusan v4.0 amandemen 20 Okt 2026 (D-07, D-10, D-11).
// GET /api/v1/habits/recap?rombel_id=&from=&to= — Teacher own active rombel, read-only.
// Habit/indikator memakai seeder produksi (HabitIndicatorSeeder: 7 habit x 4 indikator).
// Setup memakai Model::create() berantai (bukan factory), sama seperti test habit lain.

class HabitRecapTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HabitIndicatorSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- setup helpers

    /** @return array{0: School, 1: AcademicYear, 2: Rombel} */
    protected function makeSchool(string $name, string $rombelName = 'VII-A'): array
    {
        $school = School::create(['name' => $name]);

        $year = AcademicYear::create([
            'school_id'  => $school->id,
            'name'       => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date'   => '2027-06-30',
            'is_active'  => true,
        ]);

        $rombel = Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $year->id,
            'name'             => $rombelName,
            'grade_level'      => 7,
        ]);

        return [$school, $year, $rombel];
    }

    protected function makeRombel(School $school, AcademicYear $year, string $name): Rombel
    {
        return Rombel::create([
            'school_id'        => $school->id,
            'academic_year_id' => $year->id,
            'name'             => $name,
            'grade_level'      => 7,
        ]);
    }

    protected function makeTeacher(School $school, AcademicYear $year, ?Rombel $rombel, string $nip, string $status = 'active'): User
    {
        $user = User::create(['role' => 'teacher', 'username' => $nip, 'password' => 'secret']);

        $teacher = Teacher::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Guru ' . $nip,
            'nip'       => $nip,
            'gender'    => 'perempuan',
            'status'    => $status,
        ]);

        if ($rombel) {
            TeacherClassAssignment::create([
                'teacher_id'       => $teacher->id,
                'school_id'        => $school->id,
                'academic_year_id' => $year->id,
                'rombel_id'        => $rombel->id,
                'is_active'        => true,
            ]);
        }

        return $user;
    }

    protected function makeStudent(
        School $school,
        AcademicYear $year,
        Rombel $rombel,
        string $fullname,
        string $status = 'active',
        string $assignmentStatus = 'active',
    ): Student {
        $nisn = '81' . str_pad((string) (++$this->seq), 8, '0', STR_PAD_LEFT);

        $user = User::create(['role' => 'student', 'username' => $nisn, 'password' => $nisn]);

        $student = Student::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => $fullname,
            'nisn'      => $nisn,
            'gender'    => 'laki-laki',
            'poin'      => 0,
            'status'    => $status,
        ]);

        StudentClassAssignment::create([
            'student_id'       => $student->id,
            'school_id'        => $school->id,
            'rombel_id'        => $rombel->id,
            'academic_year_id' => $year->id,
            'status'           => $assignmentStatus,
        ]);

        return $student;
    }

    /** Entri 6 habit biasa; $tier = sort_order indikator (1..4). */
    protected function entry(Student $student, string $habitCode, int $tier, string $date, string $initiative): StudentHabit
    {
        $habit     = Habit::where('code', $habitCode)->firstOrFail();
        $indicator = Indicator::where('habit_id', $habit->id)->where('sort_order', $tier)->firstOrFail();

        return StudentHabit::create([
            'student_id'   => $student->id,
            'habit_id'     => $habit->id,
            'indicator_id' => $indicator->id,
            'habit_date'   => $date,
            'initiative'   => $initiative,
        ]);
    }

    /** Entri Bermasyarakat; $tiers = sort_order indikator terpilih (1-3 positif, 4 = Kurang bermasyarakat). */
    protected function bermasyarakat(Student $student, array $tiers, string $date, string $initiative): StudentHabit
    {
        $habit = Habit::where('code', 'bermasyarakat')->firstOrFail();

        $entry = StudentHabit::create([
            'student_id'   => $student->id,
            'habit_id'     => $habit->id,
            'indicator_id' => null,
            'habit_date'   => $date,
            'initiative'   => $initiative,
        ]);

        foreach ($tiers as $tier) {
            $entry->selectedIndicators()->create([
                'indicator_id' => Indicator::where('habit_id', $habit->id)->where('sort_order', $tier)->value('id'),
            ]);
        }

        return $entry;
    }

    protected function asUser(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($user));
    }

    protected function recapUrl(Rombel $rombel, ?string $from = null, ?string $to = null): string
    {
        $url = '/api/v1/habits/recap?rombel_id=' . $rombel->id;

        if ($from !== null) {
            $url .= '&from=' . $from;
        }
        if ($to !== null) {
            $url .= '&to=' . $to;
        }

        return $url;
    }

    /** Roster 3 siswa + data tetap yang dipakai beberapa test. */
    protected function seedScenario(): array
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');

        $s1 = $this->makeStudent($school, $year, $rombel, 'Ani');
        $s2 = $this->makeStudent($school, $year, $rombel, 'Budi');
        $s3 = $this->makeStudent($school, $year, $rombel, 'Citra');

        // Dalam rentang 2026-10-12 s/d 2026-10-14.
        $this->entry($s1, 'bangun_cepat', 1, '2026-10-12', 'mandiri');   // 50 + 10 = 60
        $this->entry($s1, 'beribadah', 2, '2026-10-12', 'disuruh');      // 25
        $this->entry($s2, 'bangun_cepat', 4, '2026-10-12', 'disuruh');   // 10
        $this->bermasyarakat($s1, [1, 2], '2026-10-13', 'mandiri');      // 2 positif = 25 + 10 = 35
        $this->bermasyarakat($s3, [4], '2026-10-13', 'disuruh');         // Kurang bermasyarakat = 10

        // Di luar rentang (sebelum dan sesudah) — tidak boleh dihitung.
        $this->entry($s1, 'bangun_cepat', 1, '2026-10-11', 'mandiri');
        $this->entry($s1, 'bangun_cepat', 1, '2026-10-15', 'mandiri');
        $this->bermasyarakat($s1, [1, 2, 3], '2026-10-11', 'mandiri');
        $this->bermasyarakat($s1, [1, 2, 3], '2026-10-15', 'mandiri');

        return compact('school', 'year', 'rombel', 'teacher', 's1', 's2', 's3');
    }

    // ---------------------------------------------------------------- happy path

    /** @test */
    public function rekap_menghitung_persentase_per_hari_total_poin_dan_terisi_per_kebiasaan(): void
    {
        $d = $this->seedScenario();

        $response = $this->asUser($d['teacher'])->getJson($this->recapUrl($d['rombel'], '2026-10-12', '2026-10-14'));

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame(['id' => $d['rombel']->id, 'name' => 'VII-A'], $data['rombel']);
        $this->assertSame('2026-10-12', $data['from']);
        $this->assertSame('2026-10-14', $data['to']);
        $this->assertSame(3, $data['student_count']);

        // Penyebut = 3 siswa x 7 habit = 21.
        $this->assertEquals([
            ['date' => '2026-10-12', 'filled' => 3, 'percentage' => 14.29],
            ['date' => '2026-10-13', 'filled' => 2, 'percentage' => 9.52],
            ['date' => '2026-10-14', 'filled' => 0, 'percentage' => 0],
        ], $data['per_day']);

        $this->assertEquals([
            ['student_id' => $d['s1']->id, 'fullname' => 'Ani', 'total_poin' => 120],
            ['student_id' => $d['s2']->id, 'fullname' => 'Budi', 'total_poin' => 10],
            ['student_id' => $d['s3']->id, 'fullname' => 'Citra', 'total_poin' => 10],
        ], $data['per_student']);

        // 7 kebiasaan, Bermasyarakat ikut sebagai satu kebiasaan.
        $this->assertCount(7, $data['per_habit']);
        $filled = array_column($data['per_habit'], 'filled', 'code');
        $this->assertSame(2, $filled['bangun_cepat']);
        $this->assertSame(1, $filled['beribadah']);
        $this->assertSame(2, $filled['bermasyarakat']);
        $this->assertSame(0, $filled['gemar_belajar']);
        $this->assertSame(0, $filled['berolahraga']);
        $this->assertSame(0, $filled['makan_sehat_bergizi']);
        $this->assertSame(0, $filled['tidur_cepat']);
        $this->assertSame(
            ['habit_id', 'code', 'name', 'filled'],
            array_keys($data['per_habit'][0])
        );
    }

    /** @test */
    public function total_poin_sama_dengan_poin_awarded_dari_scoring_get_entries(): void
    {
        $d = $this->seedScenario();

        $recap = $this->asUser($d['teacher'])
            ->getJson($this->recapUrl($d['rombel'], '2026-10-12', '2026-10-14'))
            ->json('data.per_student');
        $recapPoin = array_column($recap, 'total_poin', 'student_id');

        // Jumlahkan poin_awarded dari endpoint GET yang sudah ada (scoring yang sama dengan POST).
        $fromGet = [];
        foreach (['2026-10-12', '2026-10-13', '2026-10-14'] as $date) {
            foreach (['/api/v1/habits/entries', '/api/v1/bermasyarakat/entries'] as $endpoint) {
                $rows = $this->asUser($d['teacher'])->getJson("{$endpoint}?date={$date}&per_page=100")->json('data');
                foreach ($rows as $row) {
                    $fromGet[$row['student_id']] = ($fromGet[$row['student_id']] ?? 0) + $row['poin_awarded'];
                }
            }
        }

        foreach ([$d['s1'], $d['s2'], $d['s3']] as $student) {
            $this->assertSame($fromGet[$student->id] ?? 0, $recapPoin[$student->id], 'poin siswa ' . $student->fullname);
        }
    }

    /** @test */
    public function siswa_non_roster_dan_entri_di_luar_rentang_tidak_dihitung(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');
        $in      = $this->makeStudent($school, $year, $rombel, 'Dina');
        $this->entry($in, 'tidur_cepat', 1, '2026-10-12', 'mandiri'); // 60

        // Bukan roster: inactive, graduated (assignment ended), pindah (assignment ended).
        $inactive  = $this->makeStudent($school, $year, $rombel, 'Eka', 'inactive', 'active');
        $graduated = $this->makeStudent($school, $year, $rombel, 'Fani', 'graduated', 'ended');
        $moved     = $this->makeStudent($school, $year, $rombel, 'Gita', 'active', 'ended');

        // Rombel lain di sekolah yang sama dan sekolah lain.
        $otherRombel = $this->makeRombel($school, $year, 'VII-B');
        $sameSchool  = $this->makeStudent($school, $year, $otherRombel, 'Hana');
        [$schoolB, $yearB, $rombelB] = $this->makeSchool('SMP B');
        $otherSchool = $this->makeStudent($schoolB, $yearB, $rombelB, 'Indra');

        foreach ([$inactive, $graduated, $moved, $sameSchool, $otherSchool] as $s) {
            $this->entry($s, 'tidur_cepat', 1, '2026-10-12', 'mandiri');
            $this->bermasyarakat($s, [1, 2, 3], '2026-10-12', 'mandiri');
        }

        $data = $this->asUser($teacher)
            ->getJson($this->recapUrl($rombel, '2026-10-12', '2026-10-12'))
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(1, $data['student_count']);
        $this->assertSame(['Dina'], array_column($data['per_student'], 'fullname'));
        $this->assertSame(1, $data['per_day'][0]['filled']);
        $this->assertEquals(14.29, $data['per_day'][0]['percentage']); // 1 / (1 x 7)
        $this->assertSame(60, $data['per_student'][0]['total_poin']);
        $this->assertSame(0, array_column($data['per_habit'], 'filled', 'code')['bermasyarakat']);
    }

    /** @test */
    public function rombel_tanpa_siswa_aktif_mengembalikan_nol_tanpa_error(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');

        $data = $this->asUser($teacher)
            ->getJson($this->recapUrl($rombel, '2026-10-12', '2026-10-13'))
            ->assertStatus(200)
            ->json('data');

        $this->assertSame(0, $data['student_count']);
        $this->assertSame([], $data['per_student']);
        $this->assertEquals(0, $data['per_day'][0]['percentage']);
        $this->assertCount(7, $data['per_habit']);
    }

    // ---------------------------------------------------------------- rentang tanggal (D-07)

    /** @test */
    public function default_rentang_7_hari_terakhir_memakai_tanggal_asia_jakarta(): void
    {
        // 18:00 UTC tanggal 20 = 01:00 WIB tanggal 21.
        Carbon::setTestNow(Carbon::parse('2026-10-20 18:00:00', 'UTC'));

        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');

        $data = $this->asUser($teacher)->getJson($this->recapUrl($rombel))->assertStatus(200)->json('data');

        $this->assertSame('2026-10-21', $data['to']);
        $this->assertSame('2026-10-15', $data['from']);
        $this->assertCount(7, $data['per_day']);
        $this->assertSame('2026-10-15', $data['per_day'][0]['date']);
        $this->assertSame('2026-10-21', $data['per_day'][6]['date']);
    }

    /** @test */
    public function hanya_to_diberikan_from_otomatis_to_dikurangi_6_hari(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');

        $data = $this->asUser($teacher)->getJson($this->recapUrl($rombel, null, '2026-10-20'))
            ->assertStatus(200)->json('data');

        $this->assertSame('2026-10-14', $data['from']);
        $this->assertSame('2026-10-20', $data['to']);
        $this->assertCount(7, $data['per_day']);
    }

    /** @test */
    public function hanya_from_diberikan_to_default_hari_ini_dan_batas_31_hari_tetap_berlaku(): void
    {
        // 03:00 UTC tanggal 20 = 10:00 WIB tanggal 20.
        Carbon::setTestNow(Carbon::parse('2026-10-20 03:00:00', 'UTC'));

        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');

        $recent = $this->asUser($teacher)->getJson($this->recapUrl($rombel, '2026-10-14'))
            ->assertStatus(200)->json('data');
        $this->assertSame('2026-10-20', $recent['to']);
        $this->assertCount(7, $recent['per_day']);

        // 20 Sep s/d 20 Okt = 31 hari (diterima); 19 Sep = 32 hari (ditolak).
        $this->asUser($teacher)->getJson($this->recapUrl($rombel, '2026-09-20'))->assertStatus(200);
        $this->asUser($teacher)->getJson($this->recapUrl($rombel, '2026-09-19'))
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
    }

    /** @test */
    public function rentang_31_hari_diterima_dan_32_hari_ditolak_422(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');

        $ok = $this->asUser($teacher)->getJson($this->recapUrl($rombel, '2026-10-01', '2026-10-31'));
        $ok->assertStatus(200);
        $this->assertCount(31, $ok->json('data.per_day'));

        $this->asUser($teacher)->getJson($this->recapUrl($rombel, '2026-10-01', '2026-11-01'))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    /** @test */
    public function from_setelah_to_dan_format_tanggal_salah_ditolak_422(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');

        foreach ([
            $this->recapUrl($rombel, '2026-10-14', '2026-10-12'),
            $this->recapUrl($rombel, '14-10-2026', '2026-10-15'),
            $this->recapUrl($rombel, '2026-10-01', 'bukan-tanggal'),
        ] as $url) {
            $this->asUser($teacher)->getJson($url)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed');
        }
    }

    /** @test */
    public function rombel_id_wajib_dan_harus_integer_422(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001');

        foreach (['/api/v1/habits/recap', '/api/v1/habits/recap?rombel_id=abc', '/api/v1/habits/recap?rombel_id=0'] as $url) {
            $this->asUser($teacher)->getJson($url)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed');
        }
    }

    // ---------------------------------------------------------------- otorisasi & scope

    /** @test */
    public function tanpa_token_401_dan_role_selain_teacher_403(): void
    {
        $d = $this->seedScenario();

        $this->getJson($this->recapUrl($d['rombel']))->assertStatus(401);

        $headmasterUser = User::create(['role' => 'headmaster', 'username' => '2001', 'password' => 'secret']);
        Headmaster::create([
            'user_id' => $headmasterUser->id, 'school_id' => $d['school']->id,
            'fullname' => 'Kepala', 'nip' => '2001', 'gender' => 'laki-laki', 'status' => 'active',
        ]);
        $superAdmin = User::create(['role' => 'super_admin', 'username' => 'sa-recap', 'password' => 'secret']);

        foreach ([$headmasterUser, $superAdmin, $d['s1']->user] as $user) {
            $this->asUser($user)->getJson($this->recapUrl($d['rombel']))
                ->assertStatus(403);
        }
    }

    /** @test */
    public function rombel_sekolah_lain_dan_rombel_tidak_ada_menghasilkan_404_yang_sama(): void
    {
        $d = $this->seedScenario();
        [, , $rombelB] = $this->makeSchool('SMP B');

        $foreign = $this->asUser($d['teacher'])->getJson($this->recapUrl($rombelB, '2026-10-12', '2026-10-14'));
        $missing = $this->asUser($d['teacher'])->getJson('/api/v1/habits/recap?rombel_id=999999&from=2026-10-12&to=2026-10-14');

        $foreign->assertStatus(404);
        $missing->assertStatus(404);
        $this->assertSame($foreign->json(), $missing->json(), 'respons harus identik agar id rombel tidak dapat dienumerasi');
    }

    /** @test */
    public function rombel_lain_di_sekolah_yang_sama_ditolak_403(): void
    {
        $d           = $this->seedScenario();
        $otherRombel = $this->makeRombel($d['school'], $d['year'], 'VII-B');
        $teacherB    = $this->makeTeacher($d['school'], $d['year'], $otherRombel, '1002');

        // Teacher A meminta rombel B, Teacher B meminta rombel A.
        $this->asUser($d['teacher'])->getJson($this->recapUrl($otherRombel))->assertStatus(403);
        $this->asUser($teacherB)->getJson($this->recapUrl($d['rombel']))->assertStatus(403);

        // Teacher B hanya bisa merekap rombelnya sendiri (kosong).
        $this->asUser($teacherB)->getJson($this->recapUrl($otherRombel))->assertStatus(200)
            ->assertJsonPath('data.student_count', 0);
    }

    /** @test */
    public function teacher_tanpa_assignment_aktif_ditolak_403_untuk_rombel_sekolahnya_dan_404_untuk_sekolah_lain(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, null, '1001'); // belum di-assign ke rombel
        [, , $rombelB] = $this->makeSchool('SMP B');

        $this->asUser($teacher)->getJson($this->recapUrl($rombel))->assertStatus(403);
        $this->asUser($teacher)->getJson($this->recapUrl($rombelB))->assertStatus(404);
    }

    /** @test */
    public function teacher_inactive_tidak_punya_scope_403(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $teacher = $this->makeTeacher($school, $year, $rombel, '1001', 'inactive');

        $this->asUser($teacher)->getJson($this->recapUrl($rombel))->assertStatus(403);
    }

    // ---------------------------------------------------------------- read-only & performa (D-10)

    /** @test */
    public function rekap_hanya_membaca_tidak_mengubah_entri_maupun_poin_siswa(): void
    {
        $d = $this->seedScenario();
        $d['s1']->update(['poin' => 777]);

        $entriesBefore = StudentHabit::count();

        $this->asUser($d['teacher'])->getJson($this->recapUrl($d['rombel'], '2026-10-12', '2026-10-14'))->assertStatus(200);

        $this->assertSame($entriesBefore, StudentHabit::count());
        $this->assertSame(777, (int) $d['s1']->fresh()->poin);
        $this->assertSame(0, (int) $d['s2']->fresh()->poin);
    }

    /** @test */
    public function jumlah_query_tetap_tidak_bergantung_jumlah_siswa_dan_entri(): void
    {
        $d = $this->seedScenario();

        $countQueries = function () use ($d): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->asUser($d['teacher'])->getJson($this->recapUrl($d['rombel'], '2026-10-01', '2026-10-31'))->assertStatus(200);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $before = $countQueries();

        for ($i = 0; $i < 6; $i++) {
            $s = $this->makeStudent($d['school'], $d['year'], $d['rombel'], 'Tambahan ' . $i);
            $this->entry($s, 'gemar_belajar', 1 + ($i % 4), '2026-10-05', $i % 2 ? 'mandiri' : 'disuruh');
            $this->entry($s, 'berolahraga', 2, '2026-10-06', 'mandiri');
            $this->bermasyarakat($s, [1 + ($i % 3)], '2026-10-07', 'disuruh');
        }

        $this->assertSame($before, $countQueries(), 'jumlah query harus sama untuk 3 dan 9 siswa');
    }
}
