<?php

namespace App\Services\School;

use App\Models\Headmaster;
use App\Models\User;
use App\Services\User\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HeadmasterService
{
    public function __construct(protected AuditLogService $auditLog) {}

    public function index()
    {
        return Headmaster::with('school')->get();
    }

    public function show(int $id)
    {
        $headmaster = Headmaster::with('school')->find($id);

        if (!$headmaster) {
            throw new HttpException(404, 'Kepala Sekolah tidak ditemukan');
        }

        return $headmaster;
    }

    public function store(array $data): Headmaster
    {
        return DB::transaction(function () use ($data) {
            // Suksesi: nonaktifkan Headmaster aktif lama di sekolah ini (kalau ada)
            Headmaster::where('school_id', $data['school_id'])
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            $user = User::create([
                'role'     => 'headmaster',
                'username' => $data['nip'],
                'password' => Hash::make($data['nip']),
            ]);

            return Headmaster::create([
                'user_id'   => $user->id,
                'school_id' => $data['school_id'],
                'fullname'  => $data['name'],
                'nip'       => $data['nip'],
                'gender'    => $data['gender'],
                'status'    => 'active',
            ])->load('school');
        });
    }

    public function resetPassword(int $id, array $data): Headmaster
    {
        $headmaster = $this->show($id); // reuse 404 kalau tidak ada
        $actor = auth('api')->user();

        $headmaster->user->update(['password' => $data['new_password']]);

        $this->auditLog->record($actor, 'password_reset', $headmaster->user);

        return $headmaster;
    }
}