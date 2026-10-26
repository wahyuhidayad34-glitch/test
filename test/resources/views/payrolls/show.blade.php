@extends('layouts.app')
@section('title', 'Slip Gaji')

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.'); @endphp
<div class="mb-4 flex items-center justify-between print:hidden">
    <a href="{{ route('hr.payrolls.index', ['period' => $payroll->period]) }}" class="text-sm underline">← Kembali</a>
    <button onclick="window.print()" class="rounded-md border border-slate-300 bg-white px-4 py-1.5 text-sm hover:bg-slate-100">Cetak</button>
</div>

<div class="max-w-xl rounded-xl bg-white p-8 shadow-sm">
    <div class="mb-6 flex items-start justify-between">
        <div>
            <div class="text-lg font-semibold">Slip Gaji</div>
            <div class="text-sm text-slate-500">Periode {{ \Carbon\Carbon::createFromFormat('Y-m-d', $payroll->period.'-01')->translatedFormat('F Y') }}</div>
        </div>
        <x-badge :status="$payroll->status" />
    </div>

    <dl class="mb-6 grid grid-cols-3 gap-y-1 text-sm">
        <dt class="text-slate-500">Nama</dt><dd class="col-span-2">{{ $payroll->employee->user->name }}</dd>
        <dt class="text-slate-500">NIK</dt><dd class="col-span-2">{{ $payroll->employee->employee_number }}</dd>
        <dt class="text-slate-500">Departemen</dt><dd class="col-span-2">{{ $payroll->employee->user->department }}</dd>
        <dt class="text-slate-500">Jabatan</dt><dd class="col-span-2">{{ $payroll->employee->position ?? '—' }}</dd>
    </dl>

    <table class="w-full text-sm">
        <tbody class="divide-y divide-slate-100">
            <tr><td class="py-2">Gaji pokok</td><td class="py-2 text-right">{{ $rp($payroll->basic_salary) }}</td></tr>
            <tr><td class="py-2">Tunjangan tetap</td><td class="py-2 text-right">{{ $rp($payroll->allowance) }}</td></tr>
            <tr class="font-medium"><td class="py-2">Total pendapatan</td><td class="py-2 text-right">{{ $rp($payroll->gross_pay) }}</td></tr>
            <tr><td class="py-2 text-slate-600">Potongan BPJS ({{ rtrim(rtrim(number_format(config('hr.bpjs_rate') * 100, 2), '0'), '.') }}%)</td><td class="py-2 text-right text-red-600">− {{ $rp($payroll->bpjs_deduction) }}</td></tr>
            <tr><td class="py-2 text-slate-600">Potongan alpha ({{ $payroll->absence_days }} hari)</td><td class="py-2 text-right text-red-600">− {{ $rp($payroll->absence_deduction) }}</td></tr>
            <tr class="text-base font-semibold"><td class="py-3">Gaji diterima</td><td class="py-3 text-right">{{ $rp($payroll->net_pay) }}</td></tr>
        </tbody>
    </table>

    @if ($payroll->paid_at)
        <p class="mt-4 text-xs text-slate-500">Dibayar pada {{ $payroll->paid_at->translatedFormat('d F Y H:i') }}</p>
    @endif
    <p class="mt-4 text-xs text-slate-400">Belum termasuk PPh 21.</p>
</div>
@endsection
