<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanUnitEntryExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private readonly array $data) {}

    public function headings(): array
    {
        return ['No. Unit', 'Tanggal', 'Pelanggan', 'No. HP', 'Nopol', 'Kendaraan', 'Tipe', 'No. Check Up', 'No. SA', 'Status SA', 'Nilai SA (Rp)'];
    }

    public function array(): array
    {
        return array_map(fn ($b) => [
            $b['entry_no'],
            $b['entry_date'],
            $b['customer'],
            $b['phone'],
            $b['plate_number'],
            $b['kendaraan'],
            $b['type'] === 'service' ? 'Service' : 'Check Up',
            $b['checkup_no'],
            $b['sa_no'],
            $b['status_sa'],
            $b['grand_total'],
        ], $this->data['baris']);
    }

    public function title(): string
    {
        return 'Unit Entry';
    }
}
