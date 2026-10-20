<?php

namespace App\Http\Resources\Student;

// INTEG-QR-001 (Matrix #69): bentuk elemen GET /students (LIST) = StudentResource
// + qr_regenerated_at, HANYA bila pemanggil Teacher. Sengaja kelas terpisah dan
// BUKAN perubahan StudentResource, karena StudentResource juga dipakai
// GET /students/{id} dan POST /students; kedua endpoint itu tidak boleh memuat
// field ini (keputusan: field hanya di list).
//
// Sumber data: qr_tokens.regenerated_at (relasi Student::qrToken(), di-eager-load
// oleh StudentService::indexQueryForTeacher() agar tidak ada N+1). Format ISO 8601
// UTC dengan akhiran Z; null bila token belum pernah diregenerasi (token awal
// dibuat saat POST /students tanpa regenerated_at).

class StudentListResource extends StudentResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        if (auth('api')->user()?->role === 'teacher') {
            $data['qr_regenerated_at'] = $this->qrToken?->regenerated_at
                ?->clone()
                ->utc()
                ->toIso8601ZuluString();
        }

        return $data;
    }
}
