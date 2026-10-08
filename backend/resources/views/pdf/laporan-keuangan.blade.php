<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111827; }
        .kop { border-bottom: 2px solid #dc2626; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 18px; margin: 0; color: #dc2626; }
        .judul { font-size: 14px; font-weight: bold; margin: 8px 0 2px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; border: 1px solid #e5e7eb; padding: 5px; text-align: left; }
        td { border: 1px solid #e5e7eb; padding: 5px; }
        .kanan { text-align: right; }
        .kotak { border: 1px solid #e5e7eb; padding: 8px; margin-bottom: 12px; }
        .kotak td { border: none; padding: 2px 0; }
        h3 { font-size: 12px; margin: 12px 0 4px; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $bengkel['nama'] }}</h1>
        <p>{{ $bengkel['alamat'] }} {{ $bengkel['telepon'] ? '· '.$bengkel['telepon'] : '' }}</p>
    </div>

    <div class="judul">Laporan Keuangan — {{ $label_periode }}</div>
    <p style="color:#6b7280;margin:0 0 10px">
        Periode {{ \Illuminate\Support\Carbon::parse($dari)->format('d/m/Y') }} s/d {{ \Illuminate\Support\Carbon::parse($sampai)->format('d/m/Y') }}
        · Dicetak {{ $dicetak->format('d/m/Y H:i') }}
    </p>

    <div class="kotak">
        <table>
            <tr><td>Total pemasukan</td><td class="kanan">Rp {{ number_format($ringkasan['total_pemasukan'], 0, ',', '.') }}</td></tr>
            <tr><td>Total pengeluaran</td><td class="kanan">Rp {{ number_format($ringkasan['total_pengeluaran'], 0, ',', '.') }}</td></tr>
            <tr><td><strong>Arus kas (masuk - keluar)</strong></td><td class="kanan"><strong>Rp {{ number_format($ringkasan['arus_kas'], 0, ',', '.') }}</strong></td></tr>
            <tr><td>Pendapatan jasa</td><td class="kanan">Rp {{ number_format($ringkasan['pendapatan_jasa'], 0, ',', '.') }}</td></tr>
            <tr><td>Penjualan sparepart</td><td class="kanan">Rp {{ number_format($ringkasan['pendapatan_sparepart'], 0, ',', '.') }}</td></tr>
            <tr><td>HPP sparepart terjual</td><td class="kanan">- Rp {{ number_format($ringkasan['hpp_sparepart'], 0, ',', '.') }}</td></tr>
            <tr><td><strong>Laba kotor</strong></td><td class="kanan"><strong>Rp {{ number_format($ringkasan['laba_kotor'], 0, ',', '.') }}</strong></td></tr>
            <tr><td>Jumlah SA dibayar</td><td class="kanan">{{ $ringkasan['jumlah_sa_dibayar'] }}</td></tr>
            <tr><td>Rata-rata nilai per SA</td><td class="kanan">Rp {{ number_format($ringkasan['rata_rata_per_sa'], 0, ',', '.') }}</td></tr>
        </table>
    </div>

    <h3>Rincian Pemasukan</h3>
    <table>
        <thead><tr><th>Kategori</th><th class="kanan">Jumlah Transaksi</th><th class="kanan">Total (Rp)</th></tr></thead>
        <tbody>
        @forelse ($pemasukan as $p)
            <tr><td>{{ $p['label'] }}</td><td class="kanan">{{ $p['jumlah'] }}</td><td class="kanan">{{ number_format($p['total'], 0, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="3" style="text-align:center;color:#9ca3af">Tidak ada pemasukan pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>Rincian Pengeluaran</h3>
    <table>
        <thead><tr><th>Kategori</th><th class="kanan">Jumlah Transaksi</th><th class="kanan">Total (Rp)</th></tr></thead>
        <tbody>
        @forelse ($pengeluaran as $p)
            <tr><td>{{ $p['label'] }}</td><td class="kanan">{{ $p['jumlah'] }}</td><td class="kanan">{{ number_format($p['total'], 0, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="3" style="text-align:center;color:#9ca3af">Tidak ada pengeluaran pada periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>Rekap Harian/Bulanan</h3>
    <table>
        <thead><tr><th>Periode</th><th class="kanan">Pemasukan</th><th class="kanan">Pengeluaran</th><th class="kanan">Selisih</th></tr></thead>
        <tbody>
        @foreach ($seri as $s)
            <tr>
                <td>{{ $s['label'] }}</td>
                <td class="kanan">{{ number_format($s['pemasukan'], 0, ',', '.') }}</td>
                <td class="kanan">{{ number_format($s['pengeluaran'], 0, ',', '.') }}</td>
                <td class="kanan">{{ number_format($s['pemasukan'] - $s['pengeluaran'], 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
