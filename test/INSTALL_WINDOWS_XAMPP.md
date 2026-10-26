# Instalasi — Windows + XAMPP

Paket ini sudah dilengkapi struktur aplikasi Laravel 13 yang sebelumnya hilang. Folder `vendor/` tidak disertakan karena harus dibuat oleh Composer sesuai mesin pengguna.

## 1. Ekstrak proyek
Contoh:

```powershell
cd C:\xampp\htdocs\salam\hrssalim-laravel13-complete
```

## 2. Pasang dependency Laravel

```powershell
composer install
```

Perintah ini membuat `vendor/`, termasuk `vendor/laravel/framework`.

## 3. Siapkan `.env`
File `.env` dari paket lama tetap dipertahankan. Periksa bagian database dan sesuaikan nama database MySQL.

Jika APP_KEY masih kosong:

```powershell
php artisan key:generate
```

## 4. Bersihkan cache

```powershell
php artisan optimize:clear
```

## 5. Database
Untuk database TEST yang boleh di-reset:

```powershell
php artisan migrate:fresh --seed
```

Jangan gunakan `migrate:fresh` pada database produksi karena seluruh tabel akan dihapus.

## 6. Test otomatis

```powershell
php artisan test
```

## 7. Jalankan server

```powershell
php artisan serve
```

Buka `http://127.0.0.1:8000`.

Akun seeder memakai password `password`:
- admin@salaam.test
- director@salaam.test
- finance@salaam.test
- manager@salaam.test
- requester@salaam.test

## Catatan penting
- Aplikasi saat ini API-only; belum ada frontend web.
- `vendor/` sengaja tidak dimasukkan ke ZIP. Framework Laravel akan dipasang oleh `composer install`.
- Ditambahkan model yang direferensikan kode tetapi hilang dari ZIP awal: Vendor, PurchaseRequestItem, POItem, GRItem, InvoiceItem.
