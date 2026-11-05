<?php

namespace Tests\Feature\Import;

use App\Http\Requests\School\TeacherImportRequest;
use App\Http\Requests\Student\StudentImportCommitRequest;
use App\Http\Requests\Student\StudentImportPreviewRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

// DEPLOY-LIMIT-001 (D-29, usulan) - batas ukuran file import xlsx diturunkan dari 5 MB ke 4 MB supaya
// penolakan 422 dari aplikasi muncul lebih dulu daripada 413 dari platform (batas body Vercel 4,5 MB).
// Berlaku untuk tiga rule: Student Import preview, Student Import commit, Teacher Import. Foto dan logo (2 MB)
// tidak berubah. Batas 200 baris (ImportService::MAX_ROWS) tidak berubah.
//
// Diuji di level rule: hanya aturan `max` yang dievaluasi, pada file palsu berukuran tepat 4096 KB (diterima)
// dan 4097 KB (ditolak). Aturan `mimes:xlsx` sengaja tidak ikut karena file palsu bukan xlsx sungguhan.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=ImportFileSizeLimitTest`.

class ImportFileSizeLimitTest extends TestCase
{
    /** @return array<string, array{0: class-string}> */
    public static function requests(): array
    {
        return [
            'student import preview' => [StudentImportPreviewRequest::class],
            'student import commit'  => [StudentImportCommitRequest::class],
            'teacher import'         => [TeacherImportRequest::class],
        ];
    }

    /** @dataProvider requests */
    public function test_rule_file_memakai_max_4096_dan_tidak_lagi_5120(string $request): void
    {
        $rules = (new $request())->rules()['file'];

        $this->assertContains('max:4096', $rules);
        $this->assertNotContains('max:5120', $rules);
        $this->assertContains('mimes:xlsx', $rules, 'format .xlsx saja tidak berubah (F-33)');
    }

    /** @dataProvider requests */
    public function test_file_tepat_4096_kb_lolos_dan_4097_kb_ditolak(string $request): void
    {
        $sizeRule = collect((new $request())->rules()['file'])->first(fn ($r) => is_string($r) && str_starts_with($r, 'max:'));

        $ok = Validator::make(['file' => UploadedFile::fake()->create('a.xlsx', 4096)], ['file' => [$sizeRule]]);
        $this->assertTrue($ok->passes(), '4096 KB harus diterima: ' . json_encode($ok->errors()->all()));

        $over = Validator::make(['file' => UploadedFile::fake()->create('a.xlsx', 4097)], ['file' => [$sizeRule]]);
        $this->assertTrue($over->fails(), '4097 KB harus ditolak');
        $this->assertArrayHasKey('file', $over->errors()->toArray());
    }

    public function test_batas_foto_dan_logo_2_mb_tidak_berubah(): void
    {
        $this->assertContains('max:2048', (new \App\Http\Requests\Student\CreateStudentRequest())->rules()['photo']);
        $this->assertContains('max:2048', (new \App\Http\Requests\School\CreateSchoolRequest())->rules()['logo']);
    }
}
