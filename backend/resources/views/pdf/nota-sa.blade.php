<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Form SA {{ $sa->sa_no }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111827; margin: 0; }
        .kop { border-bottom: 2px solid #dc2626; padding-bottom: 8px; margin-bottom: 12px; }
        .kop h1 { font-size: 18px; margin: 0; color: #dc2626; }
        .kop p { margin: 2px 0; color: #4b5563; }
        .judul { text-align: center; font-size: 14px; font-weight: bold; margin: 10px 0 4px; }
        .nomor { text-align: center; color: #4b5563; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 2px 0; vertical-align: top; }
        .info .label { width: 110px; color: #6b7280; }
        .kotak { border: 1px solid #e5e7eb; padding: 8px; margin-bottom: 10px; }
        .barang th { background: #f3f4f6; border: 1px solid #e5e7eb; padding: 5px; text-align: left; }
        .barang td { border: 1px solid #e5e7eb; padding: 5px; }
        .kanan { text-align: right; }
        .total td { font-weight: bold; }
        .ttd { margin-top: 28px; }
        .ttd table td { width: 50%; text-align: center; padding-top: 40px; }
        .kecil { color: #6b7280; font-size: 10px; }
        .bensin { letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="kop">
        <h1>{{ $bengkel['nama'] }}</h1>
        <p>{{ $bengkel['alamat'] }}</p>
        <p>{{ $bengkel['telepon'] }}</p>
    </div>

    <div class="judul">FORM SERVICE ADVISOR (SA)</div>
    <div class="nomor">No. {{ $sa->sa_no }} &middot; Dibuat {{ $sa->created_at?->format('d/m/Y H:i') }}</div>

    <div class="kotak">
        <table class="info">
            <tr>
                <td class="label">Pelanggan</td><td>: {{ $sa->customer_name }}</td>
                <td class="label">No. Polisi</td><td>: {{ $sa->plate_number }}</td>
            </tr>
            <tr>
                <td class="label">No. HP</td><td>: {{ $sa->phone ?: '-' }}</td>
                <td class="label">Kendaraan</td><td>: {{ $sa->vehicle_name ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Mekanik</td><td>: {{ $sa->mechanic?->name ?: '-' }}</td>
                <td class="label">Odometer</td><td>: {{ $sa->odometer ? number_format($sa->odometer, 0, ',', '.') . ' km' : '-' }}</td>
            </tr>
            <tr>
                <td class="label">Level bensin</td>
                <td colspan="3">: <span class="bensin">[{{ str_repeat('#', (int) $sa->fuel_level) }}{{ str_repeat('-', max(0, 8 - (int) $sa->fuel_level)) }}]</span>
                    {{ (int) $sa->fuel_level }} / 8
                    @if ($sa->is_member_at_entry) &middot; <strong>MEMBER</strong> (diskon {{ (int) $sa->member_discount_percent }}%) @endif
                </td>
            </tr>
            <tr>
                <td class="label">Keluhan</td><td colspan="3">: {{ $sa->complaint ?: '-' }}</td>
            </tr>
        </table>
    </div>

    @if ($sa->conditions->isNotEmpty())
    <div class="kotak">
        <strong>Kondisi Kendaraan</strong>
        <table class="barang" style="margin-top:6px">
            <thead><tr><th>Item</th><th>Kategori</th><th>Status</th><th>Catatan</th></tr></thead>
            <tbody>
            @foreach ($sa->conditions as $k)
                <tr>
                    <td>{{ $k->item_name }}</td>
                    <td>{{ $k->category }}</td>
                    <td>{{ str_replace('_', ' ', $k->status) }}</td>
                    <td>{{ $k->note }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <strong>Pekerjaan / Jasa</strong>
    <table class="barang" style="margin: 6px 0 10px">
        <thead><tr><th style="width:45%">Nama</th><th>Qty</th><th class="kanan">Harga</th><th class="kanan">Diskon</th><th class="kanan">Subtotal</th></tr></thead>
        <tbody>
        @forelse ($sa->services as $j)
            <tr>
                <td>{{ $j->name }}</td>
                <td>{{ (int) $j->qty }}</td>
                <td class="kanan">{{ number_format($j->price, 0, ',', '.') }}</td>
                <td class="kanan">{{ $j->discount_amount > 0 ? number_format($j->discount_amount, 0, ',', '.') . ' (' . (int) $j->discount_percent . '%)' : '-' }}</td>
                <td class="kanan">{{ number_format($j->subtotal, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;color:#9ca3af">Tidak ada pekerjaan.</td></tr>
        @endforelse
        </tbody>
    </table>

    <strong>Sparepart</strong>
    <table class="barang" style="margin: 6px 0 10px">
        <thead><tr><th style="width:55%">Nama</th><th>Qty</th><th class="kanan">Harga</th><th class="kanan">Subtotal</th></tr></thead>
        <tbody>
        @forelse ($sa->parts as $p)
            <tr>
                <td>{{ $p->name }}{{ $p->is_free_reward ? ' (hadiah poin)' : '' }}</td>
                <td>{{ (int) $p->qty }}</td>
                <td class="kanan">{{ number_format($p->sell_price, 0, ',', '.') }}</td>
                <td class="kanan">{{ number_format($p->subtotal, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align:center;color:#9ca3af">Tidak ada sparepart.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="barang total">
        <tr><td style="width:70%">Subtotal jasa</td><td class="kanan">{{ number_format($sa->subtotal_services, 0, ',', '.') }}</td></tr>
        <tr><td>Diskon member</td><td class="kanan">- {{ number_format($sa->discount_services, 0, ',', '.') }}</td></tr>
        <tr><td>Total jasa</td><td class="kanan">{{ number_format($sa->total_services, 0, ',', '.') }}</td></tr>
        <tr><td>Total sparepart</td><td class="kanan">{{ number_format($sa->total_parts, 0, ',', '.') }}</td></tr>
        <tr style="background:#fef2f2"><td>GRAND TOTAL</td><td class="kanan">Rp {{ number_format($sa->grand_total, 0, ',', '.') }}</td></tr>
        <tr><td>Status</td><td class="kanan">{{ $sa->status?->label() }} @if($sa->paid_at) &middot; {{ $sa->paid_at->format('d/m/Y H:i') }} @endif</td></tr>
    </table>

    <div class="kecil" style="margin-top:8px">
        Catatan: {{ $sa->notes ?: '-' }}
    </div>

    <div class="ttd">
        <table>
            <tr>
                <td>Pelanggan<br><span class="kecil">( persetujuan pelanggan )</span></td>
                <td>Service Advisor<br><span class="kecil">{{ $bengkel['nama'] }}</span></td>
            </tr>
        </table>
    </div>

    <p class="kecil" style="margin-top:10px">Dicetak {{ $dicetak->format('d/m/Y H:i') }}</p>
</body>
</html>