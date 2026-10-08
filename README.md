# Aplikasi Manajemen Service Bengkel

Monorepo: **backend** (Laravel 12 API) + **frontend** (Vue 3 + Vite SPA).
Tema: dasar **putih** dengan aksen **merah**. Bahasa antarmuka: Indonesia.

Blueprint lengkap: [`docs/BENGKEL-APP-BLUEPRINT.md`](docs/BENGKEL-APP-BLUEPRINT.md)
Aturan kerja agent: [`AGENT_RULES.md`](AGENT_RULES.md)
Deploy ke hosting cPanel (2 subdomain, 1 repo): [`docs/DEPLOY-CPANEL.md`](docs/DEPLOY-CPANEL.md)
Cetak nota printer thermal: [`docs/PRINTER-THERMAL.md`](docs/PRINTER-THERMAL.md)

## Fitur

| Modul | Keterangan |
|---|---|
| General Check Up | Form pemeriksaan dengan item dari template yang bisa diatur; hasil disalin sebagai snapshot |
| Pelanggan & Kendaraan | Satu pelanggan banyak kendaraan, nopol & nomor HP dinormalisasi |
| Sparepart & Stok | Master part, barang masuk, penyesuaian, kartu stok, peringatan stok menipis |
| Master Service | Daftar pekerjaan + diskon member per pekerjaan |
| Form SA | **Hanya mencatat pekerjaan**: keluhan, kondisi, level bensin, jasa, part, mekanik; status draft → dikerjakan → selesai (+ batal). Tidak ada pembayaran di sini |
| Kasir | Modul terpisah: daftar **tagihan** (SA berstatus selesai, urut FIFO), pembayaran (tunai/transfer/QRIS) dengan hitung kembalian, riwayat penerimaan + rekap per metode bayar, cetak nota/invoice |
| Unit Entry | Rekap semua kendaraan yang masuk (check up maupun service) |
| Membership & Poin | 6 bulan (diperpanjang tiap servis), poin 1 per Rp 10.000 jasa, tukar poin, akun portal |
| Keuangan | Arus kas **dan** laba kotor, mingguan/bulanan/tahunan/rentang, export Excel & PDF |
| Portal Member | Riwayat servis & check up milik sendiri, tanpa harga beli |
| Cetak | **Cetak thermal Bluetooth (ESC/POS)** langsung dari aplikasi — satu klik, tanpa dialog cetak (lihat [`docs/PRINTER-THERMAL.md`](docs/PRINTER-THERMAL.md)); **cetak 58mm lewat dialog peramban** sebagai cadangan; **cetak A4/PDF** untuk invoice resmi; plus **pratinjau PDF** yang terbuka di tab baru (bukan langsung terunduh) dan ekspor Excel |

## Menjalankan di lokal

Prasyarat: **PHP 8.3** (`C:\php83`), **Composer**, **Node 20+**, **MariaDB/MySQL** (XAMPP).

```bash
# 1) Database
mysql -u root -e "CREATE DATABASE bengkel_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE bengkel_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2) Backend
cd backend
composer install
cp .env.example .env          # lalu sesuaikan DB_* dan OWNER_*
C:\php83\php.exe artisan key:generate
C:\php83\php.exe artisan migrate --seed
C:\php83\php.exe artisan db:seed --class=DemoSeeder   # opsional: data contoh
C:\php83\php.exe artisan serve --host=127.0.0.1 --port=8000

# 3) Frontend (terminal lain)
cd frontend
npm install
cp .env.example .env          # VITE_API_URL=http://127.0.0.1:8000/api/v1
npm run dev                   # http://127.0.0.1:5173
```

Akun awal: **owner@bengkel.test / owner12345** (dari `.env`, wajib diganti di produksi).
Data contoh menyertakan 1 member: `081211110001` dengan password awal yang dicetak
saat seeder dijalankan (wajib diganti saat masuk pertama).

## Verifikasi

```bash
cd backend
C:\php83\php.exe artisan test          # 53 tes, 331 assertion
C:\php83\php.exe artisan route:list    # 95 rute
cd ../frontend && npm run build        # build produksi
```

## Struktur

```
upj/
├── AGENT_RULES.md              aturan kerja
├── CATATAN-KEPUTUSAN.md        asumsi & penyimpangan
├── BENGKEL-APP-BLUEPRINT.md    spesifikasi
├── docs/                       salinan blueprint + DEPLOY.md
├── backend/                    Laravel 12 API  (/api/v1)
│   ├── app/Enums, Http/{Controllers,Requests,Resources}, Models, Services
│   ├── database/{migrations,seeders}
│   ├── resources/views/pdf     template nota & laporan
│   └── tests/{Feature,Support}
└── frontend/                   Vue 3 + Vite + Pinia + Tailwind
    └── src/{api,components,composables,config,layouts,router,stores,utils,views}
```