# CATATAN KEPUTUSAN & PENYIMPANGAN

Dokumen ini mencatat asumsi, keputusan teknis, dan penyimpangan dari
`BENGKEL-APP-BLUEPRINT.md`, agar dapat ditinjau ulang oleh pemilik.

## A. Runtime & lingkungan

| # | Topik | Keputusan | Alasan |
|---|---|---|---|
| A1 | PHP | PHP **8.3.35** dari `C:\php83`, **tanpa** menyentuh `C:\xampp\php` (8.2) | XAMPP dipakai bersama proyek lain |
| A2 | Database lokal | MariaDB **10.4.32** (XAMPP) | Blueprint minta MySQL 8 / MariaDB 10.6+, tetapi versi lokal 10.4 → semua kolom `*_at` memakai `dateTime` (bukan `timestamp`) agar migrasi aman |
| A3 | Database uji | `bengkel_test` (MySQL), **bukan** SQLite | PHP di mesin ini tidak menyediakan `pdo_sqlite` |
| A4 | Timezone | `Asia/Jakarta` (env `APP_TIMEZONE`) | Sesuai blueprint D15 |

## B. Penyimpangan dari blueprint (disengaja)

| # | Bagian | Penyimpangan | Keterangan |
|---|---|---|---|
| B1 | 3 Tech Stack | Frontend memakai **JavaScript**, bukan TypeScript | Blueprint sendiri menulis `main.js`, `vite.config.js`, `src/views/*.vue` |
| B2 | 9 Endpoint | `GET /settings` boleh dibaca **semua pengguna yang login**; `PUT` tetap `setting.manage` | Form Check Up & Form SA butuh `fuel_bar_count`, diskon, dan jumlah poin. Menu Pengaturan tetap tertutup |
| B3 | 7.2 Poin | Poin dihitung dari `total_services` **setelah diskon member** | Rumus blueprint memang menyebut "setelah diskon" → contoh: jasa Rp 250.000 dengan diskon 10% = 22 poin, bukan 25 |
| B4 | 6.3 Membership | "Perpanjang manual" **menumpuk** dari tanggal berakhir yang masih berlaku; bila sudah hangus dihitung dari hari ini | Mencegah perpanjangan memotong sisa masa berlaku |
| B5 | 8.2 customers | `gender` dibuat **nullable** | Data pelanggan lama/bengkel sering belum mengisi; validasi tetap `L`/`P` bila diisi |
| B6 | 6.2 Alur SA | Tersedia juga aksi **Batalkan** pada status `draft`/`in_progress` | Tanpa ini, SA salah input tak bisa dibatalkan (hanya bisa diubah) |
| B7 | 8.6 Keuangan | Ditambah `service_reminder_days` dan `update_buy_price_on_purchase` pada tabel settings | Menampung ide fitur pengingat servis & kontrol harga beli master |
| B8 | 9 Endpoint | Ditambah `GET /expenses` (daftar) dan `GET /reports/unit-entries/export` | Halaman "Pengeluaran Lain" & tombol Export Excel pada laporan unit entry |
| B9 | 8.3 stok | `stock_movements.qty` selalu **positif**, arah ditentukan `type` + kolom `stock_before`/`stock_after` | Membuat kartu stok & audit lebih mudah dibaca (sesuai blueprint) |
| B10 | Reward | Part hadiah tukar poin ditandai `is_free_reward` di SA → **tidak** dipotong dua kali saat SA selesai | Stok sudah keluar saat penukaran poin (bagian 7.3) |
| B11 | PDF | Dompdf memakai `compress => true` + `isFontSubsettingEnabled => true` (lewat `setOption`) | Menjaga ukuran berkas laporan tetap kecil |
| B12 | Nama bengkel | Seeder memakai nilai **netral** "Bengkel Motor" dari `.env` (`SHOP_NAME`) | Aturan: repo/seed tidak boleh berisi nama klien |

## C. Isi default yang sudah dibekalkan

- **Peran**: `owner`, `admin`, `kasir`, `gudang`, `member` (peta permission di `RolePermissionSeeder::PETA`).
- **Template Check Up**: "General Check Up Motor" & "General Check Up Mobil", 6 kategori (Mesin, Rem, Kelistrikan, Ban & Kaki-kaki, Cairan, Body), 30+ item.
- **Reward**: "Oli Gratis" 100 poin → part yang mengandung kata "oli".
- **Data contoh lokal** (`DemoSeeder`, **jangan** di production): 3 mekanik, 8 jasa, 12 sparepart, 5 pelanggan + kendaraan, 3 alur servis, 1 member aktif, 1 pengeluaran operasional.

## D. Catatan operasional

- Login memakai **email ATAU nomor HP**; nomor dinormalisasi (`0812…`, `62812…`, `+62 812…` → `0812…`).
- Akun owner dibuat dari `OWNER_EMAIL`/`OWNER_PASSWORD` di `.env` (tidak di-hardcode).
- Password awal akun portal member **hanya ditampilkan sekali** (di modal, dengan tombol salin) dan `must_change_password = true`.
- Perintah terjadwal `memberships:expire` berjalan tiap hari 00:05 (dijadwalkan di `routes/console.php`).
- Semua operasi stok/keuangan/poin berada di dalam `DB::transaction`; stok memakai `lockForUpdate()`.

## E. Perubahan atas permintaan pemilik (revisi 2)

| # | Permintaan | Yang dikerjakan |
|---|---|---|
| E1 | Form SA **hanya menyimpan pekerjaan**; kasir dipisah | Form SA berhenti di status `selesai` — tombol & modal **Bayar dihapus** dari halaman Form SA, diganti penanda "Pembayaran di menu Kasir". Modul **Kasir** baru: `GET /kasir/tagihan` (hanya SA berstatus selesai, urut FIFO), `GET /kasir/ringkasan`, `GET /kasir/transaksi` (riwayat + rekap per metode bayar). Izin `service-order.pay` diganti `kasir.manage` (owner/admin/kasir); seeder kini juga **menghapus permission yang sudah tidak dipakai** |
| E2 | Nota/invoice dicetak atau masuk kasir saat pekerjaan **selesai** | Kasir menampilkan tagihan yang selesai; dari situ bisa **Bayar**, **Cetak Nota (langsung)**, atau **Pratinjau PDF**. Riwayat transaksi kasir menyediakan cetak ulang nota |
| E3 | Selain PDF, ada **cetak langsung** | Tiga halaman cetak HTML tanpa layout admin yang otomatis memunculkan dialog cetak peramban: `/cetak/nota/:id` (nota/invoice Form SA), `/cetak/laporan-stok`, `/cetak/laporan-keuangan`. Tambahkan `?cetak=0` pada URL untuk melihat dulu tanpa mencetak |
| E4 | PDF **jangan langsung terunduh**, tampil di tab baru | Semua endpoint PDF (`/service-orders/{id}/print`, `/reports/stock/pdf`, `/reports/finance/export?format=pdf`) kini memakai `stream(..., ['Attachment' => false])` → `Content-Disposition: inline`. Frontend mengambilnya sebagai blob lalu membukanya di **tab baru** (`pratinjau()`); bila popup diblokir, otomatis jatuh ke unduhan agar tidak gagal diam-diam. Ekspor **Excel tetap terunduh** (memang berkas) |
| E5 | Pengaman regresi | `KasirTest` (8 tes) mengunci: tagihan hanya SA selesai, SA belum selesai tak bisa dibayar, rekap per metode, 403 tanpa izin kasir, dan **header `inline`** pada nota PDF |

## F. Cetak nota ke printer thermal Bluetooth (revisi 3)

| # | Permintaan | Yang dikerjakan |
|---|---|---|
| F1 | Nota dicetak ke printer thermal Bluetooth **CodeSoft HP-M200** dari aplikasi web | Jalur **Web Bluetooth (BLE)** + encoder **ESC/POS** sendiri: `src/utils/escpos.js`, `src/utils/notaThermal.js`, `src/composables/usePrinterThermal.js`. Kasir memilih printer sekali, setelah itu tombol **Cetak Thermal** mencetak satu klik (tanpa PDF, tanpa dialog cetak) |
| F2 | Pratinjau hasil sebelum produksi | Tersedia **Cetak Uji** (slip uji + potong kertas) dan penampil **Diagnostik GATT** (menampilkan service & karakteristik yang ditemukan printer) supaya kegagalan bisa didiagnosis dari jarak jauh |
| F3 | Cadangan bila perangkat tidak mendukung Web Bluetooth | Ditambahkan halaman **`/cetak/nota-thermal/:id`** — nota 58mm (32 kolom, monospace, `@page size: 58mm auto`) yang dicetak lewat dialog peramban. Berguna bila perangkat kasir memakai Safari/iPad |
| F4 | Cetak otomatis di kasir | Pada modal pembayaran Kasir ada opsi **"Cetak nota thermal setelah pembayaran"** (muncul bila printer tersambung), sehingga alur kasir tetap satu langkah |
| F5 | Perangkat kasir | Diputuskan memakai **Android + Chrome/Edge** (Web Bluetooth). Syarat mutlak: HTTPS + Chrome/Edge + printer BLE. iOS/Safari tidak didukung sama sekali — tidak ada jalan keluarnya di sisi web |

### Batasan yang harus diketahui pemilik

- Cetak thermal **hanya** dari peramban Chrome/Edge; halaman harus tetap terbuka saat mencetak.
- Hasil cetak fisik belum diuji pengembang (tidak ada perangkat). Yang sudah terbukti adalah
  byte ESC/POS yang benar, lebar baris maksimal 32 kolom, dan pengiriman byte sampai ke
  "printer tiruan" lewat GATT.
- Printer yang hanya Bluetooth Classic (SPP) tidak akan muncul pada daftar Web Bluetooth.
- Bila printer memakai profil GATT yang belum dikenal, kirimkan tabel Diagnostik GATT.

## G. Perbaikan tampilan (revisi 4)

| # | Masalah yang dilaporkan | Penyebab | Perbaikan |
|---|---|---|---|
| G1 | Menu sidebar aktif bersamaan / dobel | `aktif()` memakai **awalan path**: `route.path.startsWith('/kasir/')` membuat menu "Tagihan Siap Bayar" ikut menyala di halaman "Transaksi Kasir"; dua menu ber-path sama dengan query berbeda (`/spareparts` vs `/spareparts?menipis=1`) menyala bersamaan karena query tidak dihitung | Cacat dipindah ke `utils/menuAktif.js`: menu diidentifikasi lewat **nama rute** (`route.name`), dengan pembeda `query`/`tanpaQuery`. Halaman detail/ubah tetap menyalakan menu induknya (mis. `sa-detail` → "Form SA"). Berlaku juga untuk navigasi Portal Member (detail riwayat tetap menyorot "Riwayat") |
| G2 | Judul nota "Estimasi Biaya" | Pemisahan judul nota dibayar/belum dibayar | Judul kini **NOTA** (belum dibayar) dan **NOTA / INVOICE** (sudah dibayar). Kalimat "estimasi biaya" di badan nota dihapus pada nota A4, nota 58mm, dan nota thermal; label tanda tangan PDF jadi "( persetujuan pelanggan )"; label ringkasan Form SA "Estimasi total" → "Total SA" |
| G3 | (temuan tambahan) Halaman **Pengeluaran Lain** tidak punya menu | Rute `/expenses` ada dan berfungsi, tetapi tidak terdaftar di sidebar sehingga hanya bisa dibuka dengan mengetik alamatnya | Ditambahkan ke grup menu **Laporan** sebagai "Pengeluaran Lain" (izin `report.finance`) |

## H. Kemudahan menemukan aksi "Jadikan Member" (revisi 5)

| # | Masalah | Perbaikan |
|---|---|---|
| H1 | Tombol **Jadikan Member** hanya ada di dalam tab "Membership & Poin" pada halaman detail pelanggan, sehingga sulit ditemukan (tab awal yang terbuka adalah "Info & Kendaraan") | Tombol ditambahkan di **kepala halaman detail pelanggan**, muncul hanya bila pelanggan belum member atau membershipnya hangus. Untuk member aktif tombol disembunyikan (aksi yang relevan sudah ada di tab: Perpanjang / Tukar Poin) |
| H2 | Menambah member dari daftar pelanggan memerlukan membuka detail satu per satu | Aksi cepat **ikon piala** pada baris daftar pelanggan (kolom Membership), hanya muncul untuk pelanggan yang belum member / hangus |
| H3 | Modal kredensial akun portal hanya ada di halaman detail (kode ganda bila dipakai di dua tempat) | Diekstrak menjadi komponen bersama **`ModalAkunMember.vue`**, dipakai halaman detail & daftar. Menangani tiga kemungkinan: akun baru (password tampil sekali), aktivasi ulang member hangus (akun lama tetap berlaku), dan pelanggan tanpa No. HP (akun portal belum dibuat) |
| H4 | Kegagalan aktivasi (mis. No. HP kosong, sudah member aktif) **tidak menampilkan pesan apa pun** — `jadikanMember()` memakai `try/finally` tanpa `catch` | Ditambahkan penanganan galat (`ui.gagal(pesanGalat(e))`) di kedua tempat. Aturan tampil/label tombol dipusatkan di `aksiMember()` (`utils/status.js`): belum member → "Jadikan Member", hangus → "Aktifkan Kembali" |
