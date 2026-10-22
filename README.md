# Smart Log Center

Threat management dashboard untuk Wazuh, dibangun dengan Laravel 13 dan PHP 8.4.

Data alert, agent, vulnerability, dan SCA dibaca langsung dari Wazuh indexer
(OpenSearch) secara read-only. Akun, triage, label, laporan, dan audit log
disimpan di PostgreSQL (atau MySQL). Tampilan dark/light, tanpa build step —
CSS dan JS sudah ada di `public/assets`.

## Fitur utama

| Modul | Isi |
|---|---|
| Overview | KPI alert, tren per severity, top rule/agent, triage coverage |
| Alert Explorer | Pencarian, filter, paginasi, triage massal, export PDF |
| Alert Groups | Pohon agent → rule → alert, plus report per grup rule |
| Threat Hunting | Matriks MITRE ATT&CK dan peta taktik |
| Agents & Health | Status agent, detail vulnerability dan SCA per agent |
| Compliance | PCI DSS, HIPAA, GDPR, NIST 800-53, TSC |
| Reports | Antrian laporan: incident, vulnerability, SCA |
| Users | Kelola akun, role (admin/analyst/viewer), tema awal |
| Audit Log | Riwayat login, perubahan akun, perubahan backend |
| Configuration | Ubah koneksi Wazuh indexer tanpa menyentuh `.env` |

Semua halaman terkunci di balik wizard `/setup` sampai database dan indexer
tersambung. Wizard menjalankan migrasi sendiri, jadi tidak ada langkah manual.

## Instalasi via clone

Butuh PHP 8.3+ (`pdo_pgsql` atau `pdo_mysql`, `curl`, `openssl`, `mbstring`),
Composer 2, dan akses HTTPS ke Wazuh indexer (biasanya port `9200`).
Tidak perlu Node.js.

```bash
git clone https://github.com/manarul04/smartlog-center.git
cd smartlog-center
composer install
php artisan serve --port=8124
```

Buka `http://127.0.0.1:8124`. Tidak perlu `cp .env.example .env`, tidak perlu
`php artisan key:generate`, dan tidak perlu `migrate` manual — semuanya
dikerjakan wizard saat halaman pertama dibuka:

1. `.env` dibuat dari `.env.example` bila belum ada.
2. `APP_KEY` dibuat dan disimpan di `storage/framework/app-key` (di-ignore git).
3. Semua halaman dialihkan ke `/setup`.

### Isi wizard

**1. Database** — engine (PostgreSQL/MySQL), host, port, nama database,
username, password. Tombol **Test connection** mengecek tanpa menyimpan.
**Connect database** menguji koneksi, membuat database bila belum ada (butuh
hak `CREATEDB`), memigrasi schema, men-seed label default, lalu menulis
kredensial ke `.env`.

**2. Wazuh indexer** — host (boleh `https://host:port`), username, password,
dan status TLS. Matikan **Verify TLS** untuk sertifikat self-signed. Simpan
hanya berhasil kalau `_cluster/health` menjawab.

**3. Administrator** — nama, username (dipakai login), email opsional, password
(minimal 10 karakter, harus ada huruf, angka, simbol).

Setelah selesai, sesi direset dan kamu diarahkan ke halaman login.

### Instalasi non-interaktif

Kalau lebih suka menyiapkan lewat file (deployment otomatis):

```bash
git clone https://github.com/manarul04/smartlog-center.git
cd smartlog-center
composer install
cp .env.example .env
```

Isi `.env`:

```env
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=wazuh_logging
DB_USERNAME=postgres
DB_PASSWORD=secret

OPENSEARCH_HOST=https://wazuh-indexer.example.com
OPENSEARCH_PORT=9200
OPENSEARCH_USERNAME=admin
OPENSEARCH_PASSWORD=secret
OPENSEARCH_TLS_VERIFY=false
```

Lalu:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8124
```

Akun dari seeder: `admin` / `Admin#2026!secure`, `analyst` /
`Analyst#2026!secure`, `viewer` / `Viewer#2026!secure`. Ganti password setelah
login pertama lewat menu **Users**.

Wizard dilewati begitu tabel `users` berisi akun dan `OPENSEARCH_HOST` terisi.

## Instalasi via Docker

Dua container: **app** (PHP 8.4 + Apache) dan **postgres** (PostgreSQL 16).
Indexer Wazuh tidak ikut di-container — container hanya perlu rute jaringan ke
alamatnya. Tidak ada file yang perlu diedit: repo membawa `.env.docker` yang
di-mount ke `/var/www/html/.env`.

Butuh Docker Desktop dengan Compose v2 dan port `8124` kosong.

```bash
git clone https://github.com/manarul04/smartlog-center.git
cd smartlog-center
docker compose up -d --build
docker compose ps
```

Build pertama mengunduh `php:8.4-apache` dan `postgres:16-alpine`, lalu
`composer install` — perkirakan beberapa menit. Compose menunggu PostgreSQL
`healthy` sebelum menyalakan app.

Target akhir `docker compose ps`:

```
logging-app-1        Up   0.0.0.0:8124->80/tcp
logging-postgres-1   Up (healthy)
```

Buka `http://localhost:8124`, lalu isi wizard:

| Langkah | Isian |
|---|---|
| Database | Host `postgres` (**bukan** `127.0.0.1`), port `5432`, sisanya sudah terisi dari `.env.docker` |
| Password database | Ketik `postgres` — field ini sengaja selalu kosong saat halaman dimuat |
| Wazuh indexer | `https://<ip-wazuh>:9200`, matikan **Verify TLS** untuk self-signed |
| Administrator | Bebas |

Konfigurasi indexer yang diisi wizard disimpan di tabel `settings` dengan
password terenkripsi `APP_KEY`, **bukan** di `.env.docker`. Yang ditulis wizard
ke `.env.docker` hanya kredensial database, jadi `git status` akan terlihat
dirty setelah setup — itu normal. Jangan commit hasil tulisan wizard:

```bash
git checkout -- .env.docker
git update-index --skip-worktree .env.docker   # opsional: jangan pernah ter-commit
```

### Perintah harian

```bash
docker compose up -d
docker compose down
docker compose logs -f app
docker compose restart app
docker compose exec app php artisan tinker
docker compose exec postgres psql -U postgres -d wazuh_logging
```

### Catatan Docker

- Jangan ganti server container ke `php artisan serve`. Wizard menjalankan
  migrasi di tengah request, dan dev server single-threaded akan deadlock.
- `docker compose down` mempertahankan volume `pgdata` dan `app-storage`.
  **`docker compose down -v` menghapus semuanya**, termasuk database dan
  `APP_KEY` di `storage/framework/app-key`.
- Menghapus volume `app-storage` membuat `APP_KEY` dibuat ulang, sehingga
  password indexer di tabel `settings` tidak bisa didekripsi dan semua user
  ter-logout.
- Update: `git pull && docker compose up -d --build`.
- `ENV_FILE=.env docker compose up -d` menjalankan stack dengan `.env` host,
  bukan `.env.docker`.

### Troubleshooting

| Gejala | Penyebab dan solusi |
|---|---|
| `Bind for 0.0.0.0:8124 failed` | Port dipakai stack lain. `docker ps --filter publish=8124`, stop stack itu, atau ubah port di `docker-compose.yml` |
| Crash-loop, `.env` di host jadi direktori | Bind mount menunjuk file yang tidak ada. Hapus direktori itu, lalu `docker compose up -d` lagi |
| `500`, log: `could not be opened in append mode: Permission denied` | `storage/` dimiliki `root`, biasanya karena `docker compose exec app php artisan ...`. Perbaiki: `docker compose exec -u root app chown -R www-data:www-data storage bootstrap/cache` |
| `500` tepat setelah `up` | Config ter-cache. `docker compose exec app php artisan config:clear` |
| `fe_sendauth: no password supplied` | Field password wizard memang kosong. Ketik `postgres` |
| `password authentication failed for user "postgres"` | `DB_PASSWORD` di `.env.docker` beda dengan `DOCKER_DB_PASSWORD` saat volume dibuat. Samakan, atau `docker compose down -v` |
| `could not find driver` | Image bukan dari repo ini. `docker compose build --no-cache app` |
| `500` saat instalasi clone | `storage/` dan `bootstrap/cache/` tidak writable, atau `composer install` belum selesai |
| `TLS verification failed` | Sertifikat indexer self-signed. Matikan **Verify TLS** |
| `Credentials rejected (HTTP 401)` | User indexer salah, atau tidak punya role read ke index `wazuh-*` |
| Wizard muncul lagi padahal sudah setup | `OPENSEARCH_HOST` kosong atau tabel `users` kosong. Cek lalu `php artisan config:clear` |

## Perintah berguna

```bash
php artisan wazuh:test          # sanity check koneksi + query OpenSearch
php artisan wazuh:sync-kb       # isi katalog judul KB Windows
php artisan migrate:status
php artisan config:clear
php artisan view:clear
php artisan test
```

Test memakai database terpisah `wazuh_logging_test` (lihat `phpunit.xml`).
Buat sekali dengan `createdb -U postgres wazuh_logging_test`.

## Catatan produksi

- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Pastikan `storage/` dan `bootstrap/cache/` writable oleh user web server.
- Backup `storage/framework/app-key`. Tanpa file itu, password indexer yang
  tersimpan tidak bisa didekripsi dan sesi lama tidak valid.
- Pasang HTTPS di depan aplikasi (reverse proxy) agar cookie sesi aman.
- `php artisan config:cache` boleh dipakai, tapi setelah mengubah `.env`
  jalankan `config:clear` lalu cache ulang.
- Batasi akses `/setup` ke jaringan internal. Halaman itu otomatis terkunci
  setelah ada akun, tapi sebaiknya tidak diekspos saat instalasi.

## Data yang dibaca

- Alert: `wazuh-alerts-*` (rotasi harian)
- Status agent: `wazuh-monitoring-*` (jendela 30 menit)
- Vulnerability: `wazuh-states-vulnerabilities-*`
- Query dashboard di-cache 45 detik (`OPENSEARCH_CACHE_TTL`); cache key memuat
  fingerprint endpoint, jadi data cluster lama tidak muncul setelah URL diganti
