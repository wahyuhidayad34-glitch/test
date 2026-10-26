@extends('layouts.app')
@section('title', 'Cuti & Izin')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold">Cuti & Izin</h1>
    @if ($employee)
        <a href="{{ route('hr.leaves.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">+ Ajukan cuti / izin</a>
    @endif
</div>

@if ($toDecide !== null)
<section class="mb-8">
    <h2 class="mb-2 font-medium">Menunggu persetujuan Anda ({{ $toDecide->count() }})</h2>
    <div class="space-y-3">
        @forelse ($toDecide as $l)
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-medium">{{ $l->employee->user->name }} <span class="font-normal text-slate-500">· {{ $l->employee->user->department }}</span></div>
                        <div class="text-sm">{{ $l->type_label }} · {{ $l->start_date->translatedFormat('d M') }}–{{ $l->end_date->translatedFormat('d M Y') }} ({{ $l->days }} hari kerja)</div>
                        <div class="mt-1 text-sm text-slate-600">{{ $l->reason }}</div>
                    </div>
                    <form method="POST" action="{{ route('hr.leaves.approve', $l) }}">@csrf
                        <button class="rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">Setujui</button>
                    </form>
                </div>
                <form method="POST" action="{{ route('hr.leaves.reject', $l) }}" class="mt-3 flex flex-wrap gap-2">@csrf
                    <input name="decision_note" required maxlength="500" placeholder="Alasan penolakan"
                           class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm">
                    <button class="rounded-md border border-red-300 px-4 py-1.5 text-sm text-red-700 hover:bg-red-50">Tolak</button>
                </form>
            </div>
        @empty
            <div class="rounded-xl bg-white p-6 text-center text-sm text-slate-500 shadow-sm">Tidak ada pengajuan yang menunggu.</div>
        @endforelse
    </div>
    @error('decision_note') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
</section>
@endif

@if ($employee)
<section>
    <h2 class="mb-2 font-medium">Pengajuan saya <span class="font-normal text-slate-500">· sisa cuti tahunan {{ now()->year }}: {{ $remaining }} hari</span></h2>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Jenis</th><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3">Hari</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Catatan</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @forelse ($mine as $l)
                <tr>
                    <td class="px-4 py-2">{{ $l->type_label }}</td>
                    <td class="px-4 py-2">{{ $l->start_date->translatedFormat('d M') }}–{{ $l->end_date->translatedFormat('d M Y') }}</td>
                    <td class="px-4 py-2">{{ $l->days }}</td>
                    <td class="px-4 py-2"><x-badge :status="$l->status" /></td>
                    <td class="px-4 py-2 text-slate-600">{{ $l->decision_note }}</td>
                    <td class="px-4 py-2 text-right">
                        @if ($l->status === 'pending')
                            <form method="POST" action="{{ route('hr.leaves.cancel', $l) }}" onsubmit="return confirm('Batalkan pengajuan ini?')">@csrf
                                <button class="text-red-600 underline">Batalkan</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Belum ada pengajuan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $mine->links() }}</div>
</section>
@endif
@endsection
