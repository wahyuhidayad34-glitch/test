@props(['status'])
@php
    $map = [
        'hadir' => ['Hadir', 'bg-emerald-100 text-emerald-700'],
        'terlambat' => ['Terlambat', 'bg-amber-100 text-amber-700'],
        'pending' => ['Menunggu', 'bg-amber-100 text-amber-700'],
        'approved' => ['Disetujui', 'bg-emerald-100 text-emerald-700'],
        'rejected' => ['Ditolak', 'bg-red-100 text-red-700'],
        'cancelled' => ['Dibatalkan', 'bg-slate-100 text-slate-600'],
        'draft' => ['Draft', 'bg-slate-100 text-slate-600'],
        'paid' => ['Dibayar', 'bg-emerald-100 text-emerald-700'],
        'active' => ['Aktif', 'bg-emerald-100 text-emerald-700'],
        'inactive' => ['Nonaktif', 'bg-red-100 text-red-700'],
    ];
    [$label, $class] = $map[$status] ?? [ucfirst($status), 'bg-slate-100 text-slate-600'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $class"]) }}>{{ $label }}</span>
