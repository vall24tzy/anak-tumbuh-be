<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// LEVEL-RANKING-001 — integritas file data threshold (murni PHP, tanpa DB/Laravel).
// Sumber angka: dokumen "Analisis & Threshold Level ANAKTUMBUH" bagian 6.
class GamificationLevelDataTest extends TestCase
{
    protected function levels(): array
    {
        return require __DIR__ . '/../../database/seeders/data/gamification_levels.php';
    }

    /** @test */
    public function ada_101_baris_level_0_sampai_100(): void
    {
        $levels = $this->levels();

        $this->assertCount(101, $levels);
        $this->assertSame(range(0, 100), array_keys($levels));
    }

    /** @test */
    public function threshold_strictly_increasing_dan_tidak_negatif(): void
    {
        $values = array_values($this->levels());

        $this->assertSame(0, $values[0]);

        for ($i = 1; $i <= 100; $i++) {
            $this->assertGreaterThan($values[$i - 1], $values[$i], "Level {$i} harus > level " . ($i - 1));
        }
    }

    /** @test */
    public function titik_acuan_sot_cocok_persis(): void
    {
        $levels = $this->levels();

        // Level 0-4 & 100: API Contract §23 + dokumen §5; Level 5 = 757 (API Contract §23).
        $this->assertSame(0, $levels[0]);
        $this->assertSame(50, $levels[1]);
        $this->assertSame(100, $levels[2]);
        $this->assertSame(200, $levels[3]);
        $this->assertSame(400, $levels[4]);
        $this->assertSame(757, $levels[5]);
        $this->assertSame(750592, $levels[100]);
    }

    /** @test */
    public function beberapa_nilai_tengah_persis_sesuai_dokumen(): void
    {
        $levels = $this->levels();

        $this->assertSame(1271, $levels[6]);
        $this->assertSame(24952, $levels[20]);
        $this->assertSame(112162, $levels[40]);
        $this->assertSame(262172, $levels[60]);
        $this->assertSame(474982, $levels[80]);
        $this->assertSame(735320, $levels[99]);
    }
}
