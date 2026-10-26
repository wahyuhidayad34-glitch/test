<?php

return [
    // Jam masuk kantor; check-in setelah jam ini dianggap terlambat
    'work_start' => '08:30',

    // Pembagi gaji harian (hari kerja per bulan) untuk potongan alpha
    'work_days_per_month' => 22,

    // Potongan BPJS karyawan (Kesehatan 1% + JHT 2%) dari gaji pokok
    'bpjs_rate' => 0.03,

    // Jatah cuti tahunan default untuk karyawan baru
    'annual_leave_quota' => 12,

    'employment_statuses' => ['tetap' => 'Karyawan Tetap', 'kontrak' => 'Kontrak', 'probation' => 'Probation'],

    'leave_types' => ['tahunan' => 'Cuti Tahunan', 'sakit' => 'Sakit', 'izin' => 'Izin'],
];
