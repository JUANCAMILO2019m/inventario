<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AnimalsExport implements Export, WithMultipleSheets
{
    public function __construct(protected Collection $animals)
    {
    }

    public function sheets(): array
    {
        return [
            new AnimalsListSheet($this->animals),
            new AnimalRecordsSheet($this->animals),
        ];
    }
}