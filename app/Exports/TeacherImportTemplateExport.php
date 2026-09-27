<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

// Template Import Guru (IMPORT-002): Nama Lengkap, NIP, Gender saja. TIDAK
// ADA Classroom/Email — Teacher tidak punya classroom (rombel lewat Teacher
// Class Assignment, task terpisah, bukan urusan import).

class TeacherImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return ['Nama Lengkap', 'NIP', 'Gender'];
    }

    public function array(): array
    {
        return [
            ['Contoh Nama Guru', '199001012020121001', 'laki-laki'],
        ];
    }
}