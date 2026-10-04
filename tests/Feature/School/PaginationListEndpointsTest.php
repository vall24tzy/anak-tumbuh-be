<?php

namespace Tests\Feature\School;

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-15 + F-10: konvensi pagination API Contract §7 di 6 endpoint GET list (schools, headmasters, teachers,
// academic-years, rombels, teacher-class-assignments). Keputusan Matrix v3.1: page default 1, per_page
// default 20 maks 100, meta {page, per_page, total, total_pages}, urutan deterministik (id), halaman di
// luar rentang -> data kosong dengan meta valid. per_page > 100 -> 422 validation_failed (pola yang sama
// dengan ranking dan habit; Matrix hanya meminta perilaku >100 seragam dan terdokumentasi).
//
// Tiap skenario membuat 25 data dalam scope, plus data di sekolah lain untuk membuktikan `total` dihitung
// SETELAH scope (bukan jumlah global). Dijalankan lewat data provider: satu test per endpoint per perilaku.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat laporan patch.

class PaginationListEndpointsTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    public static function endpoints(): array
    {
        return [
            'schools'                   => ['schools'],
            'headmasters'               => ['headmasters'],
            'teachers'                  => ['teachers'],
            'academic-years'            => ['academic-years'],
            'rombels'                   => ['rombels'],
            'teacher-class-assignments' => ['teacher-class-assignments'],
        ];
    }

    /** @return array{0:string,1:string} [uri, token]; selalu 25 data dalam scope. */
    protected function scenario(string $endpoint): array
    {
        $uri = "/api/v1/{$endpoint}";

        if (in_array($endpoint, ['schools', 'headmasters'], true)) {
            $token = $this->fxToken($this->fxSuperAdmin());

            for ($i = 1; $i <= 25; $i++) {
                $school = $this->fxSchool(sprintf('Sekolah %02d', $i));
                if ($endpoint === 'headmasters') {
                    $this->fxHeadmaster($school, '3' . sprintf('%03d', $i));
                }
            }

            return [$uri, $token];
        }

        // Scope sekolah: Headmaster sekolah A melihat 25 data A; sekolah B (2 data) tidak boleh ikut terhitung.
        $a = $this->fxSchool('SD A');
        $b = $this->fxSchool('SD B');
        $token = $this->fxToken($this->fxHeadmaster($a, '1001'));
        $this->fxHeadmaster($b, '1002');
        $yearA = $this->fxAcademicYear($a);
        $yearB = $this->fxAcademicYear($b);

        switch ($endpoint) {
            case 'teachers':
                for ($i = 1; $i <= 25; $i++) { $this->fxTeacher($a, '4' . sprintf('%03d', $i)); }
                for ($i = 1; $i <= 2; $i++)  { $this->fxTeacher($b, '4' . sprintf('9%02d', $i)); }
                break;

            case 'academic-years':
                for ($i = 1; $i <= 24; $i++) {   // + 1 yang sudah dibuat fxAcademicYear = 25
                    AcademicYear::create([
                        'school_id' => $a->id, 'name' => "AY {$i}", 'start_date' => '2020-07-01',
                        'end_date' => '2021-06-30', 'is_active' => false,
                    ]);
                }
                AcademicYear::create(['school_id' => $b->id, 'name' => 'AY B', 'start_date' => '2020-07-01', 'end_date' => '2021-06-30', 'is_active' => false]);
                break;

            case 'rombels':
                for ($i = 1; $i <= 25; $i++) { $this->fxRombel($a, $yearA, "R{$i}"); }
                for ($i = 1; $i <= 2; $i++)  { $this->fxRombel($b, $yearB, "RB{$i}"); }
                break;

            case 'teacher-class-assignments':
                for ($i = 1; $i <= 25; $i++) {
                    $this->fxTeacherAssignment($this->fxTeacher($a, '5' . sprintf('%03d', $i))->teacher, $this->fxRombel($a, $yearA, "R{$i}"));
                }
                for ($i = 1; $i <= 2; $i++) {
                    $this->fxTeacherAssignment($this->fxTeacher($b, '5' . sprintf('9%02d', $i))->teacher, $this->fxRombel($b, $yearB, "RB{$i}"));
                }
                break;
        }

        return [$uri, $token];
    }

    protected function list(string $uri, string $token, string $query = '')
    {
        return $this->apiGet($uri . $query, $token);
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function default_page_1_per_page_20_dengan_meta_lengkap(string $endpoint): void
    {
        [$uri, $token] = $this->scenario($endpoint);

        $response = $this->list($uri, $token)->assertStatus(200);

        $this->assertCount(20, $response->json('data'));
        $this->assertSame(['page' => 1, 'per_page' => 20, 'total' => 25, 'total_pages' => 2], $response->json('meta'));
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function halaman_2_berisi_sisa_tanpa_tumpang_tindih_dan_urutan_id_stabil(string $endpoint): void
    {
        [$uri, $token] = $this->scenario($endpoint);

        $p1 = $this->list($uri, $token, '?page=1')->assertStatus(200);
        $p2 = $this->list($uri, $token, '?page=2')->assertStatus(200);

        $this->assertCount(5, $p2->json('data'));
        $this->assertSame(['page' => 2, 'per_page' => 20, 'total' => 25, 'total_pages' => 2], $p2->json('meta'));

        $ids = array_merge(array_column($p1->json('data'), 'id'), array_column($p2->json('data'), 'id'));
        $this->assertCount(25, array_unique($ids), 'tidak boleh ada item dobel/hilang antar halaman');

        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'urutan harus id ASC dan konsisten lintas halaman');
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function per_page_kustom_dan_batas_100_diterima(string $endpoint): void
    {
        [$uri, $token] = $this->scenario($endpoint);

        $ten = $this->list($uri, $token, '?per_page=10')->assertStatus(200);
        $this->assertCount(10, $ten->json('data'));
        $this->assertSame(['page' => 1, 'per_page' => 10, 'total' => 25, 'total_pages' => 3], $ten->json('meta'));

        $max = $this->list($uri, $token, '?per_page=100')->assertStatus(200);
        $this->assertCount(25, $max->json('data'));
        $this->assertSame(['page' => 1, 'per_page' => 100, 'total' => 25, 'total_pages' => 1], $max->json('meta'));
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function per_page_di_atas_100_dan_param_tidak_valid_ditolak_422_validation_failed(string $endpoint): void
    {
        [$uri, $token] = $this->scenario($endpoint);

        foreach (['?per_page=101', '?per_page=0', '?per_page=abc', '?page=0', '?page=abc'] as $query) {
            $this->list($uri, $token, $query)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'validation_failed');
        }
    }

    /**
     * @test
     * @dataProvider endpoints
     */
    public function halaman_di_luar_rentang_data_kosong_dengan_meta_valid_bukan_error(string $endpoint): void
    {
        [$uri, $token] = $this->scenario($endpoint);

        $response = $this->list($uri, $token, '?page=999')->assertStatus(200);

        $this->assertSame([], $response->json('data'));
        $this->assertSame(['page' => 999, 'per_page' => 20, 'total' => 25, 'total_pages' => 2], $response->json('meta'));
    }

    // ---------- total dihitung SETELAH scope ----------

    /** @test */
    public function teacher_melihat_total_rombel_dan_assignment_miliknya_saja_bukan_jumlah_sekolah(): void
    {
        $school = $this->fxSchool();
        $year = $this->fxAcademicYear($school);
        $teacher = $this->fxTeacher($school, '2001');
        $own = $this->fxRombel($school, $year, 'VII-A');
        $this->fxRombel($school, $year, 'VII-B');
        $this->fxRombel($school, $year, 'VII-C');
        $this->fxTeacherAssignment($teacher->teacher, $own);
        $this->fxTeacherAssignment($this->fxTeacher($school, '2002')->teacher, $this->fxRombel($school, $year, 'VII-D'));
        $token = $this->fxToken($teacher);

        $rombels = $this->list('/api/v1/rombels', $token)->assertStatus(200);
        $this->assertSame([$own->id], array_column($rombels->json('data'), 'id'));
        $this->assertSame(['page' => 1, 'per_page' => 20, 'total' => 1, 'total_pages' => 1], $rombels->json('meta'));

        $assignments = $this->list('/api/v1/teacher-class-assignments', $token)->assertStatus(200);
        $this->assertCount(1, $assignments->json('data'));
        $this->assertSame(['page' => 1, 'per_page' => 20, 'total' => 1, 'total_pages' => 1], $assignments->json('meta'));
    }

    /** @test */
    public function daftar_kosong_tetap_200_dengan_meta_total_0_dan_total_pages_1(): void
    {
        $token = $this->fxToken($this->fxSuperAdmin());

        $response = $this->list('/api/v1/schools', $token)->assertStatus(200);

        $this->assertSame([], $response->json('data'));
        $this->assertSame(['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1], $response->json('meta'));
    }
}
