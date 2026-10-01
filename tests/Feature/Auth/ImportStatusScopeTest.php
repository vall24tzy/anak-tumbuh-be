<?php

namespace Tests\Feature\Auth;

use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// F-32 - GET /teachers/import/status/{batchId} dan GET /student-imports/status/{batchId}
// hanya boleh dibaca PEMILIK batch.
//
// Bug: kedua controller memakai ImportBatch::find($batchId) tanpa filter, sehingga
// Headmaster/Teacher mana pun bisa membaca hasil import orang lain (NIP/NISN, nama, error
// baris), termasuk batch milik sekolah lain dan batch jenis lain. SoT: Matrix v3.1 F-32,
// API Contract §26 (cross-school DENY; GET di luar scope -> 404), Task Board NOTE-05/NOTE-09
// (endpoint status = technical necessity).
//
// Definisi "pemilik" (dipilih): created_by = user yang sedang login, plus type batch sesuai
// endpoint. Alasan: import Siswa berscope own rombel (§26 cross-rombel DENY), jadi
// "satu sekolah" terlalu longgar; SoT tidak memberi hak baca kepada non-pemilik mana pun.
// Konsekuensi yang dikunci test: Headmaster pengganti (suksesi) tidak dapat membaca batch
// pendahulunya, dan Teacher lain di sekolah yang sama tidak dapat membaca batch rekannya.
//
// Yang dikunci:
//   - pemilik -> 200 dengan bentuk respons yang SAMA seperti sebelum fix (tidak diubah, F-38)
//   - batch milik user lain (sekolah lain / sekolah sama), jenis lain, created_by NULL,
//     dan id yang tidak ada -> 404 dengan respons identik (keberadaan batch tidak bocor)
//   - role yang salah tetap ditolak middleware (403/401), tidak berubah
//
// STATUS EKSEKUSI: dijalankan di PHP + PostgreSQL; hasil terkini lihat docs/testing/BASELINE-STAGE-0C.md

class ImportStatusScopeTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    private const TEACHER_URI = '/api/v1/teachers/import/status/';
    private const STUDENT_URI = '/api/v1/student-imports/status/';

    protected function batch(string $type, ?User $owner, array $overrides = []): ImportBatch
    {
        return ImportBatch::create(array_merge([
            'type'        => $type,
            'status'      => 'done',
            'total'       => 3,
            'success'     => 2,
            'failed'      => 1,
            'failed_rows' => [['row' => 3, 'field' => 'nip', 'code' => 'validation_failed', 'message' => 'rahasia-baris']],
            'created_by'  => $owner?->id,
        ], $overrides));
    }

    protected function expectedShape(ImportBatch $batch): array
    {
        return [
            'batch_id'        => $batch->id,
            'status'          => 'done',
            'total_rows'      => 3,
            'processed_rows'  => 3,
            'successful_rows' => 2,
            'rejected_rows'   => 1,
            'errors'          => [['row' => 3, 'field' => 'nip', 'code' => 'validation_failed', 'message' => 'rahasia-baris']],
        ];
    }

    protected function assertNotFoundIdentical($response, $reference): void
    {
        $response->assertStatus(404)->assertJsonPath('error.code', 'not_found');

        $this->assertSame($reference->getStatusCode(), $response->getStatusCode());
        $this->assertSame($reference->json('error'), $response->json('error'));
        $this->assertStringNotContainsString('rahasia-baris', $response->getContent());
    }

    // ---------- Teacher import status (Headmaster) ----------

    /** @test */
    public function teacher_import_status_pemilik_200_dengan_bentuk_respons_tidak_berubah(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');
        $batch = $this->batch('teacher', $hm);

        $response = $this->apiGet(self::TEACHER_URI . $batch->id, $this->fxToken($hm));

        $response->assertStatus(200);
        $this->assertEquals($this->expectedShape($batch), $response->json('data'));
        $this->assertSame(
            ['batch_id', 'status', 'total_rows', 'processed_rows', 'successful_rows', 'rejected_rows', 'errors'],
            array_keys($response->json('data'))
        );
    }

    /** @test */
    public function teacher_import_status_headmaster_sekolah_lain_404(): void
    {
        $owner = $this->fxHeadmaster($this->fxSchool('SD Satu'), '1001');
        $other = $this->fxHeadmaster($this->fxSchool('SD Dua'), '1002');
        $batch = $this->batch('teacher', $owner);
        $token = $this->fxToken($other);

        $missing = $this->apiGet(self::TEACHER_URI . ($batch->id + 1000), $token);

        $this->assertNotFoundIdentical($this->apiGet(self::TEACHER_URI . $batch->id, $token), $missing);
    }

    /** @test */
    public function teacher_import_status_batch_pendahulu_di_sekolah_yang_sama_404_bagi_headmaster_pengganti(): void
    {
        $school = $this->fxSchool();
        $old = $this->fxHeadmaster($school, '1001');
        $old->headmaster->update(['status' => 'inactive']);   // suksesi: hanya satu HM aktif per sekolah
        $new = $this->fxHeadmaster($school, '1002');
        $batch = $this->batch('teacher', $old);
        $token = $this->fxToken($new);

        $missing = $this->apiGet(self::TEACHER_URI . ($batch->id + 1000), $token);

        $this->assertNotFoundIdentical($this->apiGet(self::TEACHER_URI . $batch->id, $token), $missing);
    }

    /** @test */
    public function teacher_import_status_batch_jenis_student_milik_sendiri_tetap_404(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');
        $studentTypeBatch = $this->batch('student', $hm);   // type tidak cocok walau created_by = HM ini
        $token = $this->fxToken($hm);

        $missing = $this->apiGet(self::TEACHER_URI . ($studentTypeBatch->id + 1000), $token);

        $this->assertNotFoundIdentical($this->apiGet(self::TEACHER_URI . $studentTypeBatch->id, $token), $missing);
    }

    /** @test */
    public function teacher_import_status_created_by_null_404(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');
        $orphan = $this->batch('teacher', null);
        $token = $this->fxToken($hm);

        $missing = $this->apiGet(self::TEACHER_URI . ($orphan->id + 1000), $token);

        $this->assertNotFoundIdentical($this->apiGet(self::TEACHER_URI . $orphan->id, $token), $missing);
    }

    /** @test */
    public function teacher_import_status_id_tidak_ada_404(): void
    {
        $hm = $this->fxHeadmaster($this->fxSchool(), '1001');

        $this->apiGet(self::TEACHER_URI . '999999', $this->fxToken($hm))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    // ---------- Student import status (Teacher) ----------

    /** @test */
    public function student_import_status_pemilik_200_dengan_bentuk_respons_tidak_berubah(): void
    {
        $teacher = $this->fxTeacher($this->fxSchool(), '2001');
        $batch = $this->batch('student', $teacher);

        $response = $this->apiGet(self::STUDENT_URI . $batch->id, $this->fxToken($teacher));

        $response->assertStatus(200);
        $this->assertEquals($this->expectedShape($batch), $response->json('data'));
        $this->assertSame(
            ['batch_id', 'status', 'total_rows', 'processed_rows', 'successful_rows', 'rejected_rows', 'errors'],
            array_keys($response->json('data'))
        );
    }

    /** @test */
    public function student_import_status_teacher_lain_di_sekolah_yang_sama_404(): void
    {
        $school = $this->fxSchool();
        $owner = $this->fxTeacher($school, '2001');
        $colleague = $this->fxTeacher($school, '2002');
        $batch = $this->batch('student', $owner);
        $token = $this->fxToken($colleague);

        $missing = $this->apiGet(self::STUDENT_URI . ($batch->id + 1000), $token);

        $this->assertNotFoundIdentical($this->apiGet(self::STUDENT_URI . $batch->id, $token), $missing);
    }

    /** @test */
    public function student_import_status_teacher_sekolah_lain_404(): void
    {
        $owner = $this->fxTeacher($this->fxSchool('SD Satu'), '2001');
        $other = $this->fxTeacher($this->fxSchool('SD Dua'), '2002');
        $batch = $this->batch('student', $owner);
        $token = $this->fxToken($other);

        $missing = $this->apiGet(self::STUDENT_URI . ($batch->id + 1000), $token);

        $this->assertNotFoundIdentical($this->apiGet(self::STUDENT_URI . $batch->id, $token), $missing);
    }

    /** @test */
    public function student_import_status_batch_jenis_teacher_milik_sendiri_tetap_404(): void
    {
        $teacher = $this->fxTeacher($this->fxSchool(), '2001');
        $teacherTypeBatch = $this->batch('teacher', $teacher);   // type tidak cocok walau created_by = Teacher ini
        $token = $this->fxToken($teacher);

        $missing = $this->apiGet(self::STUDENT_URI . ($teacherTypeBatch->id + 1000), $token);

        $this->assertNotFoundIdentical($this->apiGet(self::STUDENT_URI . $teacherTypeBatch->id, $token), $missing);
    }

    /** @test */
    public function student_import_status_created_by_null_404(): void
    {
        $teacher = $this->fxTeacher($this->fxSchool(), '2001');
        $orphan = $this->batch('student', null);
        $token = $this->fxToken($teacher);

        $missing = $this->apiGet(self::STUDENT_URI . ($orphan->id + 1000), $token);

        $this->assertNotFoundIdentical($this->apiGet(self::STUDENT_URI . $orphan->id, $token), $missing);
    }

    /** @test */
    public function student_import_status_id_tidak_ada_404(): void
    {
        $teacher = $this->fxTeacher($this->fxSchool(), '2001');

        $this->apiGet(self::STUDENT_URI . '999999', $this->fxToken($teacher))
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'not_found');
    }

    // ---------- Regresi middleware: role salah tetap ditolak ----------

    /** @test */
    public function role_yang_salah_dan_tanpa_token_tetap_ditolak_middleware(): void
    {
        $school = $this->fxSchool();
        $hm = $this->fxHeadmaster($school, '1001');
        $teacher = $this->fxTeacher($school, '2001');
        $hmBatch = $this->batch('teacher', $hm);
        $teacherBatch = $this->batch('student', $teacher);

        // Teacher ke endpoint Guru (role:headmaster) dan Headmaster ke endpoint Siswa (role:teacher)
        $this->apiGet(self::TEACHER_URI . $hmBatch->id, $this->fxToken($teacher))
            ->assertStatus(403)->assertJsonPath('error.code', 'forbidden');
        $this->apiGet(self::STUDENT_URI . $teacherBatch->id, $this->fxToken($hm))
            ->assertStatus(403)->assertJsonPath('error.code', 'forbidden');

        $this->apiGet(self::TEACHER_URI . $hmBatch->id)->assertStatus(401);
        $this->apiGet(self::STUDENT_URI . $teacherBatch->id)->assertStatus(401);
    }
}
