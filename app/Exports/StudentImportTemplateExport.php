<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

// Template FINAL (IMPORT-001): Nama Lengkap, NISN, Gender saja. TIDAK ADA
// Classroom/Tingkat-Grade Level/Email — grade_level kini murni dari
// rombels.grade_level (Master Rombel, ROMBEL-001), rombel_id dikirim sebagai
// parameter request terpisah (bukan kolom Excel per-baris).

class StudentImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['Nama Lengkap', 'NISN', 'Gender'];
    }

    public function array(): array
    {
        return [
            ['Contoh Nama Siswa', '1234567890', 'laki-laki'],
        ];
    }
}
