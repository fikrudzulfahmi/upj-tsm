<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Stok</title>
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
        .ringkas { margin-top: 10px; }
        .ringkas td { border: none; padding: 2px 0; }
        .menipis { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $bengkel['nama'] }}</h1>
        <p>{{ $bengkel['alamat'] }} {{ $bengkel['telepon'] ? '· '.$bengkel['telepon'] : '' }}</p>
    </div>

    <div class="judul">Laporan Stok Sparepart</div>
    <p style="color:#6b7280;margin:0 0 10px">Dicetak {{ $dicetak->format('d/m/Y H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th style="width:24px">No</th>
                <th>Nama</th>
                <th>Satuan</th>
                <th class="kanan">Stok</th>
                <th class="kanan">Min</th>
                <th class="kanan">Harga Beli</th>
                <th class="kanan">Harga Jual</th>
                <th class="kanan">Nilai Persediaan</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($baris as $i => $b)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $b['name'] }}{{ $b['menipis'] ? ' *' : '' }}</td>
                <td>{{ $b['unit'] }}</td>
                <td class="kanan {{ $b['menipis'] ? 'menipis' : '' }}">{{ $b['stock'] }}</td>
                <td class="kanan">{{ $b['min_stock'] }}</td>
                <td class="kanan">{{ number_format($b['buy_price'], 0, ',', '.') }}</td>
                <td class="kanan">{{ number_format($b['sell_price'], 0, ',', '.') }}</td>
                <td class="kanan">{{ number_format($b['nilai_persediaan'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;color:#9ca3af">Belum ada data sparepart.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="ringkas">
        <tr><td>Jumlah item</td><td class="kanan">{{ $ringkasan['jumlah_item'] }}</td></tr>
        <tr><td>Item stok menipis</td><td class="kanan">{{ $ringkasan['jumlah_menipis'] }}</td></tr>
        <tr><td><strong>Total nilai persediaan</strong></td><td class="kanan"><strong>Rp {{ number_format($ringkasan['total_nilai_persediaan'], 0, ',', '.') }}</strong></td></tr>
    </table>

    <p style="color:#6b7280;margin-top:10px">* = stok sudah mencapai/melampaui batas minimum</p>
</body>
</html>
