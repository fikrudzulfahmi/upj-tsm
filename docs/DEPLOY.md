# Panduan Deploy ke Production
> **Untuk hosting cPanel** (skenario yang dipakai proyek ini: 1 repo + 2 subdomain,
> dengan autodeploy GitHub Actions), ikuti **[`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md)** —
> panduan itu memuat langkah klik-demi-klik, daftar secret, dan tabel diagnosis.


Target umum: hosting cPanel/VPS dengan PHP 8.3 + MySQL 8/MariaDB 10.6+.
Frontend (SPA) dilayani sebagai berkas statis, backend sebagai API di subdomain.

## 0. Ringkas alur

1. Backend → `public/` sebagai document root (atau subdomain `api.domain.com`).
2. Frontend → hasil `npm run build` (`dist/`) di docroot domain utama + `.htaccess` SPA fallback.
3. Rahasia hanya di `.env` server, **tidak pernah** masuk repo.

## 1. Backend

```bash
git clone <repo> && cd backend
cp .env.example .env      # isi APP_URL, DB_*, FRONTEND_URL, OWNER_*, SHOP_*
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=OwnerUserSeeder
php artisan config:cache && php artisan route:cache && php artisan event:cache
php artisan storage:link
```

Isi `.env` produksi yang wajib:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.domain-anda.com
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
FRONTEND_URL=https://domain-anda.com,https://www.domain-anda.com
OWNER_NAME="..." OWNER_EMAIL=... OWNER_PASSWORD=...
SHOP_NAME="..." SHOP_ADDRESS="..." SHOP_PHONE="..."
```

Catatan penting:
- `FRONTEND_URL` adalah daftar origin dipisah koma; `localhost` dan `127.0.0.1` dihitung **origin berbeda**.
- `exposed_headers` di `config/cors.php` sudah memuat `Content-Disposition` agar nama berkas PDF/Excel terbaca peramban.
- **Jangan** jalankan `DemoSeeder` di production.

### Cron (wajib, untuk membership hangus)

```
* * * * * cd /home/USER/backend && php artisan schedule:run >> /dev/null 2>&1
```

Verifikasi: `php artisan schedule:list` harus memuat `memberships:expire` (harian 00:05).

### Deploy ulang (skrip)

```bash
cd backend
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan route:clear && php artisan route:cache
php artisan config:cache
php artisan queue:restart
```

> Setiap menambah rute baru: `route:clear` **sebelum** `route:cache`.

## 2. Frontend

```bash
cd frontend
npm ci
# .env: VITE_API_URL=https://api.domain-anda.com/api/v1
npm run build
# unggah isi dist/ ke docroot domain utama
```

`.htaccess` di docroot (SPA fallback):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.html$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.html [L]
</IfModule>
```

## 3. Backup & restore

```bash
# Harian (cron), simpan 14 hari terakhir
mysqldump -u USER -p'PASS' bengkel_app | gzip > /home/USER/backup/bengkel-$(date +\%F).sql.gz
find /home/USER/backup -name 'bengkel-*.sql.gz' -mtime +14 -delete

# Restore
gunzip < bengkel-2026-10-07.sql.gz | mysql -u USER -p'PASS' bengkel_app
```

**Uji restore minimal sekali** di database terpisah sebelum mengandalkan backup.

## 4. Checklist go-live (ringkas dari blueprint bagian 14)

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` terisi
- [ ] HTTPS aktif + redirect HTTP → HTTPS
- [ ] `.env` tidak ada di repo, izin berkas aman
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `migrate --force` + seeder peran/pengaturan/owner
- [ ] `config:cache`, `route:cache`, `event:cache`, `storage:link`
- [ ] Cron `schedule:run` aktif dan `memberships:expire` terdaftar
- [ ] CORS & Sanctum hanya domain production
- [ ] Backup harian berjalan & **sudah diuji restore**
- [ ] Password owner default diganti
- [ ] `php artisan test` semua lulus; `npm run build` sukses
- [ ] Setting bengkel terisi (nama, alamat, telepon untuk kop nota)
- [ ] Template Check Up disesuaikan bengkel
- [ ] Master mekanik/jasa/sparepart + stok awal diinput
- [ ] UAT 7 skenario blueprint dijalankan dengan data uji
