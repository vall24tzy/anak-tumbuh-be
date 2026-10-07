<?php

namespace App\Http\Controllers\V1\School;

use App\Exports\TeacherImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\TeacherImportRequest;
use App\Models\ImportBatch;
use App\Services\Import\ImportService;
use App\Services\School\TeacherImportService;
use App\Traits\ApiResponseTraits;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §15/§28. Headmaster only. Endpoint utama sesuai task
// board: POST /api/v1/teachers/import. template() & status() TIDAK ada di
// API Contract (NOTE-05) — dipertahankan sebagai technical necessity (kolom
// template disebut eksplisit di Acceptance Criteria; status untuk membaca HASIL
// batch yang sudah selesai, bukan lagi polling queue: F-38), pola sama seperti
// StudentImportController::status().

class TeacherImportController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected TeacherImportService $service) {}

    public function template()
    {
        return Excel::download(new TeacherImportTemplateExport(), 'template_import_guru.xlsx');
    }

    public function import(TeacherImportRequest $request)
    {
        $file = $request->file('file');
        $rowCount = $this->service->countRows($file);

        // F-38 (batas baris): lihat ImportService::MAX_ROWS (turun dari 1.000 karena import sinkron).
        if ($rowCount > ImportService::MAX_ROWS) {
            throw new HttpException(422, 'File melebihi batas maksimal ' . ImportService::MAX_ROWS . ' baris');
        }

        $user = auth('api')->user();

        $batch = ImportBatch::create([
            'type'       => 'teacher',
            'status'     => 'processing',
            'created_by' => $user->id,
        ]);

        // F-38 (keputusan FINAL pemilik): import SELALU sinkron untuk SEMUA ukuran file (sampai batas
        // batas baris di atas); tidak ada jalur queue/async dan tidak ada cabang berdasarkan jumlah
        // baris. Response selalu HTTP 200 dengan lima field kontrak (+ batch_id untuk membaca hasil
        // lewat status()). File upload tidak pernah disimpan ke storage (dibaca langsung dari request).
        //
        // F-37 (tetap berlaku di jalur sinkron): bila commit() melempar exception (mis. 403 profil
        // Headmaster tidak aktif), batch tidak boleh tertahan di 'processing'; tandai 'failed' lalu
        // lempar ulang supaya response error tetap seperti sebelumnya.
        try {
            $result = $this->service->commit($file);
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'failed_rows' => []]);

            throw $e;
        }

        $batch->update([
            'status'      => 'done',
            'total'       => $result['total_rows'],
            'success'     => $result['successful_rows'],
            'failed'      => $result['rejected_rows'],
            'failed_rows' => $result['errors'],
        ]);

        return $this->successResponse(array_merge(['batch_id' => $batch->id], $result));
    }

    public function status(int $batchId)
    {
        // F-32: batch hanya terbaca oleh PEMILIKNYA (created_by = user yang sedang login)
        // dan hanya untuk jenis 'teacher'. Batch milik orang lain, sekolah lain, jenis
        // lain, atau created_by NULL tidak dibedakan dari batch yang tidak ada (404),
        // supaya keberadaan batch tidak bocor. Bentuk respons 200 tidak diubah.
        $batch = ImportBatch::where('type', 'teacher')
            ->where('created_by', auth('api')->id())
            ->find($batchId);

        if (!$batch) {
            throw new HttpException(404, 'Batch import tidak ditemukan');
        }

        return $this->successResponse([
            'batch_id'        => $batch->id,
            'status'          => $batch->status,
            'total_rows'      => $batch->total,
            'processed_rows'  => $batch->total,
            'successful_rows' => $batch->success,
            'rejected_rows'   => $batch->failed,
            'errors'          => $batch->failed_rows,
        ]);
    }
}