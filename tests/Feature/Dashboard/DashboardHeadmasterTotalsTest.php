<?php

namespace Tests\Feature\Dashboard;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\StudentClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// INTEG-DASHHM-001 - GET /dashboard/headmaster memuat tiga total baru: total_siswa, total_guru,
// total_rombel. SoT: Dokumen Keputusan v4.0 (amandemen D-01), Matrix v5.3 #68, API Contract §25 /
// perluasan OD-11, Task Board INTEG v1.5. Additive: `rombels` (id, name, grade_level, student_count,
// average_submitted_today) tidak berubah.
//
// Definisi yang dikunci:
//   - total_siswa  = jumlah student_count seluruh rombel pada response yang sama (assignment aktif,
//                    rombel tahun ajaran aktif); siswa inactive/graduated (assignment ended) tidak dihitung
//   - total_guru   = Teacher status 'active' di sekolah Headmaster (tidak terikat tahun ajaran)
//   - total_rombel = rombel pada tahun ajaran aktif
//   - tanpa tahun ajaran aktif -> total_siswa 0 dan total_rombel 0, tanpa error
//   - sekolah lain tidak terlihat; jumlah query tidak naik seiring jumlah rombel (tanpa N+1)
//
// STATUS EKSEKUSI: ditulis tanpa menjalankan PHP; jalankan php artisan test lalu laporkan hasilnya.

class DashboardHeadmasterTotalsTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const URI = '/api/v1/dashboard/headmaster';

    private int $nisnSeq = 0;

    protected function nextNisn(): string
    {
        return sprintf('00511%05d', ++$this->nisnSeq);
    }

    /** Tambah N siswa aktif ke rombel. */
    protected function addStudents(School $school, $rombel, int $n): array
    {
        $students = [];

        for ($i = 0; $i < $n; $i++) {
            $student = $this->fxStudent($school, $this->nextNisn())->student;
            $this->fxStudentAssignment($student, $rombel);
            $students[] = $student;
        }

        return $students;
    }

    // ---------- nilai total ----------

    /** @test */
    public function total_siswa_guru_dan_rombel_benar_dan_total_siswa_sama_dengan_jumlah_student_count(): void
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombelA = $this->fxRombel($school, $year, 'VII-A');
        $rombelB = $this->fxRombel($school, $year, 'VII-B');
        $this->fxRombel($school, $year, 'VII-C'); // rombel kosong tetap dihitung di total_rombel

        $this->addStudents($school, $rombelA, 2);
        $this->addStudents($school, $rombelB, 3);

        $this->fxTeacher($school, '2001');
        $this->fxTeacher($school, '2002');

        $hm = $this->fxHeadmaster($school, '1001');

        $data = $this->apiGet(self::URI, $this->fxToken($hm))->assertStatus(200)->json('data');

        $this->assertSame(5, $data['total_siswa']);
        $this->assertSame(2, $data['total_guru']);
        $this->assertSame(3, $data['total_rombel']);

        $this->assertSame(
            collect($data['rombels'])->sum('student_count'),
            $data['total_siswa'],
            'total_siswa harus sama dengan jumlah student_count pada dashboard yang sama'
        );
        $this->assertCount($data['total_rombel'], $data['rombels']);
    }

    /** @test */
    public function guru_inactive_tidak_dihitung(): void
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $this->fxRombel($school, $year, 'VII-A');

        $this->fxTeacher($school, '2001');
        $inactive = $this->fxTeacher($school, '2002');
        $inactive->teacher->update(['status' => 'inactive']);

        $hm = $this->fxHeadmaster($school, '1001');

        $this->apiGet(self::URI, $this->fxToken($hm))
            ->assertStatus(200)
            ->assertJsonPath('data.total_guru', 1);
    }

    /** @test */
    public function siswa_inactive_dan_graduated_tidak_dihitung_di_total_siswa(): void
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');

        [$aktif, $inactive, $lulus] = $this->addStudents($school, $rombel, 3);

        // Transisi lifecycle: assignment ditutup (ended) dan status Student berubah.
        foreach ([[$inactive, 'inactive'], [$lulus, 'graduated']] as [$student, $status]) {
            $student->update(['status' => $status]);
            StudentClassAssignment::where('student_id', $student->id)->update(['status' => 'ended']);
        }

        $hm = $this->fxHeadmaster($school, '1001');

        $data = $this->apiGet(self::URI, $this->fxToken($hm))->assertStatus(200)->json('data');

        $this->assertSame(1, $data['total_siswa']);
        $this->assertSame(1, $data['rombels'][0]['student_count']);
    }

    /** @test */
    public function rombel_dan_siswa_tahun_ajaran_tidak_aktif_tidak_dihitung(): void
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');
        $this->addStudents($school, $rombel, 2);

        $oldYear = AcademicYear::create([
            'school_id' => $school->id, 'name' => '2025/2026',
            'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => false,
        ]);
        $rombelOld = $this->fxRombel($school, $oldYear, 'VI-A');
        $this->addStudents($school, $rombelOld, 4); // assignment aktif di tahun yang tidak active

        $hm = $this->fxHeadmaster($school, '1001');

        $data = $this->apiGet(self::URI, $this->fxToken($hm))->assertStatus(200)->json('data');

        $this->assertSame(2, $data['total_siswa']);
        $this->assertSame(1, $data['total_rombel']);
    }

    // ---------- tanpa tahun ajaran aktif / tanpa rombel ----------

    /** @test */
    public function tanpa_tahun_ajaran_aktif_total_siswa_dan_rombel_nol_tanpa_error(): void
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');
        $this->addStudents($school, $rombel, 2);
        $year->update(['is_active' => false]);

        $this->fxTeacher($school, '2001');
        $hm = $this->fxHeadmaster($school, '1001');

        $data = $this->apiGet(self::URI, $this->fxToken($hm))->assertStatus(200)->json('data');

        $this->assertSame([], $data['rombels']);
        $this->assertSame(0, $data['total_siswa']);
        $this->assertSame(0, $data['total_rombel']);
        // Guru tidak terikat tahun ajaran; keputusan definisi dicatat di addendum.
        $this->assertSame(1, $data['total_guru']);
    }

    /** @test */
    public function tahun_ajaran_aktif_tanpa_rombel_semua_total_siswa_dan_rombel_nol(): void
    {
        $school = $this->fxSchool('SD Satu');
        $this->fxAcademicYear($school);
        $this->fxTeacher($school, '2001');
        $this->fxTeacher($school, '2002');
        $this->fxTeacher($school, '2003')->teacher->update(['status' => 'inactive']);
        $hm = $this->fxHeadmaster($school, '1001');

        $data = $this->apiGet(self::URI, $this->fxToken($hm))->assertStatus(200)->json('data');

        $this->assertSame([], $data['rombels']);
        $this->assertSame(0, $data['total_siswa']);
        $this->assertSame(0, $data['total_rombel']);
        // Guru aktif tetap dihitung walau tidak ada rombel (konfirmasi pemilik 21 Okt 2026).
        $this->assertSame(2, $data['total_guru']);
    }

    // ---------- bentuk response: field lama tidak berubah ----------

    /** @test */
    public function field_lama_tetap_dan_tiga_total_ditambahkan_di_akhir(): void
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');
        $this->addStudents($school, $rombel, 1);
        $hm = $this->fxHeadmaster($school, '1001');

        $data = $this->apiGet(self::URI, $this->fxToken($hm))->assertStatus(200)->json('data');

        $this->assertSame(['rombels', 'total_siswa', 'total_guru', 'total_rombel'], array_keys($data));
        $this->assertSame(
            ['id', 'name', 'grade_level', 'student_count', 'average_submitted_today'],
            array_keys($data['rombels'][0])
        );
        $this->assertArrayNotHasKey('school', $data); // nama sekolah tetap dari /auth/me
        $this->assertArrayNotHasKey('academic_year', $data); // tahun ajaran dari /academic-years
    }

    // ---------- scope sekolah ----------

    /** @test */
    public function headmaster_hanya_melihat_total_sekolah_sendiri(): void
    {
        $schoolA = $this->fxSchool('SD Satu');
        $yearA = $this->fxAcademicYear($schoolA);
        $rombelA = $this->fxRombel($schoolA, $yearA, 'VII-A');
        $this->addStudents($schoolA, $rombelA, 2);
        $this->fxTeacher($schoolA, '2001');

        $schoolB = $this->fxSchool('SD Dua');
        $yearB = $this->fxAcademicYear($schoolB);
        $rombelB1 = $this->fxRombel($schoolB, $yearB, 'VII-A');
        $rombelB2 = $this->fxRombel($schoolB, $yearB, 'VII-B');
        $this->addStudents($schoolB, $rombelB1, 3);
        $this->addStudents($schoolB, $rombelB2, 4);
        $this->fxTeacher($schoolB, '2002');
        $this->fxTeacher($schoolB, '2003');
        $this->fxTeacher($schoolB, '2004');

        $hmA = $this->fxHeadmaster($schoolA, '1001');
        $hmB = $this->fxHeadmaster($schoolB, '1002');

        $a = $this->apiGet(self::URI, $this->fxToken($hmA))->assertStatus(200)->json('data');
        $b = $this->apiGet(self::URI, $this->fxToken($hmB))->assertStatus(200)->json('data');

        $this->assertSame([2, 1, 1], [$a['total_siswa'], $a['total_guru'], $a['total_rombel']]);
        $this->assertSame([7, 3, 2], [$b['total_siswa'], $b['total_guru'], $b['total_rombel']]);
    }

    // ---------- otorisasi ----------

    /** @test */
    public function role_lain_ditolak_403_dan_tanpa_token_401(): void
    {
        $school = $this->fxSchool('SD Satu');
        $year = $this->fxAcademicYear($school);
        $rombel = $this->fxRombel($school, $year, 'VII-A');
        $teacher = $this->fxTeacher($school, '2001');
        $this->fxTeacherAssignment($teacher->teacher, $rombel);
        $student = $this->fxStudent($school, $this->nextNisn());
        $sa = $this->fxSuperAdmin('sa_dashhm');

        foreach ([$teacher, $student, $sa] as $user) {
            $this->apiGet(self::URI, $this->fxToken($user))->assertStatus(403);
        }

        $this->apiGet(self::URI)->assertStatus(401);
    }

    // ---------- tanpa N+1 ----------

    /** @test */
    public function jumlah_query_tidak_naik_seiring_jumlah_rombel_dan_siswa(): void
    {
        $small = $this->fxSchool('SD Kecil');
        $yearS = $this->fxAcademicYear($small);
        $this->addStudents($small, $this->fxRombel($small, $yearS, 'VII-A'), 1);
        $hmSmall = $this->fxHeadmaster($small, '1001');

        $big = $this->fxSchool('SD Besar');
        $yearB = $this->fxAcademicYear($big);
        foreach (['VII-A', 'VII-B', 'VII-C', 'VII-D', 'VII-E'] as $name) {
            $this->addStudents($big, $this->fxRombel($big, $yearB, $name), 3);
        }
        $this->fxTeacher($big, '2001');
        $hmBig = $this->fxHeadmaster($big, '1002');

        $tokenSmall = $this->fxToken($hmSmall);
        $tokenBig = $this->fxToken($hmBig);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->apiGet(self::URI, $tokenSmall)->assertStatus(200);
        $querySmall = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->apiGet(self::URI, $tokenBig)->assertStatus(200);
        $queryBig = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($querySmall, $queryBig, '1 rombel/1 siswa vs 5 rombel/15 siswa harus memakai jumlah query yang sama');
    }
}
