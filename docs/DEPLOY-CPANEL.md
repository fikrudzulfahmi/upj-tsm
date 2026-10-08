# Deploy ke Hosting cPanel — 2 Subdomain, 1 Repo

Panduan ini untuk skenario yang dipilih: **satu repo (monorepo `upj/`)** yang dideploy ke
**dua subdomain**:

| Subdomain | Isi | Document root |
|---|---|---|
| `bengkel.domain-anda.com` | SPA Vue (hasil build) | folder kosong, mis. `/home/USER/bengkel-spa` |
| `api-bengkel.domain-anda.com` | Laravel 12 API | `<repo>/backend/public` |

Autodeploy lewat GitHub Actions (`.github/workflows/deploy-api.yml` & `deploy-spa.yml`),
yang hanya jalan bila folder terkait berubah.

> Aturan penting: **`VITE_API_URL` tertanam saat build.** Selalu lewat secret CI —
> kalau Anda menjalankan `npm run build` di laptop, aplikasi produksi akan menembak
> `http://127.0.0.1` dan seluruh data gagal dimuat.

---

## 1. Sekali saja — repo & akses

1. Buat repo di GitHub (boleh **private berisi** kode ini; repo publik tetap aman karena
   `.env` di-ignore dan tidak ada nama klien di dalam kode).
2. Push monorepo ini (dari laptop):
   ```bash
   cd D:/ApplicationWeb/upj
   git init -b main
   git add .
   git commit -m "Aplikasi manajemen bengkel: backend API + SPA"
   git remote add origin git@github.com:USER/NAMA-REPO.git
   git push -u origin main
   ```
3. **Kunci SSH server → GitHub** supaya server dapat `git pull`:
   1. Di server (SSH): `ssh-keygen -t ed25519 -C "server-bengkel" -f ~/.ssh/github_deploy -N ""`
   2. Salin isi `~/.ssh/github_deploy.pub`
   3. Di repo GitHub: **Settings → Deploy keys → Add deploy key** → tempel kunci itu,
      centang **Allow write access = TIDAK** (cukup baca), simpan.
   4. Di server, uji: `ssh -T git@github.com` (jawab `yes` saat ditanya fingerprint).
   5. Agar tidak menanyakan kunci setiap pull, tambahkan ke `~/.ssh/config`:
      ```
      Host github.com
        HostName github.com
        User git
        IdentityFile ~/.ssh/github_deploy
        IdentitiesOnly yes
      ```

## 2. Clone repo di server (sekali saja)

```bash
mkdir -p ~/repos && cd ~/repos
git clone git@github.com:USER/NAMA-REPO.git bengkel
```

Setelah ini, **folder SPA** dan **document root API** diarahkan ke dalam repo tersebut:

- API → `~/repos/bengkel/backend/public`
- SPA → `~/bengkel-spa` (folder terpisah, diisi oleh workflow, bukan oleh git)

## 3. Membuat subdomain di cPanel (perhatikan dua hal ini)

**Domains → Create A New Domain** (atau **Subdomains**), lalu untuk **masing-masing**:

| Kolom | Isi |
|---|---|
| **Domain** (dropdown) | pilih **domain yang benar** — bila domain aplikasi Anda adalah *addon domain*, pilih addon itu, **bukan** domain utama |
| **Subdomain** | **label pendek saja**: `api-bengkel` atau `bengkel` (tanpa titik, tanpa domain) |
| **Document Root** | API: `repos/bengkel/backend/public` · SPA: `bengkel-spa` |

> **Kalau dropdown Domain salah**, sertifikat akan terbit sebagai
> `api-bengkel.bengkel.domain.ingintau.my.id` → pengunjung melihat
> *"Your connection is not private"* dan aplikasi **tidak bisa** memanggil API-nya.
> Ini pernah terjadi di proyek Anda sebelumnya.

Setelah kedua subdomain dibuat:

1. **MultiPHP Manager** → pastikan kedua subdomain memakai **PHP 8.2** (atau 8.3).
   Aplikasi ini butuh PHP **≥ 8.2**.
2. **SSL/TLS Status → Run AutoSSL** (untuk kedua subdomain). Tanpa HTTPS, aplikasi
   tidak akan bisa mengakses API (dan Web Bluetooth mustahil).

## 4. Berkas `.env` di server

```bash
cd ~/repos/bengkel/backend
cp .env.production.example .env
nano .env      # isi APP_URL, FRONTEND_URL, DB_*, OWNER_*, SHOP_*
```

Nilai yang wajib benar:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-bengkel.domain-anda.com
FRONTEND_URL=https://bengkel.domain-anda.com
```

Buat database di **MySQL Databases** cPanel lebih dulu (nama akan berawalan user,
mis. `namauser_bengkel`), lalu masukkan nama + user + password ke `.env`.

## 5. Deploy pertama (sekali saja, lewat SSH)

```bash
cd ~/repos/bengkel/backend
php artisan key:generate
php artisan migrate --force

# seeder wajib (aman dijalankan ulang)
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=OwnerUserSeeder

php artisan storage:link
php artisan config:cache && php artisan route:cache
```

**Jangan** menjalankan `DemoSeeder` di produksi — itu mengisi data contoh.

Untuk SPA, cara termudah adalah **jalankan workflow** (tab Actions → *Deploy SPA (Vue)* →
*Run workflow*), atau sementara unggah manual isi `frontend/dist` dari laptop ke
document root SPA (termasuk berkas `.htaccess` yang tersembunyi).

## 6. Secrets GitHub (satu repo → diisi sekali)

**Settings → Secrets and variables → Actions → New repository secret**
(formnya hanya punya *Name* + *Secret*, jadi isi satu per satu):

| Nama | Nilai | Wajib |
|---|---|---|
| `SSH_HOST` | IP/host cPanel, mis. `103.x.x.x` | ✅ |
| `SSH_USER` | user cPanel | ✅ |
| `SSH_PORT` | biasanya `22` | ✅ |
| `SSH_KEY` | **isi** kunci privat yang Anda miliki (seluruh isi berkas, termasuk baris `BEGIN`/`END`) | ✅ |
| `DEPLOY_PATH_API` | `/home/USER/repos/bengkel/backend` | ✅ |
| `DEPLOY_PATH_SPA` | `/home/USER/bengkel-spa` | ✅ |
| `VITE_API_URL` | `https://api-bengkel.domain-anda.com/api/v1` | ✅ |
| `VITE_APP_NAME` | `Aplikasi Bengkel` | – |
| `PHP_BIN` | mis. `/usr/local/bin/ea-php82` bila `php` bawaan terlalu tua | – |
| `API_URL` | `https://api-bengkel.domain-anda.com/api/v1` → memicu uji kesehatan API | – |
| `APP_URL` | `https://bengkel.domain-anda.com` → memicu uji kesehatan SPA | – |

> Perhatikan: `DEPLOY_PATH_SPA` **tanpa** `/api/v1`, sedangkan `VITE_API_URL` **dengan** `/api/v1`.

## 7. Deploy selanjutnya (otomatis)

| Anda mengubah | Yang jalan otomatis |
|---|---|
| `backend/**` | *Deploy API (Laravel)* → pull, composer, migrate, cache |
| `frontend/**` | *Deploy SPA (Vue)* → build, unggah `dist` |
| keduanya | **dua** workflow berjalan bersamaan (tidak saling ganggu) |

Cara memantau: tab **Actions** → klik run → klik **job** di sidebar kiri.
Kalau gagal, buka langkah yang merah, **atau** lihat tab **Annotations** (pesan
diagnostik saya taruh di sana agar tidak perlu menggali log).

Catatan wajar: salinan repo **di server** hanya ter-`pull` ketika `backend/**` berubah.
Itu tidak masalah — dari repo server hanya `backend/public` yang dilayani; berkas SPA
selalu diunggah dari hasil build di GitHub, bukan dari salinan repo di server.

## 8. Bila ada masalah

| Gejala | Periksa |
|---|---|
| SPA terbuka tapi semua data kosong / "Tidak dapat terhubung ke server" | `VITE_API_URL` salah (dibakar saat build) → perbaiki secret, **jalankan ulang workflow SPA**. Cek juga Console peramban untuk galat CORS |
| `/kasir` 404 saat di-refresh | `.htaccess` tidak ikut terunggah ke document root SPA |
| API 500 | `~/repos/bengkel/backend/storage/logs/laravel.log` |
| API 404 untuk semua `/api/...` | document root subdomain API belum menunjuk `backend/public` |
| HTTPS "not private" | dropdown Domain salah saat membuat subdomain (§3) → hapus, buat ulang, Run AutoSSL |
| Tidak bisa login padahal password benar | `FRONTEND_URL` di `.env` server belum memuat origin SPA → CORS memblokir |
| Cek kebocoran berkas rahasia | `curl -I https://api-bengkel.domain-anda.com/.env` → harus **403/404** |

## 9. Rollback cepat

```bash
cd ~/repos/bengkel
git log --oneline -5                 # cari commit terakhir yang sehat
git reset --hard <SHA>
cd backend && php artisan config:cache && php artisan route:cache
```
Untuk SPA: Actions → pilih run lama yang hijau → **Re-run all jobs** (membangun ulang
dari commit saat itu).

## 10. Backup harian (cron cPanel)

Tambahkan di **Cron Jobs**:

```bash
mysqldump -u USER -p'PASS' namauser_bengkel | gzip > ~/backup/bengkel-$(date +\%F).sql.gz
find ~/backup -name 'bengkel-*.sql.gz' -mtime +14 -delete
```

Uji restore minimal sekali di database terpisah. Backup yang belum pernah diuji restore
bukan backup.

## 11. Setelah live — yang wajib dilakukan

- [ ] Ganti password owner (`OWNER_PASSWORD` bawaan hanya untuk seeder awal)
- [ ] Isi **Pengaturan → Identitas Bengkel** (dipakai kop nota & laporan)
- [ ] Isi master: mekanik, jasa, sparepart + stok awal
- [ ] Sesuaikan **Template Check Up**
- [ ] Bila memakai printer thermal: buka **Pengaturan → Printer Thermal** di perangkat kasir
      (Chrome/Edge Android) → Pilih Printer → Cetak Uji
- [ ] Matikan `APP_DEBUG` (pastikan `false`) dan cek `curl -I .../.env` = 403/404
