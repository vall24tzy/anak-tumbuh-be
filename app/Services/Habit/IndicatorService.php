<?php

namespace App\Services\Habit;

use App\Models\Indicator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IndicatorService
{
    public function index()
    {
        return Indicator::with('habit')->get();
    }

    public function show(int $id)
    {
        $indicator = Indicator::with('habit')->find($id);

        if (!$indicator) {
            throw new HttpException(404, 'Indicator tidak ditemukan');
        }

        return $indicator;
    }

    public function store(array $data)
    {
        return Indicator::create($data);
    }

    public function update(int $id, array $data)
    {
        $indicator = Indicator::find($id);

        if (!$indicator) {
            throw new HttpException(404, 'Indicator tidak ditemukan');
        }

        $indicator->update($data);

        return $indicator->fresh();
    }

    public function destroy(int $id)
    {
        $indicator = Indicator::find($id);

        if (!$indicator) {
            throw new HttpException(404, 'Indicator tidak ditemukan');
        }

        $indicator->delete();

        return true;
    }
}