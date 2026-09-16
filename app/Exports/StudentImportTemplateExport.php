<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StudentImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['Nama Lengkap', 'NISN', 'Gender', 'Classroom', 'Tingkat/Grade Level', 'Email'];
    }

    public function array(): array
    {
        return [
            ['Contoh Nama Siswa', '1234567890', 'laki-laki', '6A', 6, 'opsional@email.com'],
        ];
    }
}