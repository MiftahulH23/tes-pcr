# Akademik KRS — Single Page CRUD

Aplikasi pengelolaan KRS (Kartu Rencana Studi) mahasiswa: satu halaman untuk create/read/update/delete data `enrollments`, dengan pagination, sorting, filtering, dan pencarian yang seluruhnya dieksekusi server-side agar tetap responsif pada dataset 5 juta+ baris.

Dibangun untuk Tes Teknis Web Developer (Full Stack) Q3 2026.

## Tech stack

| Layer | Pilihan |
|---|---|
| Backend | Laravel 13 |
| Frontend | React 19 + Inertia.js (satu aplikasi, tanpa REST API terpisah untuk navigasi halaman) |
| UI Components | shadcn/ui (Radix UI primitives + Tailwind CSS), bawaan dari `laravel/react-starter-kit` |
| Database | PostgreSQL, termasuk fitur `pg_trgm` untuk pencarian cepat |
| Build tool | Vite |

## Struktur fitur

- **Skema**: `students`, `courses`, `enrollments` (FK ke keduanya), lihat `database/migrations/`.
- **Create**: `app/Actions/Enrollments/CreateEnrollment.php` — upsert student/course berdasarkan `nim`/`code` lalu insert enrollment, seluruhnya dalam **1 DB transaction** (`DB::transaction`). Jika salah satu insert gagal, semua rollback.
- **Read (tabel data)**: `app/Http/Controllers/EnrollmentController@data` + `app/Support/EnrollmentFilters.php` — query builder terpusat untuk pagination, sort, quick filter, advanced filter (AND/OR), dan live search.
- **Update**: `app/Actions/Enrollments/UpdateEnrollment.php` — bisa sekaligus ubah nama/email student dan nama/credits course terkait.
- **Delete**: soft delete (`Enrollment` pakai trait `SoftDeletes`) — lihat bagian [Keputusan Desain](#keputusan-desain).
- **Export CSV**: `app/Actions/Enrollments/ExportEnrollmentsCsv.php` — streaming response, tidak memuat seluruh dataset ke memori (lihat [Strategi Performa](#strategi-performa)).
- **Seeder 5 juta baris**: `app/Console/Commands/SeedEnrollments.php`.

## Setup Local

### Prasyarat

- PHP 8.4+ dengan ekstensi `pdo_pgsql` dan `pgsql` aktif
- Composer
- Node.js 20+ dan npm
- PostgreSQL 16+ dengan ekstensi `pg_trgm` (biasanya sudah tersedia, tinggal di-`CREATE EXTENSION`)

### 1. Clone & install dependency

```bash
git clone <url-repo-ini>
cd tes-pcr
composer install
npm install
```

### 2. Konfigurasi environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`, sesuaikan kredensial database:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=tes_pcr
DB_USERNAME=tes_pcr
DB_PASSWORD=isi_password_kamu
```

### 3. Buat database & user PostgreSQL

```sql
CREATE ROLE tes_pcr LOGIN PASSWORD 'isi_password_kamu';
CREATE DATABASE tes_pcr OWNER tes_pcr;
```

### 4. Jalankan migration

```bash
php artisan migrate
```

Migration ini juga mengaktifkan ekstensi `pg_trgm` dan membuat index GIN trigram untuk pencarian cepat (lihat `database/migrations/*_add_trigram_search_indexes.php`).

### 5. Build asset frontend

```bash
npm run build
```

### 6. Jalankan aplikasi

Untuk sekadar menjalankan/testing (tidak sedang edit kode frontend):

```bash
php artisan serve
```

Untuk mode development (hot-reload frontend, queue listener, log viewer sekaligus):

```bash
composer run dev
```

> **Catatan Windows**: panel "logs" pada `composer run dev` (Laravel Pail) akan selalu gagal di Windows karena butuh ekstensi `pcntl` yang tidak tersedia di PHP Windows. Ini normal, abaikan saja — panel `server` dan `vite` tetap berjalan normal.

Buka `http://localhost:8000/enrollments`.

## Seeding 5 juta baris

Seeder ini generate `students`, `courses`, dan `enrollments` sekaligus, dengan bulk insert ber-batch (bukan satu-satu) supaya cepat.

```bash
php artisan academic:seed-enrollments --fresh
```

Opsi yang tersedia (semua opsional, sudah ada default-nya):

| Opsi | Default | Keterangan |
|---|---|---|
| `--count` | `5000000` | Jumlah baris `enrollments` yang ingin dicapai |
| `--students` | `200000` | Jumlah baris `students` yang di-generate |
| `--courses` | `400` | Jumlah baris `courses` yang di-generate |
| `--chunk` | `5000` | Baris per batch insert |
| `--fresh` | — | Kosongkan tabel `students`/`courses`/`enrollments` dulu sebelum seeding |

Contoh seeding skala kecil untuk development cepat:

```bash
php artisan academic:seed-enrollments --fresh --count=50000 --students=5000 --courses=100
```

Estimasi waktu untuk 5 juta baris di laptop development: **~20-25 menit** (tergantung spesifikasi mesin dan disk).

### Membuktikan jumlah data

```bash
php artisan tinker --execute="echo \App\Models\Enrollment::count();"
```

Atau langsung ke database:

```sql
SELECT COUNT(*) FROM enrollments;
```

## Menjalankan test

Test suite jalan di database PostgreSQL terpisah (`tes_pcr_testing`, dikonfigurasi di `phpunit.xml`) supaya tidak menyentuh data development/seeded kamu. Buat dulu database-nya sekali:

```sql
CREATE DATABASE tes_pcr_testing OWNER tes_pcr;
```

Lalu jalankan:

```bash
php artisan test
```

> Test suite yang ada saat ini adalah bawaan starter kit (autentikasi, profile settings) — belum ada test khusus fitur KRS karena fokus waktu diarahkan ke fungsionalitas dan performa di skala 5 juta baris.

## API / Routes

| Method | Path | Keterangan |
|---|---|---|
| GET | `/enrollments` | Halaman utama (Inertia) |
| GET | `/enrollments/data` | JSON endpoint untuk tabel: pagination, sort, filter, search |
| POST | `/enrollments` | Create (insert 3 tabel dalam 1 transaksi) |
| PUT | `/enrollments/{id}` | Update |
| DELETE | `/enrollments/{id}` | Soft delete |
| GET | `/enrollments/export` | Export CSV (streaming, respects filter aktif) |

### Parameter `/enrollments/data`

| Parameter | Contoh | Keterangan |
|---|---|---|
| `page`, `page_size` | `page=1&page_size=20` | Pagination |
| `q` | `q=Ahmad` | Live search: NIM, nama mahasiswa, kode MK |
| `status[]`, `semester[]` | `status[]=APPROVED&status[]=DRAFT` | Quick filter (multi-select, digabung OR) |
| `sort` | `sort=[{"field":"academic_year","dir":"desc"},{"field":"status","dir":"asc"}]` | Multi-column sort (JSON) |
| `filters` | `filters={"logic":"and","conditions":[{"field":"status","op":"equal","value":"APPROVED"}]}` | Advanced filter (JSON) |

Kolom yang bisa di-filter/sort/search: `student_nim`, `student_name`, `course_code`, `course_name`, `semester`, `academic_year`, `status` (whitelist di `EnrollmentFilters::COLUMNS`, mencegah injeksi nama kolom sembarangan).

Operator advanced filter yang didukung: `contains`, `startsWith`, `equal`, `in`, `between` (lihat `app/Support/EnrollmentFilters.php`).

### Postman Collection

Koleksi request siap-pakai (list dengan pagination/sort/filter/search, create valid & invalid, duplicate check, update, delete, export) ada di [`docs/postman_collection.json`](docs/postman_collection.json). Import ke Postman/Insomnia, lalu set variable `base_url` ke `http://localhost:8000` (local) atau `https://tes.miftahulhuda.site` (production).

## Keputusan Desain

- **Soft delete untuk enrollments**: dipilih dibanding hard delete supaya histori KRS tidak hilang permanen (bisa dipulihkan langsung dari database bila diperlukan, misal salah hapus). Data `students`/`courses` tidak ikut ter-cascade delete ketika enrollment dihapus.
- **Create = upsert by nim/code**: form Create selalu menerima data lengkap student+course+enrollment. Jika `nim`/`code` sudah ada di database, record yang sudah ada dipakai ulang (data yang diketik untuk field itu diabaikan); jika belum ada, dibuat baru. Ini memenuhi syarat "3 tabel terlibat dalam 1 transaksi atomic" tanpa perlu toggle UI "pilih existing vs buat baru".
- **Update bisa sekaligus ubah data student/course**: form Update menyediakan field nama/email (student) dan nama/credits (course) sebagai opsional — kalau diisi, ikut ter-update dalam transaksi yang sama. Field identitas (`nim`, `course.code`) sengaja tidak bisa diubah dari form Update untuk menghindari perubahan identitas yang bisa merusak integritas riwayat KRS mahasiswa/mata kuliah lain yang memakai record yang sama.
- **Advanced filter AND/OR**: diimplementasikan sebagai **satu grup kondisi** dengan satu operator logika (AND atau OR) yang berlaku untuk semua kondisi dalam grup itu, bukan pohon logika bersarang. Backend (`EnrollmentFilters::applyAdvanced`) menerima struktur yang mudah diperluas ke group bersarang di masa depan, tapi UI saat ini hanya mengekspos satu level karena itu yang paling umum dibutuhkan untuk kasus penggunaan KRS.
- **Search & filter case-insensitive**: pencarian dan filter teks (`contains`, `startsWith`, `equal` pada kolom nama/kode) menggunakan `ILIKE` PostgreSQL. Untuk kolom enum (`status`, `semester`) yang juga dipakai untuk sorting/index, case-insensitivity dilakukan dengan menormalisasi **nilai input** ke uppercase (bukan membungkus kolom dengan `LOWER()`) — lihat [Strategi Performa](#strategi-performa) kenapa ini penting.

## Strategi Performa

Sistem ini diuji langsung di dataset **5.003.807 baris** enrollments (+200.000 students, 400 courses). Beberapa keputusan performa signifikan:

### Index

- Index B-tree standar pada `enrollments.status`, `enrollments.semester`, `enrollments.academic_year`, dan composite index `(academic_year, semester, student_id)` untuk skenario multi-column sort.
- **Index GIN trigram (`pg_trgm`)** pada `students.nim`, `students.name`, `courses.code`, `courses.name` — tanpa ini, `LIKE '%kata%'` (perlu untuk live search & advanced filter `contains`) akan melakukan **full table scan**. Dengan index ini, pencarian substring pada 5 juta baris turun dari ~2.6 detik menjadi ~150ms.

### Query design

- **COUNT tanpa join kalau tidak perlu**: query pagination butuh `COUNT(*)` untuk total halaman. Meng-`COUNT` hasil JOIN 3 tabel tanpa filter di kolom join itu mahal (±2 detik untuk 5 juta baris) walau ada index, karena Postgres tetap harus scan semua baris untuk join+hitung. `EnrollmentFilters::needsJoin()` mendeteksi apakah request benar-benar butuh join (yaitu ketika **sort** menyentuh kolom `student_*`/`course_*`); kalau tidak, COUNT dijalankan tanpa join sama sekali → turun ke ~400ms.
- **Filter ke kolom relasi via subquery, bukan JOIN**: search dan advanced filter pada `student_nim`/`student_name`/`course_code`/`course_name` diimplementasikan sebagai `WHERE enrollments.student_id IN (SELECT id FROM students WHERE ...)`, bukan `JOIN students ... WHERE students.x LIKE ...`. Dengan JOIN, planner PostgreSQL cenderung memilih nested-loop per-baris (lambat, bisa >10 detik untuk kombinasi filter+sort tertentu). Dengan subquery, planner bisa pakai index trigram di tabel `students`/`courses` secara independen lalu semi-join hasilnya — jauh lebih cepat.
- **Jangan bungkus kolom terindeks dengan fungsi**: awalnya filter `status`/`semester` case-insensitive dibuat dengan `WHERE LOWER(status) IN (...)`. Ini ternyata merusak estimasi row Postgres pada kolom yang juga dipakai untuk `ORDER BY`, membuat planner memilih rencana nested-loop yang sangat lambat (kombinasi quick filter + sort + advanced filter naik dari ~800ms jadi ~10 detik). Solusinya: normalisasi **nilai** ke uppercase di PHP sebelum query (karena `status`/`semester` adalah enum yang selalu disimpan uppercase oleh validasi), sehingga kolom tetap bisa memakai index apa adanya.
- **`fputcsv` dengan parameter eksplisit**: PHP 8.4 mendeprecate `fputcsv()` tanpa parameter `$escape` eksplisit. Dengan `APP_DEBUG=true`, deprecation notice yang muncul di **setiap baris** (jutaan kali) ternyata jadi bottleneck nyata — throughput export naik dari ~4.600 baris/detik menjadi ~16.700 baris/detik setelah parameter itu dieksplisitkan.

### Export CSV

Export tidak memuat seluruh dataset ke memori. `ExportEnrollmentsCsv` memakai `lazyById()` Laravel — iterasi memakai `WHERE id > id_terakhir ORDER BY id LIMIT n` per batch (bukan `OFFSET`), lalu langsung stream tiap baris ke response tanpa menahan baris sebelumnya di memori. Diverifikasi: penggunaan memori PHP tetap **di bawah 60MB** sepanjang proses export 5 juta baris, dan export tetap menghasilkan file yang benar (byte-identik) di setiap percobaan.

### Keterbatasan yang diketahui

- **Sort by kolom relasi (`student_name`/`course_name`) tetap lambat (~2-2.5 detik)** karena membutuhkan JOIN sungguhan across seluruh tabel untuk `ORDER BY` — tidak bisa dihindari dengan subquery seperti filter. Solusi produksi: denormalisasi kolom tampilan (`student_name`, `course_code`) langsung ke tabel `enrollments` (di-update via trigger/event saat data terkait berubah), sehingga sort tidak perlu join sama sekali. Tidak diimplementasikan di sini karena menambah kompleksitas sinkronisasi data yang di luar scope waktu tes.
- **Pagination `OFFSET` melambat di halaman yang sangat jauh** (halaman ~200.000 dari total ~250.000 bisa memakan ±5 detik) — karakteristik umum `LIMIT/OFFSET` di database manapun untuk dataset besar. Solusi produksi: keyset/cursor pagination (`WHERE id < id_terakhir`), namun ini mengorbankan kemampuan lompat ke nomor halaman sembarang yang disediakan UI tabel data saat ini.
- **`php artisan serve` (dev server bawaan PHP) memproses request secara terbatas** dibanding PHP-FPM di produksi. Untuk local development yang lebih stabil saat beberapa fitur diuji bersamaan (misal export berjalan sambil create data), jalankan dengan `php artisan serve --no-reload` (mengaktifkan `PHP_CLI_SERVER_WORKERS=4` yang sudah diset di `.env`) — ini juga default yang dipakai `composer run dev`.

## Keamanan & Observability

- **CORS**: tidak ada konfigurasi CORS eksplisit, dan ini disengaja — aplikasi ini monolith Inertia (frontend dirender oleh backend yang sama, satu origin), bukan REST API terpisah yang diakses dari domain lain. Tidak ada request cross-origin yang perlu diizinkan, jadi CORS tidak relevan untuk arsitektur ini.
- **SQL injection**: seluruh query memakai Eloquent/Query Builder dengan parameter binding (tidak ada raw SQL dengan interpolasi string), termasuk kolom dinamis untuk sort/filter yang divalidasi lewat whitelist (`EnrollmentFilters::COLUMNS`) sebelum dipakai di query.
- **Request logging**: middleware `App\Http\Middleware\LogRequests` mencatat setiap request (method, path, status code, durasi, IP) ke log channel default (`storage/logs/laravel.log`) untuk observability dasar.

## Deployment

Aplikasi live di **https://tes.miftahulhuda.site**.

### Infrastruktur

| Komponen | Pilihan |
|---|---|
| Server | VPS (Ubuntu), path project di `/var/www/tes-pcr` |
| Web server | Nginx (reverse proxy) + PHP-FPM (`php8.4-fpm`) |
| Database | PostgreSQL (setup sama seperti [Setup Local](#setup-local)) |
| SSL | Let's Encrypt (Certbot) |
| Firewall | `ufw`, hanya port 22 (SSH), 80 (HTTP), 443 (HTTPS) yang terbuka |

### CI/CD — Auto-deploy

Setiap push ke branch `main` yang lolos test suite (`.github/workflows/tests.yml`) otomatis di-deploy lewat `.github/workflows/deploy.yml`:

1. Workflow `deploy` menunggu workflow `tests` selesai dengan status sukses pada branch `main` (`workflow_run` trigger).
2. GitHub Actions SSH ke VPS (pakai [appleboy/ssh-action](https://github.com/appleboy/ssh-action)) dan menjalankan:
   ```bash
   git fetch origin main
   git reset --hard origin/main
   composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
   npm ci
   npm run build
   php artisan migrate --force
   php artisan optimize
   sudo systemctl restart php8.4-fpm
   ```

**Secrets yang harus diisi di GitHub (Settings → Secrets and variables → Actions):**

| Secret | Keterangan |
|---|---|
| `VPS_HOST` | IP atau hostname VPS |
| `VPS_USERNAME` | User SSH (mis. `raul`) |
| `VPS_SSH_KEY` | Private key SSH (format PEM) yang public key-nya sudah ada di `~/.ssh/authorized_keys` VPS |
| `VPS_PORT` | Opsional, default `22` |

**Prasyarat di VPS agar deploy tidak gagal:**

- User deploy harus bisa `sudo systemctl restart php8.4-fpm` **tanpa password** (tambahkan baris berikut lewat `sudo visudo`):
  ```
  raul ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php8.4-fpm
  ```
- File `.env` di server dikonfigurasi manual sekali (tidak ikut ter-commit/pull) dengan `APP_ENV=production`, `APP_DEBUG=false`, dan kredensial database produksi.
- Ekstensi `pg_trgm` dan migration sudah dijalankan sekali secara manual di database produksi sebelum aktivasi CI/CD pertama kali.

### Firewall

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

PostgreSQL tidak dibuka ke publik — hanya diakses via `localhost` oleh aplikasi di server yang sama.

## Catatan Stack & Asumsi

- Autentikasi (login/register) tersedia dari starter kit Laravel namun **tidak digunakan** untuk fitur KRS — halaman `/enrollments` dapat diakses tanpa login.
- Validasi format: NIM 8-12 digit, kode MK `[A-Z]{2,4}[0-9]{3}`, tahun ajaran `YYYY/YYYY` dengan tahun kedua = tahun pertama + 1, credits 1-6, unique constraint `(student_id, course_id, academic_year, semester)`.
- Pesan validasi dan error UI dalam Bahasa Indonesia (`lang/id/validation.php`, `APP_LOCALE=id`).
