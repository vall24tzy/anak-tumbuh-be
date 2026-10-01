<?php

namespace App\Http\Controllers\V1\Student;

use App\Exports\StudentImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StudentImportCommitRequest;
use App\Http\Requests\Student\StudentImportPreviewRequest;
use App\Jobs\ProcessStudentImportJob;
use App\Models\ImportBatch;
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

        if ($rowCount > 1000) {
            throw new HttpException(422, 'File melebihi batas maksimal 1.000 baris');
        }

        $user = auth('api')->user();

        $batch = ImportBatch::create([
            'type'       => 'student',
            'status'     => 'pending',
            'created_by' => $user->id,
        ]);

        if ($rowCount <= 100) {
            $batch->update(['status' => 'processing']);
            $result = $this->service->commit($file, $rombelId);
            $batch->update([
                'status'      => 'done',
                'total'       => $result['total_rows'],
                'success'     => $result['successful_rows'],
                'failed'      => $result['rejected_rows'],
                'failed_rows' => $result['errors'],
            ]);

            return $this->successResponse(array_merge(['batch_id' => $batch->id], $result));
        }

        $storedPath = $file->store('imports');
        ProcessStudentImportJob::dispatch($batch->id, $storedPath, $rombelId);

        return $this->successResponse([
            'batch_id' => $batch->id,
            'status'   => 'pending',
        ]);
    }

    /**
     * NOTE-09 (docs/db-gap-analysis.md): endpoint status/polling batch queue
     * TIDAK ada di API Contract v1.2 — dipertahankan sebagai technical
     * necessity untuk file >100 baris (queue asinkron), bukan business rule
     * baru (instruksi task poin 6).
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
