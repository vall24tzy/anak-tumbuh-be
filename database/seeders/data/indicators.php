<?php

// Data FIXED 7 habit x 4 indicator (28 baris) — Task Board v23 HABIT-001, sumber nama &
// kriteria: dokumen KEPUTUSAN SCORING 7 HABIT ANAKTUMBUH; struktur: DB Design v1.5
// §5.10-§5.11, API Contract v1.2 §21-§22.
//
// Jangan diparafrase / diubah kapitalisasi / diubah tanda baca (termasuk karakter
// en-dash "–", bukan hyphen biasa "-"). sort_order indicator 1-4 = Tier 1-4.
// point: null HANYA untuk 4 indikator Bermasyarakat (scoring count-based di
// STUDENTHABIT-001, BUKAN SUM(indicators.point)). Inisiatif ('Sadar sendiri'/'Disuruh')
// BUKAN indikator — tidak di-seed di sini.

return [
    [
        'code' => 'bangun_cepat',
        'name' => 'Bangun Cepat',
        'sort_order' => 1,
        'allows_multi_select' => false,
        'indicators' => [
            ['name' => 'Sebelum pukul 04.00', 'point' => 50, 'is_exclusive' => false],
            ['name' => 'Pukul 04.00 – 05.00', 'point' => 25, 'is_exclusive' => false],
            ['name' => 'Pukul 05.00 – 06.00', 'point' => 15, 'is_exclusive' => false],
            ['name' => 'Di atas pukul 06.00', 'point' => 10, 'is_exclusive' => false],
        ],
    ],
    [
        'code' => 'beribadah',
        'name' => 'Beribadah',
        'sort_order' => 2,
        'allows_multi_select' => false,
        'indicators' => [
            ['name' => '100% dilaksanakan', 'point' => 50, 'is_exclusive' => false],
            ['name' => '75% – 100%', 'point' => 25, 'is_exclusive' => false],
            ['name' => '50% – 75%', 'point' => 15, 'is_exclusive' => false],
            ['name' => 'Di bawah 50%', 'point' => 10, 'is_exclusive' => false],
        ],
    ],
    [
        'code' => 'gemar_belajar',
        'name' => 'Gemar Belajar',
        'sort_order' => 3,
        'allows_multi_select' => false,
        'indicators' => [
            ['name' => 'Di atas 1 jam', 'point' => 50, 'is_exclusive' => false],
            ['name' => '45 menit – 1 jam', 'point' => 25, 'is_exclusive' => false],
            ['name' => '15 menit – 45 menit', 'point' => 15, 'is_exclusive' => false],
            ['name' => 'Di bawah 15 menit', 'point' => 10, 'is_exclusive' => false],
        ],
    ],
    [
        'code' => 'berolahraga',
        'name' => 'Berolahraga',
        'sort_order' => 4,
        'allows_multi_select' => false,
        'indicators' => [
            ['name' => 'Di atas 1 jam', 'point' => 50, 'is_exclusive' => false],
            ['name' => '45 menit – 1 jam', 'point' => 25, 'is_exclusive' => false],
            ['name' => '15 menit – 45 menit', 'point' => 15, 'is_exclusive' => false],
            ['name' => 'Di bawah 15 menit', 'point' => 10, 'is_exclusive' => false],
        ],
    ],
    [
        'code' => 'bermasyarakat',
        'name' => 'Bermasyarakat',
        'sort_order' => 5,
        'allows_multi_select' => true,
        'indicators' => [
            ['name' => 'beres tempat tidur + kebersihan rumah', 'point' => null, 'is_exclusive' => false],
            ['name' => 'bantu pekerjaan orang tua', 'point' => null, 'is_exclusive' => false],
            ['name' => 'bermain teman sebaya', 'point' => null, 'is_exclusive' => false],
            ['name' => 'Kurang bermasyarakat', 'point' => null, 'is_exclusive' => true],
        ],
    ],
    [
        'code' => 'makan_sehat_bergizi',
        'name' => 'Makan Sehat dan Bergizi',
        'sort_order' => 6,
        'allows_multi_select' => false,
        'indicators' => [
            ['name' => 'Sangat beragam makanan sehat dan bergizi', 'point' => 50, 'is_exclusive' => false],
            ['name' => 'Beragam makanan sehat', 'point' => 25, 'is_exclusive' => false],
            ['name' => 'Kurang beragam makanan sehat', 'point' => 15, 'is_exclusive' => false],
            ['name' => 'Tidak beragam makanan sehat', 'point' => 10, 'is_exclusive' => false],
        ],
    ],
    [
        'code' => 'tidur_cepat',
        'name' => 'Tidur Cepat',
        'sort_order' => 7,
        'allows_multi_select' => false,
        'indicators' => [
            ['name' => 'Sebelum jam 8', 'point' => 50, 'is_exclusive' => false],
            ['name' => 'Jam 8 – 9', 'point' => 25, 'is_exclusive' => false],
            ['name' => 'Jam 9 – 10', 'point' => 15, 'is_exclusive' => false],
            ['name' => 'Di atas jam 10', 'point' => 10, 'is_exclusive' => false],
        ],
    ],
];
