<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GenericHeadingRowImport implements ToArray, WithHeadingRow
{
    public function array(array $array): array
    {
        return $array;
    }
}