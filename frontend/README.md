# Frontend — Aplikasi Bengkel (Vue 3 + Vite)

SPA untuk aplikasi manajemen service bengkel. Panduan lengkap ada di README repo induk.

## Menjalankan

```bash
npm install
cp .env.example .env      # VITE_API_URL=http://127.0.0.1:8000/api/v1
npm run dev               # http://127.0.0.1:5173
```

## Perintah lain

```bash
npm run build             # build produksi ke dist/
node tools/uji-escpos.mjs # uji encoder nota thermal (38 pemeriksaan, tanpa printer)
```

## Catatan penting

- **`VITE_API_URL` dibakar saat build.** Di produksi nilainya diberikan lewat secret
  GitHub Actions, bukan dari `.env` lokal — kalau salah, aplikasi akan menembak alamat lokal.
- **`public/.htaccess`** wajib ikut terunggah ke document root produksi, kalau tidak halaman
  seperti `/kasir` akan 404 saat di-refresh (SPA history mode).
- Halaman cetak (`/cetak/...`) sengaja tanpa layout admin agar bersih saat dicetak.
