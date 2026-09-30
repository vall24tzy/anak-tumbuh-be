<?php

namespace Tests\Feature\Gamification;

use App\Models\StudentClassAssignment;
use App\Services\Gamification\LevelService;
use Database\Seeders\GamificationLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesGamificationFixtures;
use Tests\TestCase;

// LEVEL-RANKING-001 revisi 3 — API Contract v1.2 §24, Dokumen v4.0 §9, Matrix
// #39. KEPUTUSAN TIM: ranking Academic Year AKTIF saja, response
// {student_id,name,rombel,poin,level}, pagination §7.
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class RankingTest extends TestCase
{
    use RefreshDatabase, CreatesGamificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GamificationLevelSeeder::class);
    }

    protected function ids(array $rows): array
    {
        return array_map(fn ($r) => $r['student_id'], $rows);
    }

    protected function names(array $rows): array
    {
        return array_map(fn ($r) => $r['name'], $rows);
    }

    /** @test */
    public function response_ranking_punya_tepat_5_field_tanpa_rank_atau_nisn(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $this->makeStudent($school, $year, $rombel, 100);
        $teacher = $this->makeTeacher($school, $year, $rombel);

        $response = $this->withHeaders($this->authHeader($teacher))->getJson('/api/v1/rankings/rombel');

        $response->assertStatus(200);
        $row = $response->json('data.0');
        $this->assertEqualsCanonicalizing(['student_id', 'name', 'rombel', 'poin', 'level'], array_keys($row));
        $this->assertSame('VII-A', $row['rombel']);
        $this->assertSame(2, $row['level']); // poin 100 -> Level 2
    }

    /** @test */
    public function sorting_poin_desc_lalu_name_asc(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);

        // Dibuat sengaja acak (bukan urutan alfabet) supaya urutan id != urutan nama.
        $citra = $this->makeStudent($school, $year, $rombel, 500);
        $citra->update(['fullname' => 'Citra']);
        $ahmad = $this->makeStudent($school, $year, $rombel, 500);
        $ahmad->update(['fullname' => 'Ahmad']);
        $deni = $this->makeStudent($school, $year, $rombel, 450);
        $deni->update(['fullname' => 'Deni']);
        $budi = $this->makeStudent($school, $year, $rombel, 500);
        $budi->update(['fullname' => 'Budi']);

        $teacher = $this->makeTeacher($school, $year, $rombel);
        $response = $this->withHeaders($this->authHeader($teacher))->getJson('/api/v1/rankings/rombel');

        $this->assertSame(['Ahmad', 'Budi', 'Citra', 'Deni'], $this->names($response->json('data')));
    }

    /** @test */
    public function tie_break_poin_dan_nama_identik_urut_id_asc(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);

        $first = $this->makeStudent($school, $year, $rombel, 300);
        $first->update(['fullname' => 'Sama']);
        $second = $this->makeStudent($school, $year, $rombel, 300);
        $second->update(['fullname' => 'Sama']);

        $teacher = $this->makeTeacher($school, $year, $rombel);
        $response = $this->withHeaders($this->authHeader($teacher))->getJson('/api/v1/rankings/rombel');

        $this->assertSame([$first->id, $second->id], $this->ids($response->json('data')));
    }

    /** @test */
    public function pagination_meta_dan_default_per_page_20(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        for ($i = 0; $i < 3; $i++) {
            $this->makeStudent($school, $year, $rombel, 100 + $i);
        }
        $teacher = $this->makeTeacher($school, $year, $rombel);

        $response = $this->withHeaders($this->authHeader($teacher))->getJson('/api/v1/rankings/rombel');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertSame(1, $meta['page']);
        $this->assertSame(20, $meta['per_page']);
        $this->assertSame(3, $meta['total']);
        $this->assertSame(1, $meta['total_pages']);
    }

    /** @test */
    public function per_page_101_ditolak_422(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $teacher = $this->makeTeacher($school, $year, $rombel);

        $this->withHeaders($this->authHeader($teacher))
            ->getJson('/api/v1/rankings/rombel?per_page=101')
            ->assertStatus(422);
    }

    /** @test */
    public function halaman_2_konsisten_dengan_halaman_1_tanpa_tumpang_tindih(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $s = [];
        for ($i = 0; $i < 5; $i++) {
            $st = $this->makeStudent($school, $year, $rombel, 500 - $i * 10);
            $st->update(['fullname' => chr(65 + $i)]); // A, B, C, D, E
            $s[] = $st;
        }
        $teacher = $this->makeTeacher($school, $year, $rombel);
        $headers = $this->authHeader($teacher);

        $page1 = $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel?per_page=2&page=1');
        $page2 = $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel?per_page=2&page=2');
        $page3 = $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel?per_page=2&page=3');

        $all = array_merge($this->ids($page1->json('data')), $this->ids($page2->json('data')), $this->ids($page3->json('data')));
        $this->assertSame(array_map(fn ($x) => $x->id, $s), $all);
        $this->assertSame(3, $page1->json('meta.total_pages'));
    }

    /** @test */
    public function teacher_tanpa_active_assignment_200_kosong_dengan_meta(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $teacherUser = \App\Models\User::create(['role' => 'teacher', 'username' => '19000000000000', 'password' => 'secret']);
        \App\Models\Teacher::create([
            'user_id' => $teacherUser->id, 'school_id' => $school->id, 'fullname' => 'Guru Tanpa Rombel',
            'nip' => '19000000000000', 'gender' => 'laki-laki', 'status' => 'active',
        ]);
        // Sengaja TIDAK dibuat TeacherClassAssignment.

        $response = $this->withHeaders($this->authHeader($teacherUser))->getJson('/api/v1/rankings/rombel');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.total'));
    }

    /** @test */
    public function student_tanpa_active_assignment_200_kosong(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $student = $this->makeStudent($school, $year, $rombel, 100);
        StudentClassAssignment::where('student_id', $student->id)->update(['status' => 'ended']);

        $response = $this->withHeaders($this->authHeader($student->user))->getJson('/api/v1/rankings/rombel');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    /** @test */
    public function student_tanpa_academic_year_aktif_200_kosong(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $student = $this->makeStudent($school, $year, $rombel, 100);
        $year->update(['is_active' => false]);

        $response = $this->withHeaders($this->authHeader($student->user))->getJson('/api/v1/rankings/angkatan');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    /** @test */
    public function ranking_angkatan_grade_sama_lintas_rombel_satu_school_saja(): void
    {
        [$school, $year] = $this->makeSchool();
        $vii_a = $this->makeRombel($school, $year, 'VII-A', 7);
        $vii_b = $this->makeRombel($school, $year, 'VII-B', 7);
        $viii  = $this->makeRombel($school, $year, 'VIII-A', 8);

        $s1 = $this->makeStudent($school, $year, $vii_a, 100);
        $s1->update(['fullname' => 'B']);
        $s2 = $this->makeStudent($school, $year, $vii_b, 500);
        $s2->update(['fullname' => 'A']);
        $this->makeStudent($school, $year, $viii, 900);

        [$other, $otherYear] = $this->makeSchool('SD Lain');
        $otherRombel = $this->makeRombel($other, $otherYear, 'VII-A', 7);
        $this->makeStudent($other, $otherYear, $otherRombel, 800);

        $teacher = $this->makeTeacher($school, $year, $vii_a);
        $response = $this->withHeaders($this->authHeader($teacher))->getJson('/api/v1/rankings/angkatan');

        $response->assertStatus(200);
        $this->assertSame([$s2->id, $s1->id], $this->ids($response->json('data')));
    }

    /** @test */
    public function academic_year_id_sama_dengan_tahun_aktif_hasil_tidak_berubah(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $this->makeStudent($school, $year, $rombel, 100);
        $teacher = $this->makeTeacher($school, $year, $rombel);
        $headers = $this->authHeader($teacher);

        $tanpaParam = $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel');
        $denganParam = $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel?academic_year_id=' . $year->id);

        $denganParam->assertStatus(200);
        $this->assertSame($tanpaParam->json('data'), $denganParam->json('data'));
    }

    /** @test */
    public function academic_year_id_tahun_lampau_ditolak_403_teacher_dan_student(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $student = $this->makeStudent($school, $year, $rombel, 100);
        $teacher = $this->makeTeacher($school, $year, $rombel);

        $oldYear = \App\Models\AcademicYear::create([
            'school_id' => $school->id, 'name' => '2020/2021',
            'start_date' => '2020-07-01', 'end_date' => '2021-06-30', 'is_active' => false,
        ]);

        $this->withHeaders($this->authHeader($teacher))
            ->getJson('/api/v1/rankings/rombel?academic_year_id=' . $oldYear->id)->assertStatus(403);
        $this->withHeaders($this->authHeader($student->user))
            ->getJson('/api/v1/rankings/angkatan?academic_year_id=' . $oldYear->id)->assertStatus(403);
    }

    /** @test */
    public function academic_year_id_sekolah_lain_ditolak_403(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $teacher = $this->makeTeacher($school, $year, $rombel);
        [, $otherYear] = $this->makeSchool('SD Lain');

        $this->withHeaders($this->authHeader($teacher))
            ->getJson('/api/v1/rankings/rombel?academic_year_id=' . $otherYear->id)->assertStatus(403);
    }

    /** @test */
    public function sekolah_tanpa_academic_year_aktif_dan_param_dikirim_403(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $teacherUser = \App\Models\User::create(['role' => 'teacher', 'username' => '19100000000000', 'password' => 'secret']);
        \App\Models\Teacher::create([
            'user_id' => $teacherUser->id, 'school_id' => $school->id, 'fullname' => 'Guru',
            'nip' => '19100000000000', 'gender' => 'laki-laki', 'status' => 'active',
        ]);
        $year->update(['is_active' => false]); // sekolah jadi tanpa Academic Year aktif

        // Tanpa param -> 200 kosong (bukan 403).
        $this->withHeaders($this->authHeader($teacherUser))->getJson('/api/v1/rankings/rombel')->assertStatus(200);

        // Dengan param -> 403 (asumsi tim: tidak ada "tahun aktif" utk dicocokkan).
        $this->withHeaders($this->authHeader($teacherUser))
            ->getJson('/api/v1/rankings/rombel?academic_year_id=' . $year->id)->assertStatus(403);
    }

    /** @test */
    public function urutan_pengecekan_teacher_tanpa_assignment_plus_tahun_salah_tetap_403(): void
    {
        [$school, $year] = $this->makeSchool();
        $teacherUser = \App\Models\User::create(['role' => 'teacher', 'username' => '19200000000000', 'password' => 'secret']);
        \App\Models\Teacher::create([
            'user_id' => $teacherUser->id, 'school_id' => $school->id, 'fullname' => 'Guru Tanpa Rombel',
            'nip' => '19200000000000', 'gender' => 'laki-laki', 'status' => 'active',
        ]);
        $oldYear = \App\Models\AcademicYear::create([
            'school_id' => $school->id, 'name' => '2019/2020',
            'start_date' => '2019-07-01', 'end_date' => '2020-06-30', 'is_active' => false,
        ]);

        // Teacher TIDAK punya active assignment sama sekali, DAN academic_year_id
        // salah -> harus 403 (bukan 200 kosong yg diputuskan lebih dulu).
        $this->withHeaders($this->authHeader($teacherUser))
            ->getJson('/api/v1/rankings/rombel?academic_year_id=' . $oldYear->id)->assertStatus(403);
    }

    /** @test */
    public function caller_tanpa_konteks_kelas_plus_rombel_id_apa_pun_403(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $teacherUser = \App\Models\User::create(['role' => 'teacher', 'username' => '19300000000000', 'password' => 'secret']);
        \App\Models\Teacher::create([
            'user_id' => $teacherUser->id, 'school_id' => $school->id, 'fullname' => 'Guru Tanpa Rombel',
            'nip' => '19300000000000', 'gender' => 'laki-laki', 'status' => 'active',
        ]);

        $this->withHeaders($this->authHeader($teacherUser))
            ->getJson('/api/v1/rankings/rombel?rombel_id=' . $rombel->id)->assertStatus(403);
    }

    /** @test */
    public function headmaster_dan_super_admin_tidak_punya_ranking_403(): void
    {
        [$school] = $this->makeSchool();

        foreach ([$this->makeHeadmaster($school), $this->makeSuperAdmin()] as $user) {
            $this->withHeaders($this->authHeader($user))->getJson('/api/v1/rankings/rombel')->assertStatus(403);
            $this->withHeaders($this->authHeader($user))->getJson('/api/v1/rankings/angkatan')->assertStatus(403);
        }
    }

    /** @test */
    public function rombel_id_selain_milik_sendiri_403_dan_milik_sendiri_hasil_sama(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombelA = $this->makeRombel($school, $year, 'VII-A', 7);
        $rombelB = $this->makeRombel($school, $year, 'VII-B', 7);
        $this->makeStudent($school, $year, $rombelA, 100);
        $teacher = $this->makeTeacher($school, $year, $rombelA);
        $headers = $this->authHeader($teacher);

        $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel?rombel_id=' . $rombelB->id)->assertStatus(403);

        $tanpaParam = $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel');
        $denganParam = $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel?rombel_id=' . $rombelA->id);
        $this->assertSame($tanpaParam->json('data'), $denganParam->json('data'));
    }

    /** @test */
    public function cross_school_tidak_bocor(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $this->makeStudent($school, $year, $rombel, 999999);

        [$other, $otherYear] = $this->makeSchool('SD Lain');
        $otherRombel = $this->makeRombel($other, $otherYear, 'VII-A', 7);
        $otherTeacher = $this->makeTeacher($other, $otherYear, $otherRombel);

        $response = $this->withHeaders($this->authHeader($otherTeacher))->getJson('/api/v1/rankings/angkatan');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    /** @test */
    public function level_sesuai_level_service_termasuk_batas_threshold(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        $points = [49 => 0, 50 => 1, 99 => 1, 100 => 2, 750592 => 100];
        $byId = [];

        foreach ($points as $poin => $expectedLevel) {
            $s = $this->makeStudent($school, $year, $rombel, $poin);
            $byId[$s->id] = $expectedLevel;
        }

        $teacher = $this->makeTeacher($school, $year, $rombel);
        $response = $this->withHeaders($this->authHeader($teacher))
            ->getJson('/api/v1/rankings/rombel?per_page=100');

        foreach ($response->json('data') as $row) {
            $this->assertSame($byId[$row['student_id']], $row['level'], "student {$row['student_id']}");
        }
    }

    /** @test */
    public function gamification_levels_hanya_diquery_1x_per_request_bukan_per_baris(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombel = $this->makeRombel($school, $year, 'VII-A', 7);
        for ($i = 0; $i < 5; $i++) {
            $this->makeStudent($school, $year, $rombel, 100 + $i);
        }
        $teacher = $this->makeTeacher($school, $year, $rombel);

        $count = 0;
        DB::listen(function ($query) use (&$count) {
            if (str_contains($query->sql, 'gamification_levels')) {
                $count++;
            }
        });

        $this->withHeaders($this->authHeader($teacher))->getJson('/api/v1/rankings/rombel')->assertStatus(200);

        $this->assertSame(1, $count);
    }

    /** @test */
    public function query_student_id_sort_order_tidak_mengubah_hasil(): void
    {
        [$school, $year] = $this->makeSchool();
        $rombelA = $this->makeRombel($school, $year, 'VII-A', 7);
        $rombelB = $this->makeRombel($school, $year, 'VII-B', 7);
        $mine   = $this->makeStudent($school, $year, $rombelA, 10);
        $theirs = $this->makeStudent($school, $year, $rombelB, 20);
        $teacher = $this->makeTeacher($school, $year, $rombelA);
        $headers = $this->authHeader($teacher);

        $baseline = $this->withHeaders($headers)->getJson('/api/v1/rankings/rombel');
        $withExtra = $this->withHeaders($headers)
            ->getJson('/api/v1/rankings/rombel?student_id=' . $theirs->id . '&sort=poin&order=asc');

        $withExtra->assertStatus(200);
        $this->assertSame($baseline->json('data'), $withExtra->json('data'));
        $this->assertSame([$mine->id], $this->ids($withExtra->json('data')));
    }
}
