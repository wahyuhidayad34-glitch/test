# Struktur yang ditambahkan

ZIP awal hanya memuat sebagian kode aplikasi. Paket ini menambahkan fondasi Laravel yang diperlukan agar proyek dapat di-install sebagai proyek Laravel 13 normal:

- `artisan`
- `composer.json`
- `app/Http/Controllers/Controller.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/providers.php`
- konfigurasi Laravel inti di `config/`
- `public/index.php` dan `.htaccess`
- `routes/web.php` dan `routes/console.php`
- struktur `storage/`
- migration dasar `users`, `cache`, dan `jobs`
- `tests/TestCase.php`
- `phpunit.xml`
- model yang hilang tetapi sudah direferensikan kode aplikasi

## Tentang folder framework Laravel
Kode framework Laravel tidak seharusnya dicopy manual ke source project. Framework berada di:

`vendor/laravel/framework`

Folder tersebut dibuat otomatis saat menjalankan:

```powershell
composer install
```

Cara ini lebih aman karena versi framework dan dependency tetap konsisten dengan `composer.json`.
