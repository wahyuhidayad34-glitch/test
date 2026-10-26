# Paket Perbaikan – Salaam Purchasing (Laravel 13)

Zip ini adalah **overlay**: isinya hanya file yang diubah/ditambah, dengan struktur folder yang sama
seperti `purchasing-app`. Folder `vendor/` tidak ikut, jadi ukurannya kecil.

## Cara pasang (PowerShell)

```powershell
# 1. Ekstrak zip ini, lalu timpa ke proyek (sesuaikan path)
cd C:\xampp\htdocs\salam
Expand-Archive purchasing-app-fix.zip -DestinationPath . -Force
Copy-Item .\purchasing-app-fix\* .\purchasing-app -Recurse -Force

# 2. Hapus file SQL lama (sudah digantikan migration)
Remove-Item .\purchasing-app\database\migrations\purchasing_tables.sql

# 3. Pasang Sanctum
cd .\purchasing-app
composer require laravel/sanctum

# 4. Pakai .env baru (MySQL) lalu buat APP_KEY
copy .env.example .env
php artisan key:generate

# 5. Pastikan MySQL di XAMPP menyala & ekstensi pdo_mysql aktif (php.ini).
#    Buat database "purchasing" lewat phpMyAdmin
#    (atau biarkan `php artisan migrate` menawarkan membuatnya).
php artisan optimize:clear
php artisan migrate --seed

# 6. Jalankan
php artisan serve
```

Jangan jalankan `php artisan install:api` dan jangan impor `purchasing_tables.sql` lagi
(migration & route sudah disediakan di paket ini).

## Akun seeder (password semua: `password`)

| Role      | Email                 |
|-----------|-----------------------|
| admin     | admin@salaam.test     |
| director  | director@salaam.test  |
| finance   | finance@salaam.test   |
| manager   | manager@salaam.test   |
| requester | requester@salaam.test |

## Coba cepat (curl.exe di PowerShell)

```powershell
# login -> salin nilai "token"
curl.exe -X POST http://127.0.0.1:8000/api/login -H "Accept: application/json" -H "Content-Type: application/json" -d "{\"email\":\"requester@salaam.test\",\"password\":\"password\"}"

# buat PR
curl.exe -X POST http://127.0.0.1:8000/api/purchase-requests -H "Accept: application/json" -H "Content-Type: application/json" -H "Authorization: Bearer TOKEN" -d "{\"department\":\"IT\",\"required_date\":\"2026-10-31\",\"description\":\"Laptop\",\"items\":[{\"item_name\":\"Laptop\",\"quantity\":2,\"unit\":\"unit\",\"unit_price\":15000000}]}"

# submit  ->  POST /api/purchase-requests/1/submit
# approve ->  login sebagai manager, POST /api/purchase-requests/1/approve
```

Selalu kirim header `Accept: application/json`.

## Menjalankan test

```powershell
php artisan test
```
(Perlu ekstensi `pdo_sqlite` aktif di php.ini; test memakai SQLite in-memory.)

## Daftar perbaikan (nomor sesuai daftar bug)

| # | Perbaikan |
|---|-----------|
| 1 | `bootstrap/app.php` kini memuat `routes/api.php` |
| 2 | `HasApiTokens` di `User`, migration `personal_access_tokens`, `config/sanctum.php` (token kedaluwarsa 8 jam) |
| 3 | `ApprovalController` & `DashboardController` ditulis ulang lengkap dengan namespace & import |
| 4 | Import `AuditLog` dan audit log tidak lagi double-encode; `old_values` kini diambil sebelum update |
| 5 | Event `PRApproved`, `PRRejected`, `ApprovalNeeded` dibuat + listener log (`LogApprovalActivity`) |
| 6 | Relasi `ApprovalWorkflow::purchaseRequest()` ditambahkan |
| 7 | Kolom `deleted_at` ada di tabel `vendors` (via migration) |
| 8 | Semua tabel kini berupa **migration Laravel** (`php artisan migrate`), kolom `users` ditambah lewat migration |
| 9 | `.env.example` memakai MySQL, session/cache file, queue sync |
| 10 | `DatabaseSeeder` membuat user & vendor; `User` memakai cast `hashed`; `UserFactory` diperbarui |
| 11 | Approval **berurutan**: level N hanya bisa bertindak setelah level di bawahnya approved |
| 12 | PR ≥ 500 jt selesai hanya setelah Director approve. Status final kini `approved` |
| 13 | Approve/reject memeriksa status PR & status approval (tidak bisa diproses dua kali) |
| 14 | PR `rejected` bisa diedit (kembali `draft`) lalu di-submit ulang |
| 15 | Requester tidak bisa menjadi approver/memproses PR-nya sendiri |
| 16 | `level` di request kini opsional; level ditentukan dari giliran user. Error bisnis → 422/403, bukan 500 |
| 17 | Nomor PR/PO/GR/Invoice memakai nomor terbesar + lock (tidak duplikat setelah hapus data) |
| 18 | Validasi `update()` → 422; semua field item divalidasi & tidak hilang |
| 20 | Dashboard mengenal Director, angka dibatasi sesuai hak lihat, status final dihitung benar |
| 21 | Otorisasi: requester hanya lihat miliknya; hapus draft hanya pemilik/admin |
| 22 | Tidak ada lagi `catch (Exception)` yang membocorkan pesan; error 500 dicatat di log |
| 23 | Rate limit login (10/menit), token kedaluwarsa |
| 24 | `per_page` dibatasi 1–100 |
| 25 | Route `/api/me` ditambahkan; `/api/test` hanya di environment local |
| 26 | `?string $comments` (kompatibel PHP 8.4) |
| 27 | Event dikirim **setelah commit** (`DB::afterCommit`) |
| 28 | Baris kosong sebelum `<?php` dan tag `?>` penutup dihapus |

## Perubahan perilaku yang perlu diketahui

- Status PR final sekarang **`approved`** (dulu `approved_finance`). `approved_finance` hanya status antara
  untuk PR ≥ 500 jt yang menunggu Director. Kolom `status` di migration sudah memuatnya.
- Hak lihat PR: admin semua; finance/director semua yang sudah di-submit; manager miliknya + departemennya +
  yang ia approve; requester hanya miliknya.
- Notifikasi approver berikutnya dikirim setelah level sebelumnya selesai (bukan semua sekaligus).
