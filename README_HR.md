# Modul HR — HRS Salim

Modul HR ini ditambahkan di atas proyek Laravel 13 yang sudah ada. Kode Purchasing (API `/api/*`) **tidak diubah** dan tetap berjalan.
Tampilan web HR memakai Blade + Tailwind (via CDN, jadi perlu koneksi internet saat membuka halaman).

## Fitur

| Menu | Fungsi | Siapa yang bisa |
|------|--------|-----------------|
| Dashboard | Ringkasan hari ini (HR), status absensi & sisa cuti (karyawan) | semua |
| Karyawan | Tambah / ubah / cari / aktif–nonaktifkan karyawan, gaji pokok, tunjangan, jatah cuti | admin, hr |
| Absensi | Check-in / check-out, terlambat otomatis (setelah `hr.work_start`), rekap harian | semua (rekap: admin, hr) |
| Cuti & Izin | Ajukan cuti tahunan / sakit / izin, hitung hari kerja, cek sisa kuota & bentrok tanggal, setujui / tolak / batalkan | semua; persetujuan: atasan langsung, admin, hr |
| Penggajian | Generate slip bulanan, tandai dibayar, cetak slip | generate: admin, hr · lihat semua: + finance, director · karyawan: slip miliknya yang sudah dibayar |

Aturan penting:
- Karyawan tidak bisa menyetujui cuti miliknya sendiri.
- Manager hanya bisa memproses cuti bawahan langsungnya (`users.manager_id`); admin/hr bisa semua.
- Karyawan tidak dihapus, hanya dinonaktifkan (akun nonaktif tidak bisa login, riwayat tetap ada).
- Rumus gaji (`app/Services/PayrollService.php`): `gross = gaji pokok + tunjangan`, `BPJS = 3% × gaji pokok`,
  `potongan alpha = hari alpha × gaji pokok / 22`, `diterima = gross − BPJS − potongan alpha`.
  Alpha = hari kerja (Senin–Jumat) tanpa absensi dan tanpa cuti/izin yang disetujui. **Belum termasuk PPh 21.**
  Angka-angkanya bisa diubah di `config/hr.php`.

## Cara pasang (PowerShell, XAMPP)

```powershell
# 1. Ekstrak zip ini ke folder proyek hrssalim, timpa file yang sama
cd C:\xampp\htdocs\salam\hrssalim

# 2. Pastikan dependency ada (folder vendor sudah ada di proyek Anda; jika belum:)
composer install

# 3. Database: tabel HR baru + data contoh
php artisan optimize:clear
php artisan migrate --seed
#   (database TEST yang boleh di-reset:  php artisan migrate:fresh --seed )

# 4. Jalankan
php artisan serve
```

Buka http://127.0.0.1:8000 lalu login. Password semua akun contoh: `password`

| Role | Email |
|------|-------|
| HR | hr@salaam.test |
| Admin | admin@salaam.test |
| Manager | manager@salaam.test |
| Karyawan | requester@salaam.test |
| Finance | finance@salaam.test |
| Direktur | director@salaam.test |

Ganti semua password contoh sebelum dipakai sungguhan.

## Test

```powershell
php artisan test
```
Ada `tests/Feature/HrFlowTest.php` (login, hak akses, absensi, alur cuti, payroll, tambah karyawan) di samping test Purchasing yang lama.

## File yang ditambah / diubah

Baru: `config/hr.php`, migration `2026_09_25_100001_create_hr_tables`, model `Employee`, `Attendance`, `LeaveRequest`, `Payroll`,
`app/Services/PayrollService.php`, `app/Http/Middleware/EnsureRole.php`, `app/Http/Controllers/Hr/*`, `resources/views/*`, `tests/Feature/HrFlowTest.php`.

Diubah: `routes/web.php` (halaman `/` kini masuk ke dashboard HR, bukan JSON), `bootstrap/app.php` (alias middleware `role`, redirect login),
`app/Models/User.php` (relasi `employee`, helper `isHr`/`isHrStaff`), `app/Providers/AppServiceProvider.php` (locale tanggal Indonesia), `database/seeders/DatabaseSeeder.php`.
