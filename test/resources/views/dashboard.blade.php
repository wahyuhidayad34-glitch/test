@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<h1 class="mb-1 text-2xl font-semibold">Halo, {{ auth()->user()->name }}</h1>
<p class="mb-6 text-sm text-slate-500">{{ now()->translatedFormat('l, d F Y') }}</p>

@if (isset($stats))
    <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach ([
            ['Karyawan aktif', $stats['total_employees']],
            ['Hadir hari ini', $stats['present_today']],
            ['Terlambat', $stats['late_today']],
            ['Sedang cuti', $stats['on_leave_today']],
            ['Cuti menunggu', $stats['pending_leaves']],
        ] as [$label, $value])
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="text-2xl font-semibold">{{ $value }}</div>
                <div class="text-xs text-slate-500">{{ $label }}</div>
            </div>
        @endforeach
    </div>
@elseif (isset($pendingTeamLeaves))
    <a href="{{ route('hr.leaves.index') }}" class="mb-8 block rounded-xl bg-amber-50 p-4 text-sm text-amber-800 hover:bg-amber-100">
        {{ $pendingTeamLeaves }} pengajuan cuti tim menunggu persetujuan Anda.
    </a>
@endif

@if ($employee)
<div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-medium">Absensi hari ini</h2>
        @if ($todayAttendance)
            <p class="text-sm">Masuk: <b>{{ substr($todayAttendance->check_in, 0, 5) }}</b>
                <x-badge :status="$todayAttendance->status" class="ml-1" /></p>
            <p class="text-sm">Pulang: <b>{{ $todayAttendance->check_out ? substr($todayAttendance->check_out, 0, 5) : '—' }}</b></p>
        @else
            <p class="text-sm text-slate-500">Belum check-in.</p>
        @endif
        <a href="{{ route('hr.attendance.index') }}" class="mt-3 inline-block text-sm text-slate-900 underline">Buka absensi</a>
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-medium">Cuti saya</h2>
        <p class="text-sm">Sisa cuti tahunan {{ now()->year }}: <b>{{ $remainingLeave }}</b> dari {{ $employee->leave_quota }} hari</p>
        <ul class="mt-3 space-y-1 text-sm">
            @forelse ($myRecentLeaves as $l)
                <li class="flex items-center justify-between">
                    <span>{{ $l->type_label }} · {{ $l->start_date->translatedFormat('d M') }}–{{ $l->end_date->translatedFormat('d M Y') }}</span>
                    <x-badge :status="$l->status" />
                </li>
            @empty
                <li class="text-slate-500">Belum ada pengajuan.</li>
            @endforelse
        </ul>
        <a href="{{ route('hr.leaves.create') }}" class="mt-3 inline-block text-sm text-slate-900 underline">Ajukan cuti</a>
    </section>
</div>
@else
<p class="text-sm text-slate-500">Akun ini belum terhubung ke data karyawan.</p>
@endif
@endsection
