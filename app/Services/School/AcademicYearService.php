<?php

namespace App\Services\School;

use App\Exceptions\ApiException;
use App\Models\AcademicYear;
use App\Models\Headmaster;
use App\Traits\ConvertsUniqueViolation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AcademicYearService
{
    use ConvertsUniqueViolation;

    protected function currentUser()
    {
        return auth('api')->user();
    }

    protected function schoolIdForUser($user): ?int
    {
        // Headmaster & Teacher sama-sama discope ke school masing-masing.
        // F-14 residual: hanya profil AKTIF yang punya scope; selain itu 403 (pola R7).
        if ($user->role === 'headmaster') {
            return $user->activeHeadmasterSchoolId()
                ?? throw new HttpException(403, 'Profil Headmaster tidak aktif atau tidak ditemukan');
        }

        return $user->activeTeacherSchoolId()
            ?? throw new HttpException(403, 'Profil Teacher tidak aktif atau tidak ditemukan');
    }

    public function index()
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());

        return AcademicYear::where('school_id', $schoolId)->get();
    }

    public function show(int $id)
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());
        $academicYear = AcademicYear::where('school_id', $schoolId)->find($id);

        if (!$academicYear) {
            throw new HttpException(404, 'Tahun ajaran tidak ditemukan');
        }

        return $academicYear;
    }

    public function store(array $data): AcademicYear
    {
        $schoolId = $this->schoolIdForUser($this->currentUser());

        return AcademicYear::create([
            'school_id'  => $schoolId,
            'name'       => $data['name'],
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'is_active'  => false,
        ]);
    }

    public function update(int $id, array $data): AcademicYear
    {
        $academicYear = $this->findAcademicYearOrFail($id);
        $this->assertAcademicYearInScope($academicYear, $this->currentUser());

        $this->assertEffectiveDatesValid($academicYear, $data);

        $academicYear->update($data);

        return $academicYear->fresh();
    }

    public function activate(int $id): AcademicYear
    {
        $academicYear = $this->findAcademicYearOrFail($id);
        $this->assertAcademicYearInScope($academicYear, $this->currentUser());

        // F-03: dua activate konkuren (Academic Year berbeda, sekolah sama) sama-sama lolos
        // langkah "nonaktifkan yang lain", lalu yang kedua menabrak partial unique index
        // uq_academic_years_one_active_per_school -> dulu 500. Sekarang diterjemahkan jadi 409
        // (fallback API Contract §13); hasil akhir tetap tepat satu aktif karena transaksi
        // yang kalah di-rollback seluruhnya. Perilaku sekuensial (F-02) tidak berubah.
        return $this->convertUniqueViolation(fn () => $this->activateInTransaction($academicYear), [
            'uq_academic_years_one_active_per_school' => fn () => new ApiException(
                'academic_year_activation_conflict',
                409,
                'Aktivasi tahun ajaran bentrok dengan aktivasi lain yang sedang berjalan, silakan coba lagi'
            ),
        ]);
    }

    protected function activateInTransaction(AcademicYear $academicYear): AcademicYear
    {
        return DB::transaction(function () use ($academicYear) {
            // F-02: nonaktifkan Academic Year aktif LAIN saja; baris target sengaja
            // dikecualikan. Bila target sudah aktif, bulk query builder di bawah
            // (yang tidak diketahui Eloquent) dulu ikut menulis is_active=false ke
            // baris target, lalu $academicYear->update(['is_active' => true]) di-skip
            // karena nilai in-memory-nya sudah true (tidak dirty) -> sekolah berakhir
            // tanpa Academic Year aktif dan respons melaporkan is_active=false.
            AcademicYear::where('school_id', $academicYear->school_id)
                ->where('is_active', true)
                ->whereKeyNot($academicYear->getKey())
                ->update(['is_active' => false]);

            $academicYear->update(['is_active' => true]);

            return $academicYear->fresh();
        });
    }

    /**
     * F-04: PATCH sebagian tanggal harus dinilai terhadap NILAI EFEKTIF (input + nilai tersimpan),
     * bukan terhadap field lain di request. Rule `after:start_date` di request hanya bisa
     * membandingkan dua field yang sama-sama dikirim; kalau hanya satu yang dikirim, hasilnya
     * bisa melanggar CHECK chk_academic_years_period (end_date > start_date) -> 500.
     * Dilakukan di sini (setelah cek scope), bukan di FormRequest, supaya PATCH ke tahun ajaran
     * sekolah lain tetap 403 dan id tidak ada tetap 404, bukan 422 yang membocorkan data.
     */
    protected function assertEffectiveDatesValid(AcademicYear $academicYear, array $data): void
    {
        if (!array_key_exists('start_date', $data) && !array_key_exists('end_date', $data)) {
            return;
        }

        $start = Carbon::parse($data['start_date'] ?? $academicYear->start_date)->startOfDay();
        $end   = Carbon::parse($data['end_date'] ?? $academicYear->end_date)->startOfDay();

        if ($end->lessThanOrEqualTo($start)) {
            // Salahkan field yang memang dikirim (kalau dua-duanya dikirim: end_date).
            $field = array_key_exists('end_date', $data) ? 'end_date' : 'start_date';

            throw ValidationException::withMessages([
                $field => ['Tanggal selesai harus setelah tanggal mulai (dihitung terhadap nilai tersimpan bila salah satu tidak dikirim).'],
            ]);
        }
    }

    protected function findAcademicYearOrFail(int $id): AcademicYear
    {
        $academicYear = AcademicYear::find($id);

        if (!$academicYear) {
            throw new HttpException(404, 'Tahun ajaran tidak ditemukan');
        }

        return $academicYear;
    }

    protected function assertAcademicYearInScope(AcademicYear $academicYear, $user): void
    {
        if ($academicYear->school_id !== $this->schoolIdForUser($user)) {
            throw new HttpException(403, 'Tahun ajaran ini di luar sekolah Anda');
        }
    }
}