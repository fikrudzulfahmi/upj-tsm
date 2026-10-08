# Blueprint Aplikasi Manajemen Service Bengkel

> Dokumen ini berisi **konsep, struktur database, API, struktur folder, dan perintah kerja bertahap**
> untuk dikerjakan oleh **Hermes Agent (model DeepSeek Flash)**.
> Stack: **Laravel 12 (PHP 8.3)** + **Vue 3 (Vite)**. Target: langsung live production.

---

## DAFTAR ISI

1. [Ringkasan & Tujuan](#1-ringkasan--tujuan)
2. [Keputusan Desain & Asumsi (WAJIB DIKONFIRMASI)](#2-keputusan-desain--asumsi)
3. [Tech Stack & Library](#3-tech-stack--library)
4. [Aturan Kerja untuk Agent](#4-aturan-kerja-untuk-agent-hermes--deepseek-flash)
5. [Role & Hak Akses](#5-role--hak-akses)
6. [Alur Bisnis Utama](#6-alur-bisnis-utama)
7. [Aturan Bisnis Detail](#7-aturan-bisnis-detail)
8. [Desain Database](#8-desain-database)
9. [Daftar API Endpoint](#9-daftar-api-endpoint)
10. [Struktur Folder](#10-struktur-folder)
11. [Daftar Halaman Frontend](#11-daftar-halaman-frontend)
12. [Perintah Kerja Bertahap (Prompt Siap Pakai)](#12-perintah-kerja-bertahap)
13. [Saran Fitur Tambahan](#13-saran-fitur-tambahan)
14. [Checklist Go-Live Production](#14-checklist-go-live-production)

---

## 1. Ringkasan & Tujuan

Aplikasi web untuk mengelola operasional bengkel:

| Modul | Fungsi |
|---|---|
| General Check Up | Layanan gratis, form pemeriksaan dengan item yang bisa diatur |
| Pelanggan & Kendaraan | Data pelanggan, banyak kendaraan, membership, poin |
| Sparepart & Stok | Master part, stok masuk/keluar, laporan stok, terhubung ke keuangan |
| Master Service | Daftar pekerjaan, harga, diskon member |
| Mekanik | Data mekanik |
| Form SA (Service Advisor) | Pencatatan servis: kondisi, keluhan, part, pekerjaan, estimasi, biaya |
| Unit Entry | Rekap seluruh kendaraan yang masuk (check up maupun servis) |
| Keuangan | Laporan mingguan, bulanan, tahunan |
| Portal Member | Pelanggan member melihat riwayat hasil servis/check up |

---

## 2. Keputusan Desain & Asumsi

Bagian ini penting. Agent **tidak boleh mengubah** keputusan ini tanpa persetujuan Anda.
Silakan ubah nilai di bawah **sebelum** memulai pekerjaan jika tidak sesuai.

| # | Topik | Keputusan Default | Bisa Diubah di Setting? |
|---|---|---|---|
| D1 | Jenis kendaraan | Mendukung **motor & mobil** (field `type`) | Ya |
| D2 | Masa berlaku member | **6 bulan**, dihitung dari **tanggal servis terakhir yang selesai** (setiap servis selesai, masa berlaku diperpanjang 6 bulan lagi) | Ya (jumlah bulan) |
| D3 | Check Up sendiri (tanpa lanjut servis) | **Tidak** memperpanjang member & **tidak** menambah poin (karena gratis). Hanya servis berbayar yang dihitung | Ya (toggle) |
| D4 | Diskon member | **10%** dari **biaya jasa/pekerjaan saja**, **tidak** untuk sparepart | Ya (persen global) + override per service |
| D5 | Cara jadi member | **Dibuat manual oleh kasir/admin** (tombol "Jadikan Member") di data pelanggan. Opsional: otomatis setelah servis pertama (toggle) | Ya |
| D6 | Poin | **1 poin per Rp 10.000 biaya jasa** (hanya jasa, tidak termasuk sparepart). Hanya untuk member | Ya (nominal per poin) |
| D7 | Tukar poin | Tabel **reward**: misal 100 poin = 1 oli gratis. Reward mengurangi stok oli & dicatat sebagai biaya promosi | Ya |
| D8 | Member hangus | Status berubah ke `expired`. Poin **ikut hangus (di-nol-kan)** | Ya (toggle poin ikut hangus / tidak) |
| D9 | Pengurangan stok | Stok berkurang saat SA diubah ke status **Selesai** | Tidak |
| D10 | Pencatatan pemasukan | Pemasukan tercatat saat SA **Dibayar** (lunas) | Tidak |
| D11 | Pencatatan pengeluaran part | Tercatat saat **Input Sparepart (barang masuk)** = pembelian | Tidak |
| D12 | Level bensin | **8 bar** (0–8), tampil visual bar | Ya (jumlah bar) |
| D13 | Login member | **No. HP + password**. Akun dibuat otomatis saat jadi member, password awal acak ditampilkan sekali ke kasir untuk diberikan ke pelanggan, pelanggan wajib ganti | - |
| D14 | Mata uang | Rupiah, disimpan sebagai **integer** (tanpa desimal) | - |
| D15 | Zona waktu | `Asia/Jakarta` | - |
| D16 | Autentikasi | **Laravel Sanctum token (Bearer)** untuk SPA | - |
| D17 | Database | **MySQL 8 / MariaDB 10.6+** | - |

> **Catatan laporan keuangan:** karena pembelian part dicatat sebagai pengeluaran dan penjualan part
> dicatat sebagai pemasukan, laporan arus kas bisa terlihat "rugi" saat bulan belanja stok besar.
> Maka laporan wajib menyediakan **dua tampilan**: (1) **Arus Kas** (uang masuk – uang keluar) dan
> (2) **Laba Kotor** (pendapatan – HPP part yang terjual).

---

## 3. Tech Stack & Library

### Backend
- Laravel 12, PHP 8.3, MySQL
- `laravel/sanctum` – autentikasi API
- `spatie/laravel-permission` – role & permission
- `barryvdh/laravel-dompdf` – cetak PDF (laporan stok, nota, laporan)
- `maatwebsite/excel` – export Excel laporan
- `spatie/laravel-activitylog` – audit log (opsional tapi disarankan)
- Pest / PHPUnit – testing
- Laravel Scheduler – cek member hangus harian

### Frontend
- Vue 3 (Composition API, `<script setup>`), Vite
- Vue Router, Pinia
- Axios
- Tailwind CSS
- VeeValidate atau validasi manual sederhana
- Chart.js (+ `vue-chartjs`) untuk grafik laporan
- `dayjs` untuk tanggal

---

## 4. Aturan Kerja untuk Agent (Hermes + DeepSeek Flash)

Model Flash cepat tetapi mudah kehilangan konteks pada tugas besar. Maka:

1. **Satu prompt = satu tugas kecil.** Jangan minta "buat semua modul".
2. **Selalu mulai prompt dengan konteks singkat** (lihat file `AGENT_RULES.md` di bawah).
3. **Satu fitur = satu branch/commit.** Commit setelah test lulus.
4. **Wajib ada test** (minimal feature test untuk aturan bisnis kritis: stok, diskon, poin, keuangan).
5. **Jangan refaktor file yang tidak diminta.**
6. **Jangan menebak nama tabel/kolom.** Ikuti bagian [Desain Database](#8-desain-database).
7. Setelah tiap tugas, agent harus menjalankan: `php artisan test` dan `npm run build` (jika menyentuh frontend), lalu melaporkan hasilnya.
8. Jika ada keraguan, agent **bertanya**, bukan berasumsi.

### 4.1 File konteks wajib: `AGENT_RULES.md` (taruh di root repo)

Buat file ini di awal (Fase 0) dan minta agent membacanya setiap sesi baru.

```markdown
# AGENT_RULES — Aplikasi Manajemen Service Bengkel

## Stack
- Backend: Laravel 12, PHP 8.3, MySQL, Sanctum (Bearer token), spatie/laravel-permission
- Frontend: Vue 3 + Vite + Pinia + Vue Router + Tailwind + Axios
- Struktur: monorepo → /backend (Laravel API) dan /frontend (Vue SPA)

## Aturan Kode
- Semua uang = integer Rupiah (unsignedBigInteger). DILARANG float/decimal untuk uang.
- Semua endpoint di prefix /api/v1, response JSON konsisten:
  { "success": true|false, "message": "...", "data": ..., "meta": ... }
- Gunakan FormRequest untuk validasi, API Resource untuk output.
- Logika bisnis ditaruh di Service class (app/Services), BUKAN di Controller.
- Operasi yang mengubah stok/keuangan/poin WAJIB di dalam DB::transaction
  dan stok memakai lockForUpdate().
- Gunakan SoftDeletes untuk: customers, vehicles, spareparts, services, mechanics.
- Penomoran dokumen: CU-YYYYMM-0001 (check up), SA-YYYYMM-0001 (service),
  UE-YYYYMM-0001 (unit entry). Dibuat lewat DocumentNumberService.
- Harga/diskon pada transaksi disimpan sebagai SNAPSHOT (salinan nilai saat transaksi),
  jangan ambil ulang dari master saat laporan.
- Timezone Asia/Jakarta. Bahasa UI: Indonesia.
- Nilai yang bisa diatur (persen diskon, durasi member, poin) disimpan di tabel settings.

## Aturan Proses
- Kerjakan HANYA tugas yang diminta. Jangan ubah file lain.
- Jangan ubah skema database tanpa membuat migration baru.
- Jalankan `php artisan test` sebelum menyatakan selesai.
- Jika ragu, tanya dulu.
- Rujukan lengkap: /docs/BENGKEL-APP-BLUEPRINT.md
```

> Simpan dokumen ini juga di repo sebagai `/docs/BENGKEL-APP-BLUEPRINT.md` agar agent bisa merujuknya.

---

## 5. Role & Hak Akses

| Role | Deskripsi | Akses |
|---|---|---|
| `owner` | Pemilik | Semua, termasuk laporan keuangan & setting |
| `admin` | Admin bengkel | Semua kecuali beberapa setting sensitif (opsional) |
| `kasir` | Kasir / Service Advisor | Pelanggan, Check Up, SA, pembayaran, lihat stok |
| `gudang` | Staf gudang (opsional) | Sparepart, input stok, laporan stok |
| `member` | Pelanggan member | **Hanya portal member**: lihat riwayat miliknya sendiri |

Mekanik **bukan akun login** (hanya data master) pada versi awal. Bisa dikembangkan nanti.

Permission contoh: `customer.manage`, `checkup.manage`, `service-order.manage`, `service-order.pay`,
`sparepart.manage`, `stock.input`, `report.finance`, `report.stock`, `setting.manage`, `user.manage`.

---

## 6. Alur Bisnis Utama

### 6.1 Alur General Check Up → (opsional) Service

```
Pelanggan datang
   │
   ▼
[Form General Check Up]
   ├─ Pilih pelanggan (cari nama/HP/nopol)
   │     └─ Belum ada? → tambah pelanggan + kendaraan langsung di form (modal)
   ├─ Pilih kendaraan
   ├─ Isi keluhan konsumen
   ├─ Isi hasil pemeriksaan (item dari template) : OK / Perlu Perhatian / Rusak / Tidak Diperiksa + catatan
   └─ Simpan → otomatis tercatat di UNIT ENTRY
   │
   ▼
[Pilih Finish]
   ├─ "Hanya Check Up" → status checkup = completed (selesai, tidak ada servis)
   └─ "Lanjut Service" → otomatis buka FORM SA dengan data TERISI dari check up
                          (pelanggan, kendaraan, kondisi, keluhan)
                          Unit Entry yang SAMA di-upgrade menjadi tipe "service"
```

### 6.2 Alur Form SA (Service)

```
Form SA (baru / lanjutan check up)
   ├─ Pelanggan (bisa tambah baru langsung) → Nopol, No HP, Kendaraan otomatis terisi
   ├─ Kondisi kendaraan (otomatis dari check up bila lanjutan)
   ├─ Level bensin (bar)
   ├─ Keluhan konsumen
   ├─ Mekanik pengerja
   ├─ Daftar pekerjaan  (dari master service)  → diskon member otomatis bila member aktif
   ├─ Daftar part diganti (dari master sparepart, cek stok)
   ├─ Estimasi biaya (otomatis hitung)
   │
   ▼
Status: draft → dikerjakan → selesai → dibayar
                              │            │
                         stok keluar   pemasukan tercatat
                                       poin & masa member diperbarui
```

### 6.3 Alur Membership

```
Jadikan Member ──► status active, expires_at = hari ini + 6 bulan
                   Akun portal dibuat otomatis (login = No HP)
Servis selesai ──► expires_at diperpanjang (tanggal selesai + 6 bulan), poin bertambah
Scheduler harian ► member yang expires_at < hari ini → status expired, poin di-nol-kan
Tukar poin ──────► pilih reward → poin berkurang → oli gratis dicatat di SA/transaksi
```

---

## 7. Aturan Bisnis Detail

### 7.1 Diskon Member
- Diskon hanya pada **item jasa** (`service_order_services`).
- Persen diambil dari `services.member_discount_percent` jika diisi, jika kosong pakai `settings.member_discount_percent` (default 10).
- Diskon hanya berlaku bila `membership.status = active` **pada tanggal SA dibuat**.
- Disimpan sebagai snapshot: `price`, `discount_percent`, `discount_amount`, `subtotal`.
- Rumus per item: `discount_amount = round(price × qty × discount_percent / 100)`.
- **Sparepart tidak pernah didiskon.**

### 7.2 Poin
- Poin dihitung saat SA berstatus **dibayar**.
- `points = floor(total_jasa_setelah_diskon / settings.points_per_amount)`; default `points_per_amount = 10000`.
- Hanya untuk member aktif. Semua perubahan poin dicatat di `point_transactions` (ledger, tidak boleh edit saldo langsung).
- Saldo poin = jumlah `point_transactions.points` (earn positif, redeem/expire negatif). Disimpan juga di `memberships.points_balance` untuk performa, diperbarui di transaksi DB yang sama.

### 7.3 Stok & Keuangan Sparepart
| Aksi | Efek stok | Efek keuangan |
|---|---|---|
| **Input sparepart** (barang masuk/pembelian) | stok **+** | `financial_transactions` type **expense**, kategori `pembelian_sparepart`, jumlah = qty × harga beli |
| **Output sparepart** (dipakai di SA selesai) | stok **−** | pemasukan dicatat saat SA **dibayar**: kategori `penjualan_sparepart` (qty × harga jual) |
| **Penyesuaian stok** (stock opname) | stok ± | Tidak ada keuangan (hanya log, wajib alasan) |
| **Tukar poin oli gratis** | stok **−** | Tidak ada pemasukan; dicatat biaya `promosi_poin` sebesar HPP (opsional, aktifkan via setting) |

- Semua perubahan stok dicatat di `stock_movements` (ledger). Stok tidak boleh minus (validasi + `lockForUpdate`).
- Harga beli & jual pada transaksi disimpan sebagai snapshot (agar laba kotor akurat walau harga master berubah).
- Jika SA **dibatalkan setelah selesai**, stok dikembalikan (movement tipe `return`) dan transaksi keuangan dibalik (reversal), bukan dihapus.

### 7.4 Keuangan
Pemasukan dari SA dibayar dipecah 2 baris `financial_transactions`:
1. `pendapatan_jasa` = total jasa setelah diskon
2. `penjualan_sparepart` = total part

Laporan:
- **Arus kas**: Σ income − Σ expense per periode.
- **Laba kotor**: (pendapatan jasa + penjualan part) − HPP part terjual (Σ qty × harga beli snapshot).
- Periode: **mingguan** (Senin–Minggu), **bulanan**, **tahunan**. Bisa dipilih periode lain lewat filter tanggal.

### 7.5 Check Up
- Item pemeriksaan diatur di **template** (admin bisa tambah/ubah/nonaktifkan/urutkan item, dikelompokkan per kategori, misal: Mesin, Rem, Kelistrikan, Ban, Cairan).
- Saat check up disimpan, nama item **disalin** (snapshot) ke hasil agar perubahan template tidak merusak riwayat.
- Status hasil per item: `ok`, `perlu_perhatian`, `rusak`, `tidak_diperiksa`.
- Template berbeda per jenis kendaraan (motor/mobil) — kolom `vehicle_type` (null = semua).

### 7.6 Unit Entry
- **Satu kunjungan = satu unit entry.** Dibuat otomatis saat Check Up dibuat **atau** SA dibuat langsung.
- Jika Check Up dilanjut ke Service, unit entry yang sama memiliki `service_order_id` dan `type` berubah menjadi `service` (flag `has_checkup = true`).
- Tipe: `checkup_only`, `service`.

---

## 8. Desain Database

> Semua tabel: `id` (bigint PK), `created_at`, `updated_at`. Tabel bertanda 🗑 memakai `deleted_at` (SoftDeletes).
> Kolom uang: `unsignedBigInteger`. Gunakan foreign key + index pada kolom relasi dan tanggal.

### 8.1 Pengguna & Setting

**users**
`name`, `email` (nullable, unique), `phone` (nullable, unique), `password`, `is_active` (bool), `must_change_password` (bool), `customer_id` (nullable FK → customers; terisi untuk role member), `remember_token`
> Role dikelola spatie (`roles`, `model_has_roles`, dst).

**settings**
`key` (unique), `value` (text), `type` (string|int|bool|json), `group`, `description`

Key default (seeder):
| key | default |
|---|---|
| `shop_name`, `shop_address`, `shop_phone` | (isi bengkel) |
| `membership_duration_months` | 6 |
| `member_discount_percent` | 10 |
| `points_per_amount` | 10000 |
| `member_points_expire_with_membership` | true |
| `checkup_extends_membership` | false |
| `auto_member_after_first_service` | false |
| `fuel_bar_count` | 8 |
| `record_redeem_cost` | true |

### 8.2 Pelanggan & Kendaraan

**customers** 🗑
`code` (unique, mis. C-000001), `name`, `gender` (enum: L, P), `phone` (indexed), `address` (text, nullable), `notes` (nullable)

**vehicles** 🗑
`customer_id` (FK), `plate_number` (unique, disimpan uppercase tanpa spasi berlebih), `type` (motor|mobil), `brand`, `model`, `year` (nullable), `color` (nullable), `engine_number` (nullable), `frame_number` (nullable), `last_odometer` (nullable)

**memberships**
`customer_id` (FK, unique – satu pelanggan satu record membership), `member_no` (unique), `status` (active|expired), `started_at`, `expires_at`, `last_service_at` (nullable), `points_balance` (int default 0), `discount_percent_override` (nullable)

**point_transactions**
`customer_id` (FK), `membership_id` (FK), `type` (earn|redeem|expire|adjust), `points` (int, bisa negatif), `balance_after` (int), `service_order_id` (nullable FK), `reward_id` (nullable FK), `description`

**rewards**
`name`, `points_required` (int), `sparepart_id` (nullable FK – part yang diberikan, mis. oli), `qty` (int default 1), `is_active`

**reward_redemptions**
`customer_id`, `reward_id`, `points_used`, `service_order_id` (nullable), `sparepart_id` (nullable), `qty`, `cost_amount` (HPP snapshot), `redeemed_by` (FK users), `redeemed_at`

### 8.3 Master Data

**mechanics** 🗑
`name`, `phone` (nullable), `is_active`

**services** 🗑 (master pekerjaan)
`name`, `price` (uint), `member_discount_percent` (nullable; null = pakai global), `is_active`, `description` (nullable)

**spareparts** 🗑
`sku` (unique, nullable), `name`, `unit` (pcs, liter, dll), `buy_price` (uint), `sell_price` (uint), `stock` (int, default 0), `min_stock` (int default 0), `is_active`

**stock_movements** (ledger stok)
`sparepart_id` (FK), `type` (in|out|adjust|return), `qty` (int, positif; arah ditentukan `type`), `stock_before`, `stock_after`, `buy_price` (snapshot), `sell_price` (snapshot), `reference_type`, `reference_id` (polymorphic: purchase / service_order / reward_redemption / opname), `notes`, `created_by` (FK users)

**part_purchases** (input sparepart / barang masuk)
`purchase_no`, `purchase_date`, `supplier_name` (nullable, versi awal teks bebas), `total_amount`, `notes`, `created_by`

**part_purchase_items**
`part_purchase_id`, `sparepart_id`, `qty`, `buy_price`, `subtotal`
> Saat purchase disimpan: tambah stok, update `spareparts.buy_price` ke harga terbaru (opsional via toggle), buat `stock_movements` (type `in`) dan `financial_transactions` (expense).

### 8.4 Check Up

**checkup_templates**
`name`, `vehicle_type` (nullable), `is_active`

**checkup_template_items**
`checkup_template_id` (FK), `category`, `name`, `sort_order`, `is_active`

**checkups**
`checkup_no` (unique), `unit_entry_id` (FK), `customer_id`, `vehicle_id`, `checkup_template_id` (nullable), `checkup_date`, `odometer` (nullable), `complaint` (text), `general_notes` (text, nullable), `result` (checkup_only|continue_service|null saat draft), `status` (draft|completed), `created_by`

**checkup_results**
`checkup_id` (FK), `category` (snapshot), `item_name` (snapshot), `status` (ok|perlu_perhatian|rusak|tidak_diperiksa), `note` (nullable), `sort_order`

### 8.5 Unit Entry & Service Order (Form SA)

**unit_entries**
`entry_no` (unique), `entry_date` (date), `customer_id`, `vehicle_id`, `type` (checkup_only|service), `has_checkup` (bool), `checkup_id` (nullable), `service_order_id` (nullable), `created_by`

**service_orders**
`sa_no` (unique), `unit_entry_id` (FK), `checkup_id` (nullable FK), `customer_id`, `vehicle_id`, `customer_name` (snapshot), `plate_number` (snapshot), `phone` (snapshot), `vehicle_name` (snapshot), `mechanic_id` (FK), `odometer` (nullable), `fuel_level` (tinyint 0..8), `complaint` (text), `vehicle_condition_notes` (text, nullable), `is_member_at_entry` (bool), `member_discount_percent` (snapshot), `subtotal_services`, `discount_services`, `total_services`, `total_parts`, `grand_total`, `estimate_total` (uint), `status` (draft|in_progress|finished|paid|cancelled), `finished_at`, `paid_at`, `payment_method` (cash|transfer|qris, nullable), `paid_amount`, `notes`, `created_by`

**service_order_conditions** (kondisi kendaraan; salinan dari checkup bila lanjutan)
`service_order_id`, `category`, `item_name`, `status`, `note`, `sort_order`

**service_order_services**
`service_order_id`, `service_id` (nullable FK), `name` (snapshot), `price` (snapshot), `qty` (int default 1), `discount_percent`, `discount_amount`, `subtotal`

**service_order_parts**
`service_order_id`, `sparepart_id`, `name` (snapshot), `qty`, `buy_price` (snapshot), `sell_price` (snapshot), `subtotal`, `is_free_reward` (bool default false)

### 8.6 Keuangan

**financial_transactions**
`transaction_date` (date, indexed), `type` (income|expense), `category` (pendapatan_jasa | penjualan_sparepart | pembelian_sparepart | operasional | promosi_poin | lainnya), `amount` (uint), `reference_type`, `reference_id`, `description`, `reversal_of` (nullable FK self), `created_by`

> Pengeluaran operasional lain (listrik, gaji, dll.) bisa diinput manual lewat menu **Pengeluaran Lain** (kategori `operasional`).

### 8.7 Ringkasan Relasi
```
customers 1─* vehicles
customers 1─1 memberships 1─* point_transactions
customers 1─* unit_entries 1─0..1 checkups , 0..1 service_orders
service_orders 1─* service_order_services / service_order_parts / service_order_conditions
checkups 1─* checkup_results
spareparts 1─* stock_movements
```

---

## 9. Daftar API Endpoint

Prefix `/api/v1`. Auth: `Authorization: Bearer <token>`.

### Auth
| Method | URL | Keterangan |
|---|---|---|
| POST | `/auth/login` | Login (email/HP + password) |
| POST | `/auth/logout` | Logout |
| GET | `/auth/me` | Profil + role + permission |
| POST | `/auth/change-password` | Ganti password |

### Setting & User (owner/admin)
| GET/PUT | `/settings` | Baca/ubah setting |
| CRUD | `/users` | Kelola user & role |

### Pelanggan
| Method | URL | Keterangan |
|---|---|---|
| GET | `/customers?search=` | Cari nama/HP/nopol, paginasi |
| POST | `/customers` | Tambah (boleh sekaligus `vehicles[]`) |
| GET/PUT/DELETE | `/customers/{id}` | Detail, ubah, hapus |
| POST | `/customers/{id}/vehicles` | Tambah kendaraan |
| PUT/DELETE | `/vehicles/{id}` | Ubah/hapus kendaraan |
| POST | `/customers/{id}/membership` | Jadikan member |
| POST | `/customers/{id}/membership/renew` | Perpanjang manual |
| GET | `/customers/{id}/points` | Riwayat poin |
| POST | `/customers/{id}/redeem` | Tukar poin (body: `reward_id`) |
| GET | `/customers/{id}/history` | Riwayat check up & servis |

### Master
| CRUD | `/mechanics`, `/services`, `/spareparts`, `/rewards` |
| CRUD | `/checkup-templates` (+ `/checkup-templates/{id}/items`, reorder) |

### Sparepart & Stok
| POST | `/part-purchases` | Input sparepart (barang masuk) |
| GET | `/part-purchases` | Riwayat pembelian |
| POST | `/stock-adjustments` | Penyesuaian stok |
| GET | `/stock-movements?sparepart_id=` | Kartu stok |
| GET | `/reports/stock` | Laporan stok terkini (JSON) |
| GET | `/reports/stock/pdf` | Cetak PDF stok terkini |

### Check Up
| GET | `/checkups` | Daftar |
| POST | `/checkups` | Buat (draft/lengkap) |
| GET/PUT | `/checkups/{id}` | Detail / ubah (selagi belum selesai) |
| POST | `/checkups/{id}/finish` | Body: `result` = `checkup_only` \| `continue_service`. Jika lanjut → response berisi `service_order_id` hasil buat otomatis |

### Service Order (SA)
| GET | `/service-orders` | Daftar + filter status/tanggal |
| POST | `/service-orders` | Buat baru (opsional `checkup_id`) |
| GET/PUT | `/service-orders/{id}` | Detail / ubah (selagi belum selesai) |
| POST | `/service-orders/{id}/start` | draft → in_progress |
| POST | `/service-orders/{id}/finish` | → finished (stok keluar) |
| POST | `/service-orders/{id}/pay` | → paid (keuangan, poin, member) |
| POST | `/service-orders/{id}/cancel` | Batal (dengan reversal) |
| GET | `/service-orders/{id}/print` | PDF Form SA / nota |

### Laporan
| GET | `/reports/finance?period=weekly\|monthly\|yearly&date=` | Ringkasan + rincian |
| GET | `/reports/finance/export` | Excel/PDF |
| GET | `/reports/unit-entries?from=&to=&type=` | Laporan unit entry |
| GET | `/dashboard/summary` | Angka ringkas dashboard |
| POST | `/expenses` | Pengeluaran operasional manual |

### Portal Member (role member, hanya data miliknya)
| GET | `/portal/profile` | Data, status member, poin, masa berlaku |
| GET | `/portal/history` | Riwayat check up & servis |
| GET | `/portal/history/{type}/{id}` | Detail hasil (kondisi, pekerjaan, part) tanpa HPP/harga beli |
| GET | `/portal/vehicles` | Kendaraan miliknya |

---

## 10. Struktur Folder

```
bengkel-app/                    ← repo (monorepo)
├── AGENT_RULES.md
├── docs/
│   └── BENGKEL-APP-BLUEPRINT.md
├── backend/                    ← Laravel 12
│   ├── app/
│   │   ├── Enums/              (ServiceOrderStatus, CheckupItemStatus, StockMovementType, ...)
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── CustomerController.php
│   │   │   │   ├── VehicleController.php
│   │   │   │   ├── MembershipController.php
│   │   │   │   ├── MechanicController.php
│   │   │   │   ├── ServiceController.php
│   │   │   │   ├── SparepartController.php
│   │   │   │   ├── PartPurchaseController.php
│   │   │   │   ├── CheckupTemplateController.php
│   │   │   │   ├── CheckupController.php
│   │   │   │   ├── ServiceOrderController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   ├── PortalController.php
│   │   │   │   └── SettingController.php
│   │   │   ├── Requests/       (FormRequest per aksi)
│   │   │   └── Resources/      (API Resource)
│   │   ├── Models/
│   │   ├── Services/
│   │   │   ├── DocumentNumberService.php
│   │   │   ├── SettingService.php
│   │   │   ├── MembershipService.php
│   │   │   ├── PointService.php
│   │   │   ├── StockService.php
│   │   │   ├── FinanceService.php
│   │   │   ├── CheckupService.php
│   │   │   ├── ServiceOrderService.php
│   │   │   └── ReportService.php
│   │   ├── Console/Commands/ExpireMemberships.php
│   │   └── Policies/
│   ├── database/{migrations,seeders,factories}
│   ├── routes/api.php
│   └── tests/Feature/
└── frontend/                   ← Vue 3 + Vite
    ├── src/
    │   ├── api/                (axios instance + modul per resource)
    │   ├── stores/             (auth, ui, ...)
    │   ├── router/
    │   ├── layouts/            (AdminLayout, PortalLayout, AuthLayout)
    │   ├── components/
    │   │   ├── ui/             (BaseButton, BaseInput, BaseModal, BaseTable, ...)
    │   │   ├── CustomerPicker.vue
    │   │   ├── CustomerQuickForm.vue   (tambah pelanggan di dalam form)
    │   │   ├── FuelGauge.vue           (level bensin bar)
    │   │   ├── CheckupItemsForm.vue
    │   │   └── ...
    │   ├── views/
    │   │   ├── auth/ dashboard/ customers/ checkups/ service-orders/
    │   │   ├── spareparts/ services/ mechanics/ unit-entries/
    │   │   ├── reports/ settings/ portal/
    │   ├── utils/ (format rupiah, tanggal)
    │   └── main.js
    └── vite.config.js
```

**Deployment:** build frontend (`npm run build`) → hasil `dist/` disajikan oleh web server pada domain utama, `/api` diarahkan ke `backend/public`. Alternatif sederhana: domain `app.example.com` (frontend) dan `api.example.com` (backend) dengan CORS dikonfigurasi.

---

## 11. Daftar Halaman Frontend

**Admin / Kasir**
| Route | Halaman |
|---|---|
| `/login` | Login |
| `/` | Dashboard (unit hari ini, SA berjalan, omzet hari ini, stok menipis, member akan hangus) |
| `/customers` , `/customers/:id` | Daftar & detail pelanggan (tab: kendaraan, membership & poin, riwayat) |
| `/checkups` , `/checkups/create` , `/checkups/:id` | Check up |
| `/service-orders` , `/service-orders/create` , `/service-orders/:id` | Form SA |
| `/unit-entries` | Rekap unit entry |
| `/spareparts` | Master sparepart + kartu stok |
| `/part-purchases` , `/part-purchases/create` | Input sparepart |
| `/services` | Master pekerjaan |
| `/mechanics` | Mekanik |
| `/rewards` | Reward tukar poin |
| `/reports/finance` | Laporan keuangan |
| `/reports/stock` | Laporan stok |
| `/reports/unit-entries` | Laporan unit entry |
| `/settings` | Setting umum, template check up, user |

**Portal Member** (`/portal/...`): beranda (status member, poin, masa berlaku, countdown), riwayat, detail hasil.

**Komponen kunci yang harus dibuat sebagai reusable:**
- `CustomerPicker` + `CustomerQuickForm` (dipakai di Check Up dan SA)
- `CheckupItemsForm` (dipakai di Check Up dan menampilkan ulang di SA)
- `FuelGauge` (klik bar untuk set level)
- `MoneyInput` (format Rupiah)

---

## 12. Perintah Kerja Bertahap

> **Cara memakai:** salin satu prompt, tempel ke Hermes Agent, tunggu selesai & test lulus, commit, baru lanjut.
> Setiap prompt diawali kalimat baku:
> **"Baca `AGENT_RULES.md` dan `docs/BENGKEL-APP-BLUEPRINT.md` bagian yang disebut, lalu kerjakan HANYA tugas berikut."**

---

### FASE 0 — Fondasi Repo & Production Pipeline

**Prompt 0.1 — Struktur repo**
```
Baca docs/BENGKEL-APP-BLUEPRINT.md bagian 3, 4, dan 10.
Tugas: buat struktur monorepo.
1. Buat file AGENT_RULES.md di root (isi persis dari bagian 4.1 blueprint).
2. Install Laravel 12 di folder /backend (PHP 8.3). Set timezone Asia/Jakarta, locale id.
3. Install & konfigurasi: laravel/sanctum, spatie/laravel-permission,
   barryvdh/laravel-dompdf, maatwebsite/excel.
4. Buat project Vue 3 + Vite di /frontend. Install: vue-router, pinia, axios, tailwindcss, dayjs, chart.js, vue-chartjs.
5. Buat .env.example di backend dan frontend (VITE_API_URL).
6. Buat .gitignore yang benar untuk keduanya.
Kriteria selesai: `php artisan test` jalan, `npm run build` sukses, `GET /api/v1/ping` mengembalikan {"success":true}.
```

**Prompt 0.2 — Standar API**
```
Baca AGENT_RULES.md.
Tugas: buat fondasi API.
1. Trait/helper ApiResponse untuk format { success, message, data, meta }.
2. Global exception handler JSON untuk /api/* (validasi 422, 401, 403, 404, 500).
3. DocumentNumberService: generate nomor CU-/SA-/UE-/PB- + YYYYMM + urut 4 digit,
   aman dari duplikasi (pakai tabel document_sequences + lockForUpdate).
4. SettingService + tabel settings + seeder semua key dari bagian 8.1.
5. Feature test untuk DocumentNumberService dan SettingService.
```

**Prompt 0.3 — Deploy awal ke hosting (go-live dini)**
```
Tugas: siapkan deployment ke hosting production.
1. Buat docs/DEPLOY.md berisi langkah: clone repo, composer install --no-dev -o,
   php artisan key:generate, migrate --force, config:cache, route:cache,
   storage:link, setup cron scheduler (* * * * * php artisan schedule:run), permission folder storage.
2. Buat skrip deploy.sh (git pull, composer install, migrate --force, cache, build frontend bila perlu).
3. Konfigurasi CORS dan Sanctum untuk domain production (ambil dari .env).
4. Buat contoh konfigurasi Nginx/Apache untuk melayani frontend (SPA fallback ke index.html) dan /api ke backend/public.
Jangan menulis kredensial apa pun ke repo.
```
> 💡 **Tips:** Deploy sejak Fase 0, lalu *deploy ulang tiap fase selesai*. Dengan begitu "langsung production" berjalan bertahap dan terkontrol, bukan satu ledakan di akhir.

---

### FASE 1 — Autentikasi, Role, Setting

**Prompt 1.1 — Backend auth & role**
```
Baca AGENT_RULES.md dan blueprint bagian 5, 8.1, 9 (Auth).
Tugas:
1. Migration users sesuai blueprint (name, email nullable, phone nullable unique, password, is_active, must_change_password, customer_id nullable).
2. Seeder role: owner, admin, kasir, gudang, member + permission sesuai bagian 5.
3. Seeder 1 user owner (email & password dari .env, bukan hardcode).
4. Endpoint: POST /auth/login (email ATAU phone + password, tolak jika is_active=false),
   POST /auth/logout, GET /auth/me (user + roles + permissions), POST /auth/change-password.
5. Throttle login 5x/menit.
6. Middleware role/permission pada group route.
Test: login sukses, login gagal, akun nonaktif ditolak, akses tanpa permission = 403.
```

**Prompt 1.2 — Frontend kerangka & login**
```
Baca AGENT_RULES.md dan blueprint bagian 11.
Tugas frontend:
1. Axios instance (baseURL dari VITE_API_URL, interceptor token & 401 → logout).
2. Pinia auth store (token di localStorage, user, roles, permissions, fungsi can()).
3. Router dengan guard (auth, role, permission), layout AdminLayout (sidebar + header) dan AuthLayout.
4. Halaman Login dan Ganti Password (paksa jika must_change_password).
5. Komponen UI dasar di components/ui: BaseButton, BaseInput, BaseSelect, BaseModal, BaseTable (dengan paginasi), BaseBadge, ConfirmDialog, Toast.
6. Util formatRupiah dan formatTanggal (Indonesia).
Sidebar menampilkan menu sesuai permission. Tampilan responsif (tablet-friendly).
```

---

### FASE 2 — Pelanggan & Kendaraan

**Prompt 2.1 — Backend**
```
Baca AGENT_RULES.md dan blueprint bagian 8.2 dan 9 (Pelanggan).
Tugas:
1. Migration + Model customers (SoftDeletes, code otomatis C-000001) dan vehicles (SoftDeletes).
2. Normalisasi nopol (uppercase, hapus spasi ganda) dan nomor HP (format 08xx / 62xx konsisten).
3. CustomerController: list dengan search (nama, HP, nopol) + paginasi; store boleh sekaligus vehicles[]; show (dengan kendaraan); update; destroy.
4. VehicleController: tambah/ubah/hapus kendaraan milik customer. Satu customer boleh banyak kendaraan. Nopol unik.
5. FormRequest + API Resource.
Test: tambah customer + 2 kendaraan, nopol duplikat ditolak, pencarian by nopol.
```

**Prompt 2.2 — Frontend**
```
Baca AGENT_RULES.md dan blueprint bagian 11.
Tugas:
1. Halaman /customers (tabel, cari, tambah, ubah, hapus) dan /customers/:id (tab: Info, Kendaraan, Membership & Poin [placeholder dulu], Riwayat [placeholder]).
2. Komponen REUSABLE CustomerPicker (autocomplete cari nama/HP/nopol; setelah pilih customer tampilkan dropdown kendaraan) dan CustomerQuickForm (modal tambah pelanggan + kendaraan cepat; setelah simpan langsung terpilih di picker). Emit event: selected(customer, vehicle).
Komponen ini akan dipakai ulang di Check Up dan Form SA.
```

---

### FASE 3 — Master: Mekanik, Service, Sparepart, Stok

**Prompt 3.1 — Mekanik & Service (backend + frontend)**
```
Baca AGENT_RULES.md dan blueprint 8.3.
Tugas: CRUD mechanics dan services (backend API + halaman frontend /mechanics dan /services).
services punya kolom price (integer Rupiah) dan member_discount_percent nullable (kosong = pakai setting global).
Di halaman services tampilkan kolom "Harga Member" yang dihitung dari diskon efektif.
Gunakan SoftDeletes. Test untuk CRUD dan perhitungan diskon efektif.
```

**Prompt 3.2 — Sparepart & ledger stok (backend)**
```
Baca AGENT_RULES.md dan blueprint 7.3, 8.3.
Tugas backend:
1. Migration/Model: spareparts, stock_movements, part_purchases, part_purchase_items, financial_transactions.
2. StockService dengan method: increase(), decrease(), adjust(), returnStock() — semua dalam DB::transaction + lockForUpdate, tolak stok minus, selalu tulis stock_movements dengan snapshot harga.
3. FinanceService::recordExpense()/recordIncome()/reverse().
4. POST /part-purchases: tambah stok tiap item, buat stock_movements type in, buat 1 financial_transaction expense kategori pembelian_sparepart sebesar total.
5. POST /stock-adjustments (wajib alasan), GET /stock-movements.
6. CRUD /spareparts (stok tidak boleh diubah langsung lewat update; hanya lewat movement).
Test WAJIB: input part menambah stok & mencatat expense; stok tidak bisa minus; adjust mencatat movement; concurrency sederhana (dua decrease bersamaan tidak membuat stok minus).
```

**Prompt 3.3 — Sparepart (frontend)**
```
Tugas frontend:
1. Halaman /spareparts: tabel (nama, harga beli, harga jual, stok, badge stok menipis bila stok <= min_stock), tambah/ubah, tombol "Kartu Stok" (modal riwayat movement).
2. Halaman /part-purchases/create (Input Sparepart): tanggal, supplier, baris item (pilih sparepart, qty, harga beli), total otomatis, simpan.
3. Modal Penyesuaian Stok (alasan wajib).
```

**Prompt 3.4 — Laporan stok & cetak**
```
Tugas:
1. GET /reports/stock: daftar sparepart, stok, harga beli, harga jual, nilai persediaan (stok × harga beli), total nilai, filter stok menipis.
2. GET /reports/stock/pdf: PDF rapi dengan header nama bengkel (dari settings), tanggal cetak, tabel, total. Orientasi portrait A4.
3. Halaman /reports/stock dengan tombol "Cetak PDF" dan "Export Excel".
Test: total nilai persediaan benar.
```

---

### FASE 4 — General Check Up

**Prompt 4.1 — Template & item pemeriksaan**
```
Baca AGENT_RULES.md dan blueprint 7.5, 8.4.
Tugas:
1. Migration/Model checkup_templates, checkup_template_items.
2. Seeder template default "General Check Up Motor" dan "General Check Up Mobil" (item per kategori: Mesin, Rem, Kelistrikan, Ban & Kaki-kaki, Cairan, Body).
3. API CRUD template + item (tambah, ubah, nonaktifkan, urutkan ulang).
4. Halaman Settings → "Template Check Up": kelola item, drag/urut (atau tombol naik/turun), aktif/nonaktif.
Test: item nonaktif tidak muncul saat form dibuat.
```

**Prompt 4.2 — Backend Check Up & Unit Entry**
```
Baca AGENT_RULES.md dan blueprint 6.1, 7.6, 8.4, 8.5.
Tugas backend:
1. Migration/Model unit_entries, checkups, checkup_results.
2. CheckupService::create(): buat unit_entry (type checkup_only, has_checkup true) + checkup + hasil item (SNAPSHOT nama & kategori dari template).
3. POST /checkups, GET /checkups, GET/PUT /checkups/{id} (edit hanya jika status draft).
4. POST /checkups/{id}/finish dengan body result=checkup_only|continue_service:
   - checkup_only → status completed.
   - continue_service → status completed + otomatis buat service_order (status draft) yang TERISI: customer, vehicle, keluhan, kondisi (salin checkup_results ke service_order_conditions), hubungkan ke unit_entry yang sama (unit_entry.type = service, service_order_id terisi). Response mengembalikan service_order_id.
   Proses harus atomik (DB::transaction).
Catatan: tabel service_orders dan service_order_conditions dibuat di Fase 5; jika belum ada, buat migration-nya sekarang sesuai blueprint 8.5.
Test: checkup_only tidak membuat SA; continue_service membuat SA dengan kondisi & keluhan tersalin dan unit_entry yang sama.
```

**Prompt 4.3 — Frontend form Check Up**
```
Baca AGENT_RULES.md dan blueprint 6.1, 11.
Tugas frontend:
1. Halaman /checkups (daftar) dan /checkups/create.
2. Form: CustomerPicker (dengan CustomerQuickForm bila pelanggan belum ada), pilih kendaraan, odometer, keluhan konsumen (textarea), CheckupItemsForm (item dikelompokkan per kategori; tiap item pilihan OK / Perlu Perhatian / Rusak / Tidak Diperiksa + catatan; warna status berbeda), catatan umum.
3. Tombol Simpan Draft dan "Selesai". Saat Selesai tampilkan modal 2 pilihan: [Hanya Check Up] atau [Lanjut Service]. Pilihan kedua langsung redirect ke /service-orders/:id (SA yang sudah terisi).
4. Template dipilih otomatis sesuai jenis kendaraan.
```

---

### FASE 5 — Form SA (Service Order)

**Prompt 5.1 — Backend CRUD SA & perhitungan**
```
Baca AGENT_RULES.md dan blueprint 7.1, 7.3, 8.5.
Tugas backend:
1. Migration/Model service_orders, service_order_services, service_order_parts, service_order_conditions (jika belum).
2. ServiceOrderService::create/update dengan perhitungan server-side (JANGAN percaya total dari frontend):
   - Tiap item jasa: snapshot nama & harga dari master; diskon member otomatis bila customer punya membership aktif pada tanggal SA (persen = override service → override membership → setting global). Sparepart TIDAK didiskon.
   - Tiap item part: snapshot nama, harga beli, harga jual; validasi stok cukup (belum dikurangi).
   - Hitung subtotal_services, discount_services, total_services, total_parts, grand_total, estimate_total.
3. Snapshot customer_name, plate_number, phone, vehicle_name pada SA.
4. Validasi fuel_level 0..(setting fuel_bar_count).
5. Endpoint: POST/GET/PUT /service-orders, daftar dengan filter status & tanggal.
6. Edit hanya boleh bila status draft atau in_progress.
Test: diskon hanya pada jasa, tidak pada part; non-member tanpa diskon; member kadaluarsa tanpa diskon; override persen per service.
```

**Prompt 5.2 — Backend status & dampaknya**
```
Baca AGENT_RULES.md dan blueprint 6.2, 7.3, 7.4.
Tugas:
1. POST /service-orders/{id}/start : draft → in_progress.
2. POST /service-orders/{id}/finish : in_progress → finished. Dalam 1 transaksi: kurangi stok semua part (StockService::decrease, reference service_order) — gagal total jika ada stok kurang.
3. POST /service-orders/{id}/pay : finished → paid. Body: payment_method, paid_amount. Dalam 1 transaksi: catat 2 income (pendapatan_jasa = total_services, penjualan_sparepart = total_parts; abaikan baris bernilai 0), isi paid_at. (Poin & membership ditambahkan di Fase 6 — sediakan hook/event ServiceOrderPaid.)
4. POST /service-orders/{id}/cancel : jika sudah finished/paid → kembalikan stok (type return) dan buat reversal keuangan; wajib alasan. Status cancelled.
5. Transisi status tidak valid ditolak (422).
Test: stok berkurang saat finish; income tercatat saat pay; cancel mengembalikan stok & membuat reversal; part dengan stok kurang membuat finish gagal dan stok tidak berubah.
```

**Prompt 5.3 — Frontend Form SA**
```
Baca AGENT_RULES.md dan blueprint 6.2, 11.
Tugas frontend:
1. /service-orders (daftar + filter status/tanggal) dan /service-orders/create dan /service-orders/:id.
2. Form SA: CustomerPicker + CustomerQuickForm (nopol, no HP, kendaraan terisi otomatis), CheckupItemsForm untuk kondisi kendaraan (terisi otomatis bila dari check up), komponen FuelGauge (8 bar, klik untuk set), keluhan, pilih mekanik, tabel jasa (cari dari master, qty, tampil harga, diskon member, subtotal), tabel part (cari dari master, qty, tampil stok tersisa, peringatan bila melebihi stok), panel ringkasan biaya (subtotal jasa, diskon member, total jasa, total part, estimasi total) — hanya tampilan; angka final dihitung server.
3. Tombol aksi sesuai status: Simpan, Mulai Dikerjakan, Selesai, Bayar (modal metode bayar), Batalkan.
4. Badge "MEMBER" dan sisa masa berlaku bila pelanggan member.
```

**Prompt 5.4 — Cetak SA / Nota**
```
Tugas: GET /service-orders/{id}/print menghasilkan PDF (A5 atau A4) berisi: header bengkel, no SA, tanggal, data pelanggan & kendaraan, level bensin (visual bar), kondisi kendaraan, keluhan, mekanik, daftar jasa (dengan diskon member), daftar part, total. Tambahkan tombol Cetak di halaman detail SA. Tanda tangan pelanggan (kolom kosong) di bagian bawah.
```

---

### FASE 6 — Membership & Poin

**Prompt 6.1 — Backend membership & poin**
```
Baca AGENT_RULES.md dan blueprint 6.3, 7.2, 8.2, bagian 2 (keputusan D2–D8, D13).
Tugas:
1. Migration/Model memberships, point_transactions, rewards, reward_redemptions.
2. MembershipService:
   - activate(customer): buat membership active, expires_at = hari ini + settings.membership_duration_months, member_no unik, BUAT akun user role member (login = phone customer, password acak, must_change_password=true). Kembalikan password awal SEKALI dalam response (tidak disimpan plain).
   - renewFromService(customer, date): perpanjang expires_at = date + durasi; last_service_at = date.
   - expire(): set status expired; jika setting points_expire_with_membership true → buat point_transaction type expire dan saldo jadi 0.
3. PointService: earn(), redeem(), adjust() — selalu lewat point_transactions + update points_balance di transaksi yang sama.
4. Listener ServiceOrderPaid: bila customer member aktif → earn poin = floor(total_services / points_per_amount), dan renewFromService. (Jika is_member_at_entry false namun ada auto_member_after_first_service → activate.)
5. Artisan command memberships:expire + jadwalkan harian jam 00:05 di routes/console.php.
6. Endpoint: POST /customers/{id}/membership, POST .../renew, GET .../points, POST .../redeem (reward_id → cek saldo, kurangi stok part reward via StockService, buat reward_redemption; bila setting record_redeem_cost → catat expense kategori promosi_poin sebesar HPP).
7. CRUD /rewards (default seed: "Oli Gratis" 100 poin → pilih sparepart oli).
Test: aktivasi buat akun & expires 6 bulan; servis dibayar → poin bertambah & expires diperpanjang; non-member tidak dapat poin; command expire menghanguskan member & poin; redeem poin kurang ditolak; redeem mengurangi stok & poin.
```

**Prompt 6.2 — Frontend membership**
```
Tugas frontend:
1. Tab "Membership & Poin" di detail pelanggan: status (badge Aktif/Hangus), no member, tanggal mulai, tanggal berakhir + sisa hari, saldo poin, tombol Jadikan Member / Perpanjang, riwayat poin, tombol Tukar Poin (pilih reward).
2. Saat berhasil Jadikan Member: modal menampilkan password awal SEKALI beserta tombol Salin dan Cetak kartu member kecil.
3. Halaman /rewards (CRUD reward).
4. Di dashboard: widget "Member akan hangus ≤ 14 hari".
```

---

### FASE 7 — Laporan Keuangan & Unit Entry

**Prompt 7.1 — Backend laporan**
```
Baca AGENT_RULES.md dan blueprint 7.4, 9 (Laporan).
Tugas ReportService:
1. finance(period weekly|monthly|yearly, date): kembalikan total income per kategori, total expense per kategori, arus kas (income-expense), HPP part terjual (dari snapshot buy_price service_order_parts untuk SA paid pada periode), laba kotor, jumlah unit, rata-rata nilai per SA, dan data seri untuk grafik (harian untuk weekly/monthly, bulanan untuk yearly).
2. Pastikan transaksi reversal dihitung benar (tidak double).
3. unitEntries(from, to, type): daftar unit entry (no, tanggal, pelanggan, nopol, tipe, status SA bila ada) + ringkasan jumlah check up saja vs service, jumlah check up yang lanjut service (konversi %).
4. Endpoint JSON + export Excel/PDF untuk finance.
5. POST /expenses untuk pengeluaran operasional manual.
Test dengan data fixture: weekly/monthly/yearly menghasilkan angka benar, reversal benar, laba kotor benar.
```

**Prompt 7.2 — Frontend laporan**
```
Tugas frontend:
1. /reports/finance: pilihan periode (Mingguan/Bulanan/Tahunan) + pemilih tanggal, kartu ringkasan (Pemasukan, Pengeluaran, Arus Kas, Laba Kotor), grafik (Chart.js), tabel rincian per kategori, tab "Arus Kas" dan "Laba Kotor", tombol Export Excel & Cetak PDF. Hanya role owner/admin.
2. /reports/unit-entries: filter tanggal & tipe, tabel, ringkasan konversi check up → service, export.
3. Menu "Pengeluaran Lain" (form input operasional).
```

---

### FASE 8 — Portal Member

**Prompt 8.1 — Backend**
```
Baca AGENT_RULES.md dan blueprint 9 (Portal Member).
Tugas: endpoint /portal/* hanya untuk role member.
- SEMUA query WAJIB difilter customer_id milik user login (tes keamanan: member A tidak bisa melihat data member B walau menebak ID → 404).
- Detail hasil: kondisi kendaraan, keluhan, pekerjaan, part yang diganti (nama & qty), mekanik, tanggal, total. JANGAN tampilkan harga beli/HPP.
- Member berstatus expired tetap bisa login dan melihat riwayat, tetapi tampilkan banner "Membership berakhir".
Test keamanan IDOR dan kebocoran harga beli.
```

**Prompt 8.2 — Frontend**
```
Tugas: PortalLayout (mobile-first) dengan halaman: Beranda (kartu member: status, sisa masa berlaku, saldo poin, progres menuju reward), Riwayat (daftar check up & servis per kendaraan), Detail hasil. Login memakai No. HP. Paksa ganti password di login pertama. Pengguna member tidak boleh mengakses route admin (guard router + backend).
```

---

### FASE 9 — Dashboard, Hardening, Kualitas

**Prompt 9.1 — Dashboard**
```
Tugas: GET /dashboard/summary dan halaman dashboard: unit masuk hari ini, SA berjalan, SA menunggu pembayaran, omzet hari ini, stok menipis (top 5), member akan hangus, grafik 7 hari terakhir.
```

**Prompt 9.2 — Keamanan & performa**
```
Tugas audit (perbaiki temuan, jangan ubah fitur):
1. Pastikan semua route API terlindungi auth + permission; tambah policy bila perlu.
2. Cek N+1 query pada list endpoint (pakai with()), tambah index DB pada kolom filter/tanggal.
3. Rate limit endpoint sensitif; sanitasi input; nonaktifkan debug.
4. Aktifkan spatie/laravel-activitylog pada model: customers, spareparts, services, service_orders, memberships, settings (siapa mengubah apa).
5. Pastikan tidak ada float untuk uang di seluruh kode (grep).
6. Buat tes ringkas end-to-end alur: check up → lanjut SA → finish → pay → cek stok, income, poin, expires.
Laporkan daftar temuan dan perbaikan.
```

**Prompt 9.3 — Backup & monitoring**
```
Tugas: siapkan backup database harian (spatie/laravel-backup atau skrip mysqldump + cron, simpan 14 hari terakhir), logging error ke file harian, halaman /health sederhana. Dokumentasikan prosedur restore di docs/DEPLOY.md.
```

---

### FASE 10 — Go-Live
Ikuti [Checklist Go-Live](#14-checklist-go-live-production). Lakukan **UAT** (uji oleh kasir/mekanik asli) dengan data uji sebelum memasukkan data asli.

---

## 13. Saran Fitur Tambahan

Diurutkan dari prioritas tertinggi (nilai tinggi, usaha rendah):

| Prioritas | Fitur | Manfaat |
|---|---|---|
| ⭐⭐⭐ | **Pengingat servis berikutnya** (tanggal / odometer) + tombol kirim WhatsApp (link `wa.me` dengan template pesan) | Pelanggan kembali lebih sering; menjaga member tidak hangus |
| ⭐⭐⭐ | **Notifikasi member akan hangus** (H-14, H-7) via WhatsApp link | Retensi pelanggan |
| ⭐⭐⭐ | **Persetujuan estimasi pelanggan** (checkbox/tanda tangan di SA sebelum dikerjakan) | Mencegah sengketa biaya |
| ⭐⭐⭐ | **Foto kondisi kendaraan** (sebelum/sesudah) pada SA & check up | Bukti kondisi, kepercayaan |
| ⭐⭐ | **Komisi/produktivitas mekanik** (jumlah unit & omzet jasa per mekanik per periode) | Evaluasi & insentif |
| ⭐⭐ | **Supplier & hutang** (master supplier, pembelian kredit) | Keuangan lebih rapi |
| ⭐⭐ | **Peringatan stok minimum** + saran daftar belanja | Hindari kehabisan part |
| ⭐⭐ | **Tutup kas harian** (rekap kas masuk per metode bayar) | Kontrol kasir |
| ⭐⭐ | **Garansi pekerjaan/part** (masa garansi di nota) | Nilai tambah layanan |
| ⭐⭐ | **Pembayaran sebagian (DP/cicil)** | Fleksibilitas |
| ⭐ | **Retur sparepart ke supplier** | Akurasi stok |
| ⭐ | **Paket servis** (bundling jasa + part) | Mempercepat input SA |
| ⭐ | **Level member (Silver/Gold)** berdasar total belanja | Gamifikasi |
| ⭐ | **QR code pada nota/kartu member** → buka portal | Kemudahan akses |
| ⭐ | **PWA** (portal bisa di-install di HP) | Pengalaman seperti aplikasi |
| ⭐ | **Cetak thermal 58/80mm** untuk nota | Cocok untuk kasir |

> Saran: kerjakan dulu Fase 0–9 sampai stabil di production, baru tambah fitur di atas satu per satu.

---

## 14. Checklist Go-Live Production

**Server & Konfigurasi**
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` terisi
- [ ] HTTPS aktif (SSL), redirect HTTP → HTTPS
- [ ] `.env` tidak ada di repo, izin file aman (600)
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`, `db:seed --class=RolePermissionSeeder` & seeder setting
- [ ] `config:cache`, `route:cache`, `event:cache`, `storage:link`
- [ ] Cron scheduler aktif (`schedule:run` tiap menit) → pastikan `memberships:expire` berjalan
- [ ] CORS & Sanctum hanya mengizinkan domain production
- [ ] Backup DB harian berjalan & **sudah diuji restore**
- [ ] Akun owner default diganti passwordnya

**Aplikasi**
- [ ] Semua test lulus (`php artisan test`)
- [ ] `npm run build` sukses dan di-deploy
- [ ] Setting bengkel terisi (nama, alamat, telepon untuk header nota)
- [ ] Template Check Up disesuaikan dengan kebutuhan bengkel
- [ ] Master mekanik, service, sparepart (beserta stok awal) diinput
- [ ] Stok awal diinput lewat **Penyesuaian Stok/Input Sparepart** dengan keterangan "Stok awal" (pikirkan dampak pada laporan pengeluaran; bila tidak ingin tercatat sebagai pengeluaran, gunakan penyesuaian stok)
- [ ] User kasir/admin/gudang dibuat

**UAT (wajib)**
- [ ] Skenario 1: pelanggan baru → check up → hanya check up
- [ ] Skenario 2: pelanggan baru → check up → lanjut SA → selesai → bayar
- [ ] Skenario 3: member aktif → SA dengan jasa & part → diskon hanya jasa, poin bertambah
- [ ] Skenario 4: tukar poin oli gratis → stok oli berkurang
- [ ] Skenario 5: input sparepart → pengeluaran tercatat; SA dibayar → pemasukan tercatat
- [ ] Skenario 6: cancel SA → stok & keuangan kembali
- [ ] Skenario 7: login member → hanya melihat data sendiri
- [ ] Laporan mingguan/bulanan/tahunan cocok dengan hitungan manual
- [ ] Cetak SA, nota, dan laporan stok tampil rapi

**Setelah Go-Live**
- [ ] Pantau log error 1 minggu pertama
- [ ] Kumpulkan masukan kasir & mekanik, catat sebagai backlog
- [ ] Jadwalkan review keamanan & update dependensi bulanan

---

## Lampiran A — Template Prompt Harian (untuk tugas di luar daftar)

```
Baca AGENT_RULES.md. Konteks: aplikasi manajemen service bengkel (Laravel 12 + Vue 3).
Tugas: <satu tugas spesifik>
File yang boleh diubah: <daftar file/folder>
Aturan bisnis terkait: <rujuk bagian blueprint, mis. 7.1>
Kriteria selesai: <hasil yang bisa diuji>
Wajib: tulis test, jalankan php artisan test, laporkan hasil. Jangan ubah di luar scope.
```

## Lampiran B — Prompt Perbaikan Bug

```
Baca AGENT_RULES.md.
Bug: <deskripsi + langkah reproduksi + hasil yang diharapkan vs aktual>
Langkah: 1) tulis test yang GAGAL mereproduksi bug, 2) perbaiki dengan perubahan minimal,
3) pastikan test lulus dan semua test lain tetap lulus, 4) jelaskan akar masalah dalam 3 kalimat.
```

---
*Akhir dokumen.*
