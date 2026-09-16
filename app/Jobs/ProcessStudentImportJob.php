<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\User; // <-- tambah ini
use App\Services\Student\StudentImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessStudentImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $importBatchId,
        protected string $storedPath,
        protected int $schoolId,
    ) {}

    public function handle(StudentImportService $service): void
    {
        $batch = ImportBatch::find($this->importBatchId);

        if (!$batch) {
            return;
        }

        $batch->update(['status' => 'processing']);

        // Job ini jalan di worker terpisah, TIDAK punya konteks request/JWT
        // dari HTTP call awal. StudentService::store() butuh auth('api')->user()
        // buat cek scoping (Headmaster/Guru), jadi kita "login"-kan manual
        // user yang upload file ini (sudah dicatat di ImportBatch::created_by).
        $actingUser = User::find($batch->created_by);
        if ($actingUser) {
            auth('api')->login($actingUser);
        }

        $fullPath = Storage::path($this->storedPath);
        $result = $service->generate($fullPath, $this->schoolId);

        $batch->update([
            'status'      => 'done',
            'total'       => $result['total'],
            'success'     => $result['success'],
            'failed'      => $result['failed'],
            'failed_rows' => $result['failed_rows'],
        ]);

        Storage::delete($this->storedPath);
    }
}