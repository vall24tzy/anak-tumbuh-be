<?php

namespace App\Services\School;

use App\Models\Headmaster;
use App\Models\User;
use App\Services\Upload\UploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HeadmasterService
{
    public function __construct(protected UploadService $uploadService) {}

    public function index()
    {
        return Headmaster::with(['school', 'user'])->get();
    }

    public function show(int $id)
    {
        $headmaster = Headmaster::with(['school', 'user'])->find($id);

        if (!$headmaster) {
            throw new HttpException(404, 'Kepala Sekolah tidak ditemukan');
        }

        return $headmaster;
    }

    public function store(array $data): array
    {
        if (Headmaster::where('school_id', $data['school_id'])->exists()) {
            throw new HttpException(422, 'Sekolah ini sudah memiliki Kepala Sekolah');
        }

        $logoFile = $data['photo'] ?? null;
        unset($data['photo']);

        $plainPassword = Str::random(8);

        $headmaster = DB::transaction(function () use ($data, $plainPassword) {
            $user = User::create([
                'uuid'      => (string) Str::uuid(),
                'username'  => $data['username'],
                'email'     => $data['email'],
                'password'  => $plainPassword, // otomatis ke-hash (cast 'hashed' di model User)
                'level'     => 2,
                'is_active' => 1,
            ]);

            return Headmaster::create([
                'user_id'   => $user->id,
                'school_id' => $data['school_id'],
                'fullname'  => $data['fullname'],
                'nip'       => $data['nip'],
                'gender'    => $data['gender'],
            ]);
        });

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'headmasters', $headmaster->id);
            $headmaster->update(['photo' => $path]);
        }

        return [$headmaster->load(['school', 'user']), $plainPassword];
    }

    public function update(int $id, array $data)
    {
        $headmaster = Headmaster::find($id);

        if (!$headmaster) {
            throw new HttpException(404, 'Kepala Sekolah tidak ditemukan');
        }

        $logoFile = $data['photo'] ?? null;
        unset($data['photo']);

        $userFields = array_intersect_key($data, array_flip(['username', 'email']));
        $headmasterFields = array_diff_key($data, $userFields);

        if (!empty($userFields)) {
            $headmaster->user->update($userFields);
        }

        if (!empty($headmasterFields)) {
            $headmaster->update($headmasterFields);
        }

        if ($logoFile instanceof UploadedFile) {
            $path = $this->uploadService->upload($logoFile, 'headmasters', $headmaster->id, $headmaster->photo);
            $headmaster->update(['photo' => $path]);
        }

        return $headmaster->fresh()->load(['school', 'user']);
    }

    public function destroy(int $id)
    {
        $headmaster = Headmaster::find($id);

        if (!$headmaster) {
            throw new HttpException(404, 'Kepala Sekolah tidak ditemukan');
        }

        $headmaster->user->update(['is_active' => 0]);

        return true;
    }
}