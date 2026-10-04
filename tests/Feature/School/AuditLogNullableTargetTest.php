<?php

namespace Tests\Feature\School;

use App\Models\AuditLog;
use App\Services\User\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSchoolFixtures;
use Tests\TestCase;

// Stage 4A / F-31 (Matrix v3.1, keputusan FINAL): audit_logs.target_user_id nullable (DB Design §5.16).
// Insert tanpa target berhasil; insert dengan target tetap berfungsi seperti sebelumnya.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP/PostgreSQL; hasil run lihat laporan patch.

class AuditLogNullableTargetTest extends TestCase
{
    use RefreshDatabase, CreatesSchoolFixtures;

    /** @test */
    public function f31_kolom_target_user_id_nullable_di_skema(): void
    {
        $row = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns WHERE table_name = 'audit_logs' AND column_name = 'target_user_id'"
        );

        $this->assertSame('YES', $row->is_nullable);
    }

    /** @test */
    public function f31_record_tanpa_target_tersimpan_dengan_target_null(): void
    {
        $actor = $this->fxSuperAdmin();

        app(AuditLogService::class)->record($actor, 'event_tanpa_target');

        $log = AuditLog::where('action', 'event_tanpa_target')->first();
        $this->assertNotNull($log);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertNull($log->target_user_id);
    }

    /** @test */
    public function f31_record_dengan_target_tetap_menyimpan_target(): void
    {
        $actor = $this->fxSuperAdmin();
        $target = $this->fxTeacher($this->fxSchool(), '2001');

        app(AuditLogService::class)->record($actor, 'password_reset', $target);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->id, 'action' => 'password_reset', 'target_user_id' => $target->id,
        ]);
    }
}
