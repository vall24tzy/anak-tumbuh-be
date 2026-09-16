<?php

namespace App\Services\Habit;

use App\Models\Habit;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HabitService
{
    public function index()
    {
        return Habit::with('indicators')->get();
    }

    public function show(int $id)
    {
        $habit = Habit::with('indicators')->find($id);

        if (!$habit) {
            throw new HttpException(404, 'Habit tidak ditemukan');
        }

        return $habit;
    }

    public function store(array $data)
    {
        return Habit::create($data);
    }

    public function update(int $id, array $data)
    {
        $habit = Habit::find($id);

        if (!$habit) {
            throw new HttpException(404, 'Habit tidak ditemukan');
        }

        $habit->update($data);

        return $habit->fresh();
    }

    public function destroy(int $id)
    {
        $habit = Habit::find($id);

        if (!$habit) {
            throw new HttpException(404, 'Habit tidak ditemukan');
        }

        $habit->delete();

        return true;
    }
}