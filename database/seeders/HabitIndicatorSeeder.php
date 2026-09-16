<?php

namespace Database\Seeders;

use App\Models\Habit;
use App\Models\Indicator;
use Illuminate\Database\Seeder;

class HabitIndicatorSeeder extends Seeder
{
    /**
     * Poin final tiap tingkat indikator (Requirement v2.1 §4 & dokumen
     * Analisis & Threshold Level ANAKTUMBUH). MENURUN dari Indicator 1 (perilaku
     * terbaik) ke Indicator 4 (perilaku paling minimal). Bonus "Inisiatif Mandiri"
     * (+10) DIHITUNG TERPISAH di STUDENTHABIT-001, bukan di sini.
     */
    private const POIN = [50, 25, 15, 10];

    public function run(): void
    {
        $habits = [
            'Bangun Cepat' => [
                'Sebelum pukul 04.00',
                'Pukul 04.00 – 05.00',
                'Pukul 05.00 – 06.00',
                'Di atas pukul 06.00',
            ],
            'Beribadah' => [
                '100% dilaksanakan',
                '75% – 99% dilaksanakan',
                '50% – 74% dilaksanakan',
                'Di bawah 50% dilaksanakan',
            ],
            'Gemar Belajar' => [
                'Di atas 1 jam',
                '45 menit sampai 1 jam',
                '15 menit sampai 45 menit',
                'Di bawah 15 menit (termasuk tidak belajar)',
            ],
            'Berolahraga' => [
                'Di atas 1 jam',
                '45 menit sampai 1 jam',
                '15 menit sampai 45 menit',
                'Di bawah 15 menit (termasuk tidak olahraga)',
            ],
            'Bermasyarakat' => [
                'Menceklis 3 kegiatan positif (Opsi 1, 2, dan 3)',
                'Menceklis 2 dari kegiatan positif',
                'Menceklis 1 dari kegiatan positif',
                'Kurang bermasyarakat',
            ],
            'Makan Sehat dan Bergizi' => [
                'Sangat beragam makanan sehat dan bergizi',
                'Beragam makanan sehat',
                'Kurang beragam makanan sehat',
                'Tidak beragam makanan sehat (termasuk tidak sarapan/makan)',
            ],
            'Tidur Cepat' => [
                'Sebelum jam 20.00 (Jam 8 malam)',
                'Jam 20.00 – 21.00 (Jam 8–9 malam)',
                'Jam 21.00 – 22.00 (Jam 9–10 malam)',
                'Di atas jam 22.00 (Jam 10 malam)',
            ],
        ];

        foreach ($habits as $habitName => $indicatorNames) {
            $habit = Habit::firstOrCreate(['name' => $habitName]);

            foreach ($indicatorNames as $index => $indicatorName) {
                Indicator::updateOrCreate(
                    [
                        'habit_id' => $habit->id,
                        'name'     => $indicatorName,
                    ],
                    [
                        'poin' => self::POIN[$index],
                    ]
                );
            }
        }
    }
}