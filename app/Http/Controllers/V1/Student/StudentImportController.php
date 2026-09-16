<?php

namespace App\Http\Controllers\V1\Student;

use App\Exports\StudentImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessStudentImportJob;
use App\Models\ImportBatch;
use App\Services\Student\StudentImportService;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StudentImportController extends Controller
{
    use ApiResponseTraits;

    public function __construct(protected StudentImportService $service) {}

    public function template()
    {
        return Excel::download(new StudentImportTemplateExport(), 'template_import_siswa.xlsx');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'], // 5120 KB = 5 MB
        ]);

        $result = $this->service->preview($request->file('file'));

        return $this->successResponse($result, 'Preview import berhasil diambil');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $file = $request->file('file');
        $rowCount = $this->service->countRows($file);

        if ($rowCount > 1000) {
            throw new HttpException(422, 'File melebihi batas maksimal 1.000 baris');
        }

        $user = auth('api')->user();
        $schoolId = $this->service->resolveSchoolId($user, $request->input('school_id'));

        $batch = ImportBatch::create([
            'type'       => 'student',
            'status'     => 'pending',
            'created_by' => $user->id,
        ]);

        if ($rowCount <= 100) {
            $batch->update(['status' => 'processing']);
            $result = $this->service->generate($file, $schoolId);
            $batch->update([
                'status'      => 'done',
                'total'       => $result['total'],
                'success'     => $result['success'],
                'failed'      => $result['failed'],
                'failed_rows' => $result['failed_rows'],
            ]);

            return $this->successResponse(array_merge(['batch_id' => $batch->id], $result), 'Import selesai diproses');
        }

        $storedPath = $file->store('imports');
        ProcessStudentImportJob::dispatch($batch->id, $storedPath, $schoolId);

        return $this->successResponse([
            'batch_id' => $batch->id,
            'status'   => 'pending',
        ], 'File diterima, sedang diproses di background. Cek status lewat endpoint status.');
    }

    public function status(int $batchId)
    {
        $batch = ImportBatch::find($batchId);

        if (!$batch) {
            throw new HttpException(404, 'Batch import tidak ditemukan');
        }

        return $this->successResponse([
            'batch_id'    => $batch->id,
            'status'      => $batch->status,
            'total'       => $batch->total,
            'success'     => $batch->success,
            'failed'      => $batch->failed,
            'failed_rows' => $batch->failed_rows,
        ], 'Status import berhasil diambil');
    }
}