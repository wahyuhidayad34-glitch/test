@extends('layouts.app')
@section('title', 'Absensi')

@section('content')
<h1 class="mb-4 text-2xl font-semibold">Absensi</h1>

@if ($employee)
<section class="mb-8 rounded-xl bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="text-sm text-slate-500">{{ now()->translatedFormat('l, d F Y') }} · jam masuk maksimal {{ config('hr.work_start') }}</div>
            @if ($today)
                <div class="mt-1 text-sm">Masuk <b>{{ substr($today->check_in, 0, 5) }}</b>
                    <x-badge :status="$today->status" class="ml-1" />
                    · Pulang <b>{{ $today->check_out ? substr($today->check_out, 0, 5) : '—' }}</b></div>
            @else
                <div class="mt-1 text-sm">Anda belum check-in hari ini.</div>
            @endif
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('hr.attendance.check-in') }}">@csrf
                <button @disabled($today) class="rounded-md bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">Check-in</button>
            </form>
            <form method="POST" action="{{ route('hr.attendance.check-out') }}">@csrf
                <button @disabled(!$today || $today->check_out) class="rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40">Check-out</button>
            </form>
        </div>
    </div>
</section>

<h2 class="mb-2 font-medium">Riwayat saya (31 hari terakhir)</h2>
<div class="mb-8 overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-100 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Masuk</th><th class="px-4 py-3">Pulang</th><th class="px-4 py-3">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($mine as $a)
            <tr>
                <td class="px-4 py-2">{{ $a->work_date->translatedFormat('D, d M Y') }}</td>
                <td class="px-4 py-2">{{ substr($a->check_in, 0, 5) }}</td>
                <td class="px-4 py-2">{{ $a->check_out ? substr($a->check_out, 0, 5) : '—' }}</td>
                <td class="px-4 py-2"><x-badge :status="$a->status" /></td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">Belum ada riwayat.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif

@if ($all !== null)
<div class="mb-2 flex flex-wrap items-center justify-between gap-2">
    <h2 class="font-medium">Absensi seluruh karyawan</h2>
    <form method="GET" class="flex gap-2">
        <input type="date" name="date" value="{{ $date->toDateString() }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm">
        <button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-100">Tampilkan</button>
    </form>
</div>
<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-100 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Karyawan</th><th class="px-4 py-3">Departemen</th><th class="px-4 py-3">Masuk</th><th class="px-4 py-3">Pulang</th><th class="px-4 py-3">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($all as $a)
            <tr>
                <td class="px-4 py-2">{{ $a->employee->user->name }}</td>
                <td class="px-4 py-2">{{ $a->employee->user->department }}</td>
                <td class="px-4 py-2">{{ substr($a->check_in, 0, 5) }}</td>
                <td class="px-4 py-2">{{ $a->check_out ? substr($a->check_out, 0, 5) : '—' }}</td>
                <td class="px-4 py-2"><x-badge :status="$a->status" /></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Tidak ada data absensi pada tanggal ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection
