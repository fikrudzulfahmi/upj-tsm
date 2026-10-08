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

## Tema UI (tambahan pemilik)
- Dasar putih, aksen merah. Warna aksen: red-600 (#dc2626) / red-700 untuk hover.
- Ikon memakai SVG (lucide-vue-next), bukan emoji.
