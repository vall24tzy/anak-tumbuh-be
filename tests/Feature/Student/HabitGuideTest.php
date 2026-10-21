<?php

namespace Tests\Feature\Student;

use App\Models\AcademicYear;
use App\Models\Habit;
use App\Models\HabitGuide;
use App\Models\Headmaster;
use App\Models\Rombel;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClassAssignment;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Database\Seeders\HabitIndicatorSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// INTEG-PANDUAN-001 — Matrix #63 (tulis) dan #64 (lihat), D-06, D-13, amandemen Dokumen v4.0 20 Okt 2026 (a).
// GET /rombels/{rombel_id}/habit-guides (Teacher, Student) dan
// PATCH /rombels/{rombel_id}/habit-guides/{habit_id} (Teacher). Teks polos maks 2.000 karakter;
// content kosong/null menghapus panduan. Habit memakai seeder produksi (7 habit).

class HabitGuideTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HabitIndicatorSeeder::class);
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

        return [$school, $year, $this->makeRombel($school, $year, $rombelName)];
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

    protected function makeStudent(School $school, AcademicYear $year, ?Rombel $rombel): Student
    {
        $nisn = '91' . str_pad((string) (++$this->seq), 8, '0', STR_PAD_LEFT);

        $user = User::create(['role' => 'student', 'username' => $nisn, 'password' => $nisn]);

        $student = Student::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Siswa ' . $nisn,
            'nisn'      => $nisn,
            'gender'    => 'laki-laki',
            'poin'      => 0,
            'status'    => 'active',
        ]);

        if ($rombel) {
            StudentClassAssignment::create([
                'student_id'       => $student->id,
                'school_id'        => $school->id,
                'rombel_id'        => $rombel->id,
                'academic_year_id' => $year->id,
                'status'           => 'active',
            ]);
        }

        return $student;
    }

    protected function makeHeadmaster(School $school, string $nip): User
    {
        $user = User::create(['role' => 'headmaster', 'username' => $nip, 'password' => 'secret']);

        Headmaster::create([
            'user_id'   => $user->id,
            'school_id' => $school->id,
            'fullname'  => 'Kepala ' . $nip,
            'nip'       => $nip,
            'gender'    => 'laki-laki',
            'status'    => 'active',
        ]);

        return $user;
    }

    protected function asUser(User $user): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . auth('api')->login($user));
    }

    protected function habitId(string $code): int
    {
        return Habit::where('code', $code)->value('id');
    }

    protected function guideUrl(Rombel|int $rombel, ?int $habitId = null): string
    {
        $id  = $rombel instanceof Rombel ? $rombel->id : $rombel;
        $url = "/api/v1/rombels/{$id}/habit-guides";

        return $habitId === null ? $url : "{$url}/{$habitId}";
    }

    /** Sekolah A: rombel VII-A (+ guru A, siswa A) dan VII-B (+ guru B, siswa B). */
    protected function scenario(): array
    {
        [$school, $year, $rombelA] = $this->makeSchool('SMP A');
        $rombelB  = $this->makeRombel($school, $year, 'VII-B');
        $teacherA = $this->makeTeacher($school, $year, $rombelA, '1001');
        $teacherB = $this->makeTeacher($school, $year, $rombelB, '1002');
        $studentA = $this->makeStudent($school, $year, $rombelA);
        $studentB = $this->makeStudent($school, $year, $rombelB);

        return compact('school', 'year', 'rombelA', 'rombelB', 'teacherA', 'teacherB', 'studentA', 'studentB');
    }

    // ---------------------------------------------------------------- tulis dan baca

    /** @test */
    public function teacher_menulis_panduan_dan_teacher_serta_student_rombel_membacanya(): void
    {
        $d      = $this->scenario();
        $habit  = $this->habitId('bangun_cepat');
        $habit2 = $this->habitId('beribadah');

        // Belum ada panduan -> daftar kosong.
        $this->asUser($d['teacherA'])->getJson($this->guideUrl($d['rombelA']))
            ->assertStatus(200)->assertExactJson(['data' => []]);

        // Tulis dua panduan (urutan tulis dibalik dari urutan habit).
        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], $habit2), ['content' => 'Panduan ibadah'])
            ->assertStatus(200)
            ->assertExactJson(['data' => ['habit_id' => $habit2, 'content' => 'Panduan ibadah']]);
        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'Bangun sebelum subuh'])
            ->assertStatus(200);

        $expected = ['data' => [
            ['habit_id' => $habit, 'content' => 'Bangun sebelum subuh'],   // sort_order 1
            ['habit_id' => $habit2, 'content' => 'Panduan ibadah'],        // sort_order 2
        ]];

        $this->asUser($d['teacherA'])->getJson($this->guideUrl($d['rombelA']))->assertStatus(200)->assertExactJson($expected);
        $this->asUser($d['studentA']->user)->getJson($this->guideUrl($d['rombelA']))->assertStatus(200)->assertExactJson($expected);
    }

    /** @test */
    public function patch_kedua_menimpa_isi_tanpa_membuat_baris_baru(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');

        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'Versi 1'])->assertStatus(200);
        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'Versi 2'])->assertStatus(200);

        $this->assertSame(1, HabitGuide::count());
        $this->assertSame('Versi 2', HabitGuide::first()->content);
    }

    /** @test */
    public function content_kosong_null_atau_spasi_menghapus_panduan_dan_get_sesudahnya_kosong(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');
        $url   = $this->guideUrl($d['rombelA'], $habit);

        foreach ([[''], [null], ['   ']] as [$empty]) {
            $this->asUser($d['teacherA'])->patchJson($url, ['content' => 'Ada panduan'])->assertStatus(200);
            $this->assertSame(1, HabitGuide::count());

            // D-13: respons 200 dengan content null; baris benar-benar terhapus.
            $this->asUser($d['teacherA'])->patchJson($url, ['content' => $empty])
                ->assertStatus(200)
                ->assertExactJson(['data' => ['habit_id' => $habit, 'content' => null]]);
            $this->assertSame(0, HabitGuide::count());

            // GET sesudahnya sama dengan "belum ada panduan", untuk Teacher maupun Student.
            $this->asUser($d['teacherA'])->getJson($this->guideUrl($d['rombelA']))->assertExactJson(['data' => []]);
            $this->asUser($d['studentA']->user)->getJson($this->guideUrl($d['rombelA']))->assertExactJson(['data' => []]);
        }
    }

    /** @test */
    public function menghapus_panduan_yang_tidak_ada_tetap_200_idempoten(): void
    {
        $d = $this->scenario();

        $this->asUser($d['teacherA'])
            ->patchJson($this->guideUrl($d['rombelA'], $this->habitId('tidur_cepat')), ['content' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.content', null);
    }

    /** @test */
    public function menghapus_satu_panduan_tidak_menghapus_panduan_habit_lain(): void
    {
        $d      = $this->scenario();
        $habit  = $this->habitId('bangun_cepat');
        $habit2 = $this->habitId('beribadah');

        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'A'])->assertStatus(200);
        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], $habit2), ['content' => 'B'])->assertStatus(200);
        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => null])->assertStatus(200);

        $this->asUser($d['teacherA'])->getJson($this->guideUrl($d['rombelA']))
            ->assertExactJson(['data' => [['habit_id' => $habit2, 'content' => 'B']]]);
    }

    // ---------------------------------------------------------------- validasi

    /** @test */
    public function content_tanpa_key_ditolak_422_dan_tidak_menghapus_panduan(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');
        $url   = $this->guideUrl($d['rombelA'], $habit);

        $this->asUser($d['teacherA'])->patchJson($url, ['content' => 'Tetap ada'])->assertStatus(200);

        $this->asUser($d['teacherA'])->patchJson($url, [])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        $this->asUser($d['teacherA'])->patchJson($url, ['isi' => 'salah nama field'])
            ->assertStatus(422);

        $this->assertSame('Tetap ada', HabitGuide::first()->content);
    }

    /** @test */
    public function batas_2000_karakter_tepat_2000_diterima_2001_ditolak_422_termasuk_multibyte(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');
        $url   = $this->guideUrl($d['rombelA'], $habit);

        $this->asUser($d['teacherA'])->patchJson($url, ['content' => str_repeat('a', 2000)])->assertStatus(200);
        $this->asUser($d['teacherA'])->patchJson($url, ['content' => str_repeat('é', 2000)])->assertStatus(200);
        $this->assertSame(str_repeat('é', 2000), HabitGuide::first()->content);

        $this->asUser($d['teacherA'])->patchJson($url, ['content' => str_repeat('a', 2001)])
            ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');

        // Penolakan tidak menimpa panduan yang sudah ada.
        $this->assertSame(str_repeat('é', 2000), HabitGuide::first()->content);
    }

    /** @test */
    public function tag_html_ditolak_422_sedangkan_teks_polos_apa_adanya_diterima(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');
        $url   = $this->guideUrl($d['rombelA'], $habit);

        foreach (['<b>tebal</b>', 'halo <script>alert(1)</script>', '<img src=x onerror=alert(1)>', '</p>', '<!-- komentar -->'] as $html) {
            $this->asUser($d['teacherA'])->patchJson($url, ['content' => $html])
                ->assertStatus(422)->assertJsonPath('error.code', 'validation_failed');
        }
        $this->assertSame(0, HabitGuide::count());

        // Bukan tag HTML: tanda < > biasa, markdown, dan baris baru disimpan apa adanya.
        foreach (['Saya <3 belajar', 'a < b dan c > d', "**tebal** dan _miring_\nbaris dua", 'Tulis "doa" & amalkan'] as $plain) {
            $this->asUser($d['teacherA'])->patchJson($url, ['content' => $plain])
                ->assertStatus(200)->assertJsonPath('data.content', $plain);
            $this->asUser($d['studentA']->user)->getJson($this->guideUrl($d['rombelA']))
                ->assertJsonPath('data.0.content', $plain);
        }
    }

    /** @test */
    public function habit_id_tidak_dikenal_404_dan_rombel_id_atau_habit_id_non_numerik_404(): void
    {
        $d = $this->scenario();

        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], 9999), ['content' => 'x'])
            ->assertStatus(404);
        $this->asUser($d['teacherA'])->patchJson("/api/v1/rombels/{$d['rombelA']->id}/habit-guides/abc", ['content' => 'x'])
            ->assertStatus(404);
        $this->asUser($d['teacherA'])->getJson('/api/v1/rombels/abc/habit-guides')
            ->assertStatus(404);
        $this->assertSame(0, HabitGuide::count());
    }

    // ---------------------------------------------------------------- otorisasi & scope

    /** @test */
    public function tanpa_token_401_dan_role_tidak_berhak_403(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');

        $this->getJson($this->guideUrl($d['rombelA']))->assertStatus(401);
        $this->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'x'])->assertStatus(401);

        $headmaster = $this->makeHeadmaster($d['school'], '2001');
        $superAdmin = User::create(['role' => 'super_admin', 'username' => 'sa-guide', 'password' => 'secret']);

        // Headmaster dan Super Admin: tidak boleh baca maupun tulis (Matrix #63/#64).
        foreach ([$headmaster, $superAdmin] as $user) {
            $this->asUser($user)->getJson($this->guideUrl($d['rombelA']))->assertStatus(403);
            $this->asUser($user)->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'x'])->assertStatus(403);
        }

        // Student: hanya baca, tidak boleh tulis.
        $this->asUser($d['studentA']->user)->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'x'])
            ->assertStatus(403);

        $this->assertSame(0, HabitGuide::count());
    }

    /** @test */
    public function teacher_lintas_rombel_di_sekolah_sama_403_untuk_baca_dan_tulis(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');

        $this->asUser($d['teacherA'])->getJson($this->guideUrl($d['rombelB']))->assertStatus(403);
        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelB'], $habit), ['content' => 'x'])->assertStatus(403);
        $this->asUser($d['teacherB'])->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'x'])->assertStatus(403);

        $this->assertSame(0, HabitGuide::count());
    }

    /** @test */
    public function student_hanya_membaca_rombel_sendiri_lintas_rombel_403(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');

        $this->asUser($d['teacherB'])->patchJson($this->guideUrl($d['rombelB'], $habit), ['content' => 'Khusus rombel B'])
            ->assertStatus(200);

        $this->asUser($d['studentA']->user)->getJson($this->guideUrl($d['rombelB']))->assertStatus(403);
        $this->asUser($d['studentB']->user)->getJson($this->guideUrl($d['rombelB']))
            ->assertStatus(200)->assertJsonPath('data.0.content', 'Khusus rombel B');
    }

    /** @test */
    public function panduan_rombel_lain_tidak_bocor_ke_daftar_rombel_sendiri(): void
    {
        $d     = $this->scenario();
        $habit = $this->habitId('bangun_cepat');

        $this->asUser($d['teacherA'])->patchJson($this->guideUrl($d['rombelA'], $habit), ['content' => 'Milik A'])->assertStatus(200);
        $this->asUser($d['teacherB'])->patchJson($this->guideUrl($d['rombelB'], $habit), ['content' => 'Milik B'])->assertStatus(200);

        $this->asUser($d['teacherA'])->getJson($this->guideUrl($d['rombelA']))
            ->assertExactJson(['data' => [['habit_id' => $habit, 'content' => 'Milik A']]]);
        $this->asUser($d['studentB']->user)->getJson($this->guideUrl($d['rombelB']))
            ->assertExactJson(['data' => [['habit_id' => $habit, 'content' => 'Milik B']]]);
        $this->assertSame(2, HabitGuide::count());
    }

    /** @test */
    public function rombel_sekolah_lain_dan_rombel_tidak_ada_menghasilkan_404_identik_untuk_teacher_dan_student(): void
    {
        $d = $this->scenario();
        [, , $rombelC] = $this->makeSchool('SMP C');
        $habit = $this->habitId('bangun_cepat');

        foreach ([$d['teacherA'], $d['studentA']->user] as $user) {
            $foreign = $this->asUser($user)->getJson($this->guideUrl($rombelC));
            $missing = $this->asUser($user)->getJson($this->guideUrl(999999));

            $foreign->assertStatus(404);
            $missing->assertStatus(404);
            $this->assertSame($foreign->json(), $missing->json(), 'respons harus identik agar id rombel tidak dapat dienumerasi');
        }

        $foreignWrite = $this->asUser($d['teacherA'])->patchJson($this->guideUrl($rombelC, $habit), ['content' => 'x']);
        $missingWrite = $this->asUser($d['teacherA'])->patchJson($this->guideUrl(999999, $habit), ['content' => 'x']);
        $foreignWrite->assertStatus(404);
        $missingWrite->assertStatus(404);
        $this->assertSame($foreignWrite->json(), $missingWrite->json());
        $this->assertSame(0, HabitGuide::count());
    }

    /** @test */
    public function teacher_inactive_teacher_tanpa_assignment_dan_student_tanpa_rombel_aktif_403(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $habit = $this->habitId('bangun_cepat');

        $inactive     = $this->makeTeacher($school, $year, $rombel, '1001', 'inactive');
        $noAssignment = $this->makeTeacher($school, $year, null, '1002');
        $noRombel     = $this->makeStudent($school, $year, null);

        foreach ([$inactive, $noAssignment, $noRombel->user] as $user) {
            $this->asUser($user)->getJson($this->guideUrl($rombel))->assertStatus(403);
        }
        foreach ([$inactive, $noAssignment] as $user) {
            $this->asUser($user)->patchJson($this->guideUrl($rombel, $habit), ['content' => 'x'])->assertStatus(403);
        }
        $this->assertSame(0, HabitGuide::count());
    }

    // ---------------------------------------------------------------- integritas data

    /** @test */
    public function rombel_yang_punya_panduan_tidak_bisa_dihapus_restrict(): void
    {
        [$school, $year, $rombel] = $this->makeSchool('SMP A');
        $headmaster = $this->makeHeadmaster($school, '2001');
        $spare      = $this->makeRombel($school, $year, 'VII-C');
        $habit      = $this->habitId('bangun_cepat');

        // Kontrol: rombel kosong tanpa panduan dan tanpa assignment bisa dihapus.
        $this->asUser($headmaster)->deleteJson("/api/v1/rombels/{$spare->id}")->assertSuccessful();

        // Rombel tanpa assignment tetapi punya panduan: FK RESTRICT menolak penghapusan.
        $guarded = $this->makeRombel($school, $year, 'VII-D');
        HabitGuide::create(['rombel_id' => $guarded->id, 'habit_id' => $habit, 'content' => 'Panduan']);

        // Savepoint: error FK membatalkan transaksi test PostgreSQL; rollback ke savepoint memulihkannya.
        DB::beginTransaction();
        $this->asUser($headmaster)->deleteJson("/api/v1/rombels/{$guarded->id}")->assertStatus(409);
        DB::rollBack();

        $this->assertDatabaseHas('rombels', ['id' => $guarded->id]);
        $this->assertSame(1, HabitGuide::where('rombel_id', $guarded->id)->count());
    }

    /** @test */
    public function constraint_db_menolak_baris_kosong_dan_lebih_dari_2000_karakter_serta_duplikat(): void
    {
        [, , $rombel] = $this->makeSchool('SMP A');
        $habit = $this->habitId('bangun_cepat');

        foreach (['', str_repeat('a', 2001)] as $bad) {
            DB::beginTransaction(); // savepoint: error constraint tidak boleh membatalkan transaksi test
            try {
                HabitGuide::create(['rombel_id' => $rombel->id, 'habit_id' => $habit, 'content' => $bad]);
                $this->fail('CHECK constraint seharusnya menolak content dengan panjang ' . mb_strlen($bad));
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->getCode());
            } finally {
                DB::rollBack();
            }
        }

        HabitGuide::create(['rombel_id' => $rombel->id, 'habit_id' => $habit, 'content' => 'ok']);

        $duplicateRejected = false;
        DB::beginTransaction();
        try {
            HabitGuide::create(['rombel_id' => $rombel->id, 'habit_id' => $habit, 'content' => 'duplikat']);
        } catch (UniqueConstraintViolationException) {
            $duplicateRejected = true;
        } finally {
            DB::rollBack();
        }

        $this->assertTrue($duplicateRejected, 'UNIQUE(rombel_id, habit_id) seharusnya menolak baris kedua');
        $this->assertSame(1, HabitGuide::where('rombel_id', $rombel->id)->count());
    }
}
