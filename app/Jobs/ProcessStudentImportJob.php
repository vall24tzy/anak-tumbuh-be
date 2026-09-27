<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\User;
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
        protected int $rombelId,
    ) {}

    public function handle(StudentImportService $service): void
    {
        $batch = ImportBatch::find($this->importBatchId);

        if (!$batch) {
            return;
        }

        $batch->update(['status' => 'processing']);

        // Job jalan di worker terpisah, tidak punya konteks request/JWT dari
        // HTTP call awal — "login"-kan manual user yang upload (dicatat di
        // ImportBatch::created_by) supaya StudentService::store() bisa
        // resolve TeacherScopeService::activeAssignment() dengan benar.
        $actingUser = User::find($batch->created_by);
        if ($actingUser) {
            auth('api')->login($actingUser);
        }

        $fullPath = Storage::path($this->storedPath);
        $result = $service->commit($fullPath, $this->rombelId);

        $batch->update([
            'status'      => 'done',
            'total'       => $result['total_rows'],
            'success'     => $result['successful_rows'],
            'failed'      => $result['rejected_rows'],
            'failed_rows' => $result['errors'],
        ]);

        Storage::delete($this->storedPath);
    }
}
