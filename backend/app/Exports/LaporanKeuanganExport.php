<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class LaporanKeuanganExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(private readonly array $data) {}

    public function headings(): array
    {
        return ['Keterangan', 'Kategori', 'Jumlah (Rp)'];
    }

    public function array(): array
    {
        $b = $this->data['bengkel'] ?? ['nama' => 'Bengkel', 'alamat' => '', 'telepon' => ''];
        $r = $this->data['ringkasan'];

        $baris = [
            [$b['nama'], 'Laporan Keuangan', ''],
            [$b['alamat'], 'Periode', $this->data['label_periode']],
            [$b['telepon'], 'Tanggal cetak', now()->format('d/m/Y H:i')],
            ['', '', ''],
            ['RINGKASAN', '', ''],
            ['Total pemasukan', '', $r['total_pemasukan']],
            ['Total pengeluaran', '', $r['total_pengeluaran']],
            ['Arus kas (masuk - keluar)', '', $r['arus_kas']],
            ['Pendapatan jasa', '', $r['pendapatan_jasa']],
            ['Pendapatan sparepart', '', $r['pendapatan_sparepart']],
            ['HPP sparepart terjual', '', $r['hpp_sparepart']],
            ['Laba kotor', '', $r['laba_kotor']],
            ['Jumlah SA dibayar', '', $r['jumlah_sa_dibayar']],
            ['Rata-rata per SA', '', $r['rata_rata_per_sa']],
            ['', '', ''],
            ['RINCIAN PEMASUKAN', '', ''],
        ];

        foreach ($this->data['pemasukan'] as $item) {
            $baris[] = ['', $item['label'], $item['total']];
        }

        $baris[] = ['', '', ''];
        $baris[] = ['RINCIAN PENGELUARAN', '', ''];

        foreach ($this->data['pengeluaran'] as $item) {
            $baris[] = ['', $item['label'], $item['total']];
        }

        return $baris;
    }

    public function title(): string
    {
        return 'Laporan Keuangan';
    }
}
