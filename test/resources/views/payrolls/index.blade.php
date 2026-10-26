@extends('layouts.app')
@section('title', 'Penggajian')

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.'); @endphp
<h1 class="mb-4 text-2xl font-semibold">Penggajian</h1>

@if ($seesAll)
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex items-end gap-2">
            <div><label class="mb-1 block text-xs text-slate-500">Periode</label>
                <input type="month" name="period" value="{{ $period }}" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm"></div>
            <button class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-100">Tampilkan</button>
        </form>
        @if (auth()->user()->isHrStaff())
            <form method="POST" action="{{ route('hr.payrolls.generate') }}"
                  onsubmit="return confirm('Buat / hitung ulang slip gaji periode {{ $period }}? Slip yang sudah dibayar tidak diubah.')">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Generate slip {{ $period }}</button>
            </form>
        @endif
    </div>
    @error('period') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror
@endif

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-100 text-xs uppercase text-slate-500">
        <tr>
            @if ($seesAll)<th class="px-4 py-3">Karyawan</th>@else<th class="px-4 py-3">Periode</th>@endif
            <th class="px-4 py-3 text-right">Gross</th><th class="px-4 py-3 text-right">Potongan</th>
            <th class="px-4 py-3 text-right">Diterima</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($payrolls as $p)
            <tr>
                @if ($seesAll)
                    <td class="px-4 py-2"><div class="font-medium">{{ $p->employee->user->name }}</div><div class="text-xs text-slate-500">{{ $p->employee->employee_number }}</div></td>
                @else
                    <td class="px-4 py-2">{{ $p->period }}</td>
                @endif
                <td class="px-4 py-2 text-right">{{ $rp($p->gross_pay) }}</td>
                <td class="px-4 py-2 text-right">{{ $rp($p->total_deduction) }}</td>
                <td class="px-4 py-2 text-right font-medium">{{ $rp($p->net_pay) }}</td>
                <td class="px-4 py-2"><x-badge :status="$p->status" /></td>
                <td class="px-4 py-2 text-right whitespace-nowrap">
                    <a href="{{ route('hr.payrolls.show', $p) }}" class="underline">Slip</a>
                    @if ($seesAll && $p->status === 'draft' && in_array(auth()->user()->role, ['admin', 'hr', 'finance'], true))
                        <form method="POST" action="{{ route('hr.payrolls.pay', $p) }}" class="ml-3 inline" onsubmit="return confirm('Tandai sudah dibayar?')">@csrf
                            <button class="text-emerald-700 underline">Tandai dibayar</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">
                {{ $seesAll ? 'Belum ada slip untuk periode ini.' : 'Belum ada slip gaji yang dirilis.' }}
            </td></tr>
        @endforelse
        </tbody>
        @if ($seesAll && $payrolls->count())
            <tfoot class="bg-slate-50 font-medium"><tr><td class="px-4 py-3" colspan="3">Total diterima</td><td class="px-4 py-3 text-right">{{ $rp($totalNet) }}</td><td colspan="2"></td></tr></tfoot>
        @endif
    </table>
</div>
@endsection
