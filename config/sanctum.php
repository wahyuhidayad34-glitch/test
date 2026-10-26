<?php

/*
| Konfigurasi parsial: nilai lain diambil dari default paket laravel/sanctum
| (dimerge otomatis). Di sini token dibuat kedaluwarsa setelah 8 jam.
| Ubah menjadi null bila ingin token tidak pernah kedaluwarsa.
*/
return [
    'expiration' => 60 * 8, // menit
];
