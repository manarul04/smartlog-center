# Smart Log Center

Threat management dashboard untuk Wazuh, dibangun dengan Laravel. Data alert,
agent, vulnerability dan SCA dibaca langsung dari Wazuh indexer (OpenSearch),
sementara akun, triage, dan laporan disimpan di PostgreSQL. Tampilan dark/light,
tanpa build step (CSS/JS langsung dari `public/assets`).

## Daftar isi

- [Fitur](#fitur)
- [Kebutuhan](#kebutuhan)
- [Instalasi (5 menit)](#instalasi-5-menit)
- [Wizard setup](#wizard-setup)
- [Instalasi manual lewat .env](#instalasi-manual-lewat-env)
- [Setelah setup](#setelah-setup)
- [Menjalankan di Laragon](#menjalankan-di-laragon)
- [Konfigurasi backend](#konfigurasi-backend)
- [Troubleshooting installer](#troubleshooting-installer)
- [Modul](#modul)
- [Triage alert](#triage-alert)
- [Reports](#reports)
- [Role dan tema](#role-dan-tema)
- [Catatan data](#catatan-data)
- [Perintah berguna](#perintah-berguna)
- [Testing](#testing)
- [Struktur proyek](#struktur-proyek)
- [Catatan produksi](#catatan-produksi)

## Fitur

| Modul | Isi |
|---|---|
| Overview | KPI alert, tren per severity, top rule/agent, ringkasan triage |
| Alert Explorer | Pencarian, filter, paginasi, triage massal, export PDF |
| Threat Hunting | Matriks MITRE ATT&CK dan peta taktik |
| Agents & Health | Status agent, detail vulnerability dan SCA per agent |
| Compliance | PCI DSS, HIPAA, GDPR, NIST 800-53, TSC |
| Reports | Antrian laporan: incident, vulnerability, SCA |
| Users | Kelola akun, role, dan tema awal |
| Audit Log | Riwayat login, perubahan akun, perubahan backend |
| Configuration | Ubah koneksi Wazuh indexer tanpa menyentuh `.env` |

## Kebutuhan

- PHP 8.1 atau lebih baru dengan ekstensi `pdo_pgsql` (atau `pdo_mysql`), `curl`, `openssl`, `mbstring`
- Composer 2
- PostgreSQL 13+ (default) atau MySQL 8 / MariaDB 10.6+
- Akses HTTPS ke Wazuh indexer (biasanya port `9200`)
- Tidak perlu Node.js: CSS dan JS sudah ada di `public/assets`

Cek cepat:

```bash
php -v
composer -V
php -m | findstr pdo_pgsql
```

## Instalasi (5 menit)

```bash
git clone https://github.com/manarul04/smartlog-center.git
cd smartlog-center
composer install
php artisan serve --port=8124
```

Buka `http://127.0.0.1:8124`. Tidak perlu `cp .env.example .env`, tidak perlu
`php artisan key:generate`, dan tidak perlu `migrate` manual: semuanya
dikerjakan wizard.

Yang terjadi otomatis saat pertama kali dibuka:

1. `.env` dibuat dari `.env.example` bila belum ada.
2. `APP_KEY` dibuat dan disimpan di `storage/framework/app-key` (file ini
   diabaikan git). Kunci ini dipakai untuk enkripsi sesi dan password indexer.
3. Semua halaman dialihkan ke `/setup` karena database dan indexer belum diisi.

## Wizard setup

Wizard berjalan tiga langkah, dan setiap langkah hanya bisa lanjut setelah
koneksinya benar-benar tersambung.

### Langkah 1: Database

Isi engine (PostgreSQL atau MySQL), host, port, nama database, username, dan
password.

- Tombol **Test connection** mengecek koneksi tanpa menyimpan apa pun.
- Saat **Connect database** ditekan: koneksi diuji, database dibuat bila belum
  ada (butuh hak `CREATEDB` di PostgreSQL), schema dimigrasi, label default
  di-seed, lalu kredensial ditulis ke `.env`.
- Bila database sudah ada dan sudah punya schema, migrasi hanya melanjutkan
  yang belum jalan.

Setelah berhasil, penanda langkah 1 berubah menjadi centang dan halaman
menampilkan langkah 2.

### Langkah 2: Wazuh indexer

Isi host, port (`9200`), username, password, dan status TLS.

- Host boleh ditulis `https://` diikuti host indexer, atau `host:port`; skema
  `https://` ditambahkan otomatis dan port dipisah sendiri.
- **Test connection** memanggil `_cluster/health` ke endpoint tersebut.
- Jika sertifikat Wazuh self-signed, biarkan **Verify TLS certificate** mati.
- Simpan hanya berhasil kalau endpoint menjawab dan kredensialnya diterima.

### Langkah 3: Administrator

Isi nama, **username** (dipakai untuk login), email (opsional), dan password
(minimal 10 karakter, harus ada huruf, angka, dan simbol).

Setelah selesai, sesi direset dan Anda diarahkan ke halaman login dengan pesan
"Setup complete".

## Instalasi manual lewat .env

Kalau lebih suka menyiapkan lewat file (misalnya untuk deployment otomatis):

```bash
git clone https://github.com/manarul04/smartlog-center.git
cd smartlog-center
composer install
cp .env.example .env
```

Isi bagian ini di `.env`:

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

Wizard otomatis dilewati begitu tabel `users` sudah ada, `OPENSEARCH_HOST`
terisi, dan minimal satu akun tersedia. Kalau `OPENSEARCH_HOST` masih kosong,
halaman login tetap bisa dibuka, dan admin diarahkan ke `/setup` untuk
melengkapi koneksi indexer.

## Setelah setup

Login memakai **username** (email juga diterima untuk akun lama).

Akun dari seeder manual:

| Username | Password | Role |
|---|---|---|
| admin | `Admin#2026!secure` | Administrator |
| analyst | `Analyst#2026!secure` | Security Analyst |
| viewer | `Viewer#2026!secure` | Viewer |

Ganti password setelah login pertama lewat menu **Users**. Kalau memakai
wizard, akun yang Anda buat di langkah 3 adalah administrator dan seeder tidak
perlu dijalankan.

## Menjalankan di Laragon

1. Clone repo ke `D:\laragon\www\smartlog-center`.
2. Jalankan `composer install` dari terminal Laragon.
3. Arahkan virtual host (atau folder root) ke subfolder `public/`.
4. Buka domain tersebut, wizard akan muncul.

Kalau memakai `php artisan serve`, cukup:

```bash
php artisan serve --port=8124
```

## Konfigurasi backend

Menu **Configuration** (`/config`, khusus admin) ada di paling bawah sidebar.

| Field | Keterangan |
|---|---|
| Host | Skema + host indexer, port boleh ikut di sini |
| Port | Default `9200` |
| Username / Password | Basic auth; password kosong berarti pertahankan yang lama |
| Verify TLS | Matikan untuk sertifikat self-signed |

Nilai disimpan di tabel `settings` dan menimpa `OPENSEARCH_*` dari `.env` saat
boot. Password disimpan terenkripsi dengan `APP_KEY`. Tombol **Test now**
mengecek cluster health, dan **Reset to .env** menghapus semua override.

Menyimpan hanya berhasil setelah endpoint diuji, jadi salah password atau host
tidak akan tersimpan.

## Troubleshooting installer

| Gejala | Penyebab dan solusi |
|---|---|
| Halaman putih / 500 saat pertama dibuka | Pastikan `composer install` selesai dan folder `storage`, `bootstrap/cache` bisa ditulis. Jalankan `php artisan storage:link` bila perlu. |
| `could not find driver` | Ekstensi `pdo_pgsql` atau `pdo_mysql` belum aktif di `php.ini`. |
| `password authentication failed` | Username/password database salah. Di PostgreSQL cek juga `pg_hba.conf`. |
| `permission denied to create database` | User database tidak punya hak `CREATEDB`. Buat database manual lalu isi nama database yang sudah ada. |
| `TLS verification failed` | Sertifikat indexer self-signed. Matikan **Verify TLS certificate**, atau pasang CA bundle yang benar. |
| `Credentials rejected (HTTP 401)` | Username/password indexer salah, atau user tidak punya role read ke index `wazuh-*`. |
| `Cannot reach the endpoint` / timeout | Host atau port salah, firewall menutup `9200`, atau indexer hanya listen di localhost. |
| `The response is not an OpenSearch cluster health document` | URL mengarah ke Wazuh dashboard (443), bukan ke indexer (9200). |
| Wizard muncul lagi padahal sudah setup | Cek `OPENSEARCH_HOST` di `.env` tidak kosong dan tabel `users` berisi akun. Jalankan `php artisan config:clear`. |
| `.env` tidak bisa ditulis | Perbaiki permission file. Wizard menulis kredensial DB ke `.env`; bila ditolak, isi `.env` manual lalu ulangi. |
| Ingin mengulang wizard dari awal | Kosongkan `OPENSEARCH_HOST`, hapus isi tabel `settings`, dan pastikan tabel `users` kosong, atau jalankan `php artisan migrate:fresh`. |

Kalau `php artisan serve` dipakai, server membaca `.env` saat start. Aplikasi ini
membaca ulang `.env` setiap request, jadi hasil wizard langsung dipakai tanpa
restart. Untuk perubahan manual di `.env`, tetap disarankan restart server.

## Modul

| Modul | Route | Isi |
|---|---|---|
| Overview | `/` | KPI, tren alert per severity, top rule/grup/agent, ringkasan triage |
| Alert Explorer | `/explorer` | Pencarian, filter, paginasi, triage massal |
| Reports | `/reports` | Antrian terpusat: incident, vulnerability, SCA |
| Threat Hunting | `/threats` | Matriks MITRE ATT&CK, peta taktik |
| Agents & Health | `/agents` | Status agent, vulnerability, skor SCA |
| Compliance | `/compliance` | PCI DSS, HIPAA, GDPR, NIST 800-53, TSC |
| Users | `/users` | Kelola akun dan role (admin) |
| Audit Log | `/audit` | Riwayat login, perubahan akun, triage (admin) |
| Configuration | `/config` | Ubah koneksi Wazuh indexer tanpa edit `.env` (admin) |

## Triage alert

Status penanganan, label, assignee dan catatan disimpan di PostgreSQL; OpenSearch
tetap read-only. Alert dikenali lewat field `id` milik dokumen Wazuh, sehingga
anotasi bertahan saat index harian berotasi.

**Status alert:** Not Reviewed -> In Review -> In Progress -> Resolved / False Positive / Closed.
Alert tanpa anotasi dianggap `not_reviewed` dan ditampilkan muted.

**Label** (10 default dari `LabelSeeder`): True Positive, False Positive,
Suspicious, Malware, Brute Force, Lateral Movement, Privilege Escalation, Data
Exfiltration, Policy Violation, Needs Escalation.

**Alur kerja**

1. Buka **Alert Explorer**, klik baris alert mana pun.
2. Drawer kanan menampilkan detail lengkap: rule, agent, event message, event
   data, compliance mapping, dan raw JSON.
3. Di drawer: pilih status, assignee, label, tulis catatan, lalu **Save handling**.
4. Untuk banyak alert: centang baris di explorer, lalu **Apply to selected**.
5. Eskalasi: **Report Incident** di drawer (lihat bagian Grouped Incident).

Filter `status` dan `label` di explorer diterjemahkan menjadi batasan UID di
query OpenSearch, jadi total dan paginasi tetap akurat (bukan hanya memfilter
satu halaman).

## Reports

Satu menu **Reports** (`/reports`) menggantikan Incidents, Tickets, dan queue
vulnerability. Semua penanganan memakai satu tabel `tickets`, satu kosakata
status, dan satu timeline.

### Tiga jenis report

| Type | Dibuat dari | Item yang ditautkan |
|---|---|---|
| `incident` | Drawer alert, **New report** | alert uid + grouping rule/agent |
| `vulnerability` | Drawer temuan, **Create report** | agent + CVE + package |
| `sca` | Modal SCA check, **Create report** | agent + policy + check id |

Referensi berurutan: `RPT-000001`.

### Anti-duplikasi

`ticket_items` menyimpan identitas temuan dengan constraint unik
`(ticket_id, kind, ref_key)`. Sebelum membuat report, sistem mencari item dengan
`kind + ref_key` yang sama; kalau sudah ada, pengguna diarahkan ke report lama
dengan pesan "This finding already has report RPT-xxxxxx".

### Status tunggal

```
Not Reviewed -> Open -> Assigned -> In Review -> Remediating -> Verified Fixed -> Closed
                                                             -> Risk Accepted
                                                             -> False Positive
```

### Bagian kondisional di detail report

- `incident`: Tracked Alert Group (total alert live dari OpenSearch), Alerts in This Group
- `vulnerability`: CVE/CVSS/package, status scanner, **Verify against scanner**
- `sca`: check id, policy, remediation

Verifikasi vulnerability memanggil ulang OpenSearch: finding yang hilang saat
berstatus `remediating` otomatis menjadi **Verified Fixed** dengan `resolved_at`.

### Migrasi dari sistem lama

Tabel `incidents`, `incident_comments`, `vulnerability_reviews`,
`vulnerability_review_events`, dan `sca_check_states` dikonsolidasikan ke
`tickets` lalu dihapus. Status lama dipetakan:

| Lama | Baru |
|---|---|
| `acknowledged` | `in_review` |
| `accepted_risk` | `risk_accepted` |
| `resolved` | `verified` |
| `investigating` | `in_review` |
| `contained` | `remediating` |

URL lama `/tickets` dan `/incidents` dialihkan ke `/reports`.

## Export PDF

Tombol **Export PDF** di Alert Explorer menghasilkan laporan sesuai filter yang
sedang aktif. Judul, cakupan, dan rentang tanggal ikut menyesuaikan:

- Judul: `Security Alert Report`
- Cakupan: daftar filter aktif, mis. `Agent: dc1 · Severity: >= 7`
  (atau `All Alerts` bila tanpa filter)
- Periode: tanggal/jam konkret hasil konversi range relatif
  (mis. `13 Oct 2026 03:07 - 20 Oct 2026 03:07 (Last 7 days)`)
- Nama file: `alert-report_agent-dc1_20261013_20261020.pdf`

Isi laporan: KPI ringkas, distribusi severity, top agent & rule group, top rule,
dan tabel detail alert (300 terbaru; angka agregat mencakup seluruh hasil).
Laporan memuat logo perusahaan dan nama aplikasi di header serta footer.

Dependensi: `barryvdh/laravel-dompdf`. Logo dirender dari
`public/assets/img/logo.png`.

## Detail per agent: Vulnerability & SCA

Dari **Agents & Health** setiap angka bisa diklik untuk masuk ke daftar detailnya:

- Angka **Critical / High / Medium** pada tabel *Vulnerability Exposure* membuka
  `/agents/{id}/vulnerabilities?severity=Critical` (atau High/Medium)
- Angka **Failed** pada tabel *SCA Compliance Scores* membuka
  `/agents/{id}/sca?result=failed`
- Nama agent juga menjadi tautan ke halaman detail agent

### Vulnerability list

Filter severity, kategori, dan pencarian (CVE id, package, deskripsi).
Kolom: CVE, CVSS, severity, deskripsi + condition, package/versi, kategori.
Diurutkan berdasarkan CVSS tertinggi. Menampilkan 50 temuan per halaman.

### SCA checks + handling

Menampilkan hasil tiap check (bukan hanya ringkasan): check id, judul,
rationale, hasil (passed/failed/not applicable), dan policy.
Filter: periode (7/30/90 hari), result, policy, dan pencarian.

Tombol **Handle** membuka panel berisi deskripsi, remediation, command/directory
dari policy, lalu form untuk menetapkan:

| Field | Isi |
|---|---|
| Handling status | Open, Acknowledged, Remediating, Accepted Risk, Resolved, False Positive |
| Assignee | user yang bertanggung jawab |
| Note | rencana remediasi atau alasan pengecualian |

Status disimpan pada ticket terkait, sehingga tetap tersimpan meski Wazuh
melakukan rescan (document id OpenSearch berubah setiap scan).

### Catatan skor SCA

Satu agent bisa menjalankan beberapa policy (misalnya CIS benchmark dan policy
custom). Ringkasan memilih policy dengan **jumlah check terbanyak**, bukan yang
terbaru, agar policy kecil dengan skor 100% tidak menutupi policy CIS yang
punya ratusan kegagalan.

## Grouped Incident (total alert live)

Incident bisa dibuat langsung dari alert di drawer (**Report Incident**). Incident
menyimpan kunci pengelompokan, bukan salinan ribuan baris alert:

| Kolom | Isi |
|---|---|
| `group_agent` | agent sumber, mis. `dc2` |
| `group_rule_id` | rule sumber, mis. `60137` |
| `group_window_from` | awal penghitungan (tanggal pilihan atau timestamp alert) |
| `group_window_to` | batas akhir opsional |
| `group_counted_at` | kapan hitungan terakhir dijalankan |

**Total alerts dihitung live dari OpenSearch** dengan filter
`rule.id + agent.name + timestamp >= window_from`.

Halaman incident menampilkan:

- **Tracked Alert Group**: total alert, first/last seen, window, dan distribusi level
- **Alerts in This Group**: 50 alert terbaru dari grup tersebut
- Tombol **Refresh** untuk memperbarui cap waktu hitungan

Field tanggal **Count alerts from** di form report menentukan titik awal
penghitungan; kalau dikosongkan, dipakai timestamp alert sumber.

## Role dan tema

- **admin**: semua modul, kelola user, audit log, konfigurasi backend
- **analyst**: monitoring, hunting, triage, kelola laporan
- **viewer**: baca saja (aksi triage disembunyikan; endpoint POST mengembalikan 403)

Tombol di topbar beralih light/dark. Pilihan disimpan per akun (`users.theme`)
dan dicerminkan ke `localStorage` agar tidak ada kedipan saat pindah halaman.
Palet tema ada di `public/assets/css/app.css` (`[data-theme='dark']` dan
`[data-theme='light']`).

## Catatan data

- Index alert: `wazuh-alerts-*` (rotasi harian)
- Status agent: `wazuh-monitoring-*` (jendela 30 menit terakhir)
- Vulnerability: `wazuh-states-vulnerabilities-*`
- MITRE hanya muncul jika ruleset punya metadata ATT&CK; window 24 jam sering kosong
- Query dashboard di-cache 45 detik (`OPENSEARCH_CACHE_TTL`)
- Cache key memuat fingerprint endpoint, jadi data cluster lama tidak muncul
  setelah URL diganti

## Perintah berguna

```bash
php artisan wazuh:test          # sanity check koneksi + query OpenSearch
php artisan migrate:status      # cek migrasi
php artisan migrate:fresh --seed
php artisan config:clear
php artisan view:clear
php artisan test
```

## Testing

Test memakai database terpisah `wazuh_logging_test` (lihat `phpunit.xml`).
Buat sekali:

```bash
createdb -U postgres wazuh_logging_test
php artisan test
```

Cakupan: gate wizard (instalasi baru, penguncian `/setup`, pembuatan admin
pertama), pembacaan `.env`, dan smoke test halaman login.

## Struktur proyek

```
app/
  Http/Controllers/       Overview, Explorer, Reports, Agents, Config, Setup, ...
  Http/Middleware/        RequireSetup (gate wizard), Authenticate, EnsureRole
  Models/                 User, Ticket, Setting, Label, AuditLog, ...
  Services/               BackendConfig, DatabaseProbe, OpenSearch/*
  Support/                Setup, EnvFile, EnvWriter, AppKey, Severity
config/                   opensearch.php dan konfigurasi Laravel
database/migrations/      23 migrasi (users, tickets, settings, username, ...)
resources/views/          Blade: setup/, config/, overview/, explorer/, reports/, ...
public/assets/            CSS, JS, logo (tanpa build step)
routes/web.php            Semua route web
```

## Catatan produksi

- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Pastikan `storage/` dan `bootstrap/cache/` writable oleh user web server.
- Backup `storage/framework/app-key`: tanpa file itu, password indexer yang
  tersimpan tidak bisa didekripsi dan sesi lama tidak valid.
- Gunakan HTTPS di depan aplikasi (reverse proxy) agar cookie sesi aman.
- `php artisan config:cache` boleh dipakai, tetapi setelah mengubah `.env`
  jalankan `php artisan config:clear` lalu cache ulang.
- Batasi akses `/setup`: halaman itu otomatis terkunci setelah ada akun, tetapi
  sebaiknya tetap hanya bisa dijangkau dari jaringan internal saat instalasi.
