<?php

namespace App\Http\Controllers\V1\Student;

use App\Exports\StudentImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StudentImportCommitRequest;
use App\Http\Requests\Student\StudentImportPreviewRequest;
use App\Models\ImportBatch;
use App\Services\Import\ImportService;
use App\Services\Student\StudentImportService;
use App\Traits\ApiResponseTraits;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §18/§28. Teacher only (Matrix — SA/HM tidak
// create/import Student). Path top-level "student-imports" (bukan nested di
// bawah "students") sesuai kontrak.

class StudentImportController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected StudentImportService $service) {}

    public function template()
    {
        return Excel::download(new StudentImportTemplateExport(), 'template_import_siswa.xlsx');
    }

    public function preview(StudentImportPreviewRequest $request)
    {
        $result = $this->service->preview($request->file('file'));

        return $this->successResponse($result);
    }

    public function commit(StudentImportCommitRequest $request)
    {
        $file = $request->file('file');
        $rombelId = (int) $request->input('rombel_id');

        // Revalidasi server-side eksplisit sebelum apapun diproses — API
        // Contract §18/§28: commit tidak boleh percaya hasil preview client.
        $this->service->assertOwnActiveRombel($rombelId);

        $rowCount = $this->service->countRows($file);

        // F-38 (batas baris): lihat ImportService::MAX_ROWS (turun dari 1.000 karena import sinkron).
        if ($rowCount > ImportService::MAX_ROWS) {
            throw new HttpException(422, 'File melebihi batas maksimal ' . ImportService::MAX_ROWS . ' baris');
        }

        $user = auth('api')->user();

        $batch = ImportBatch::create([
            'type'       => 'student',
            'status'     => 'processing',
            'created_by' => $user->id,
        ]);

        // F-38 (keputusan FINAL pemilik): sama dengan Teacher import. Selalu sinkron untuk semua
        // ukuran file sampai batas baris di atas, tanpa queue dan tanpa cabang jumlah baris; response
        // selalu HTTP 200 dengan lima field kontrak (+ batch_id). F-37: exception di commit()
        // menandai batch 'failed' (tidak tertahan di 'processing') lalu dilempar ulang.
        try {
            $result = $this->service->commit($file, $rombelId);
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

    /**
     * NOTE-09 (docs/db-gap-analysis.md): endpoint status batch TIDAK ada di
     * API Contract v1.2 — dipertahankan sebagai technical necessity, bukan
     * business rule baru (instruksi task poin 6). F-38: import kini selalu
     * sinkron, jadi endpoint ini membaca HASIL batch yang sudah selesai
     * (bukan lagi polling queue).
     */
    public function status(int $batchId)
    {
        // F-32: batch hanya terbaca oleh PEMILIKNYA (created_by = user yang sedang login)
        // dan hanya untuk jenis 'student'. Batch milik orang lain, sekolah/rombel lain,
        // jenis lain, atau created_by NULL tidak dibedakan dari batch yang tidak ada (404),
        // supaya keberadaan batch tidak bocor. Bentuk respons 200 tidak diubah.
        $batch = ImportBatch::where('type', 'student')
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
