<?php

namespace App\Http\Controllers\V1\School;

use App\Exports\TeacherImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\TeacherImportRequest;
use App\Jobs\ProcessTeacherImportJob;
use App\Models\ImportBatch;
use App\Services\School\TeacherImportService;
use App\Traits\ApiResponseTraits;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\HttpException;

// API Contract v1.2 §15/§28. Headmaster only. Endpoint utama sesuai task
// board: POST /api/v1/teachers/import. template() & status() TIDAK ada di
// API Contract (NOTE-05) — dipertahankan sebagai technical necessity (kolom
// template disebut eksplisit di Acceptance Criteria; status untuk polling
// queue >100 baris), pola sama seperti StudentImportController::status().

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

        if ($rowCount > 1000) {
            throw new HttpException(422, 'File melebihi batas maksimal 1.000 baris');
        }

        $user = auth('api')->user();

        $batch = ImportBatch::create([
            'type'       => 'teacher',
            'status'     => 'pending',
            'created_by' => $user->id,
        ]);

        if ($rowCount <= 100) {
            $batch->update(['status' => 'processing']);
            $result = $this->service->commit($file);
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
        ProcessTeacherImportJob::dispatch($batch->id, $storedPath);

        return $this->successResponse([
            'batch_id' => $batch->id,
            'status'   => 'pending',
        ]);
    }

    public function status(int $batchId)
    {
        $batch = ImportBatch::find($batchId);

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