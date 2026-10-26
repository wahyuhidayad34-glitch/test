<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about-project', function () {
    $this->info('Salaam Purchasing Procurement Control API');
})->purpose('Menampilkan identitas aplikasi');
