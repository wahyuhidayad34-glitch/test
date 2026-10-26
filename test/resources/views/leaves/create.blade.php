@extends('layouts.app')
@section('title', 'Ajukan Cuti')

@section('content')
<h1 class="mb-1 text-2xl font-semibold">Ajukan cuti / izin</h1>
<p class="mb-4 text-sm text-slate-500">Sisa cuti tahunan {{ now()->year }}: <b>{{ $remaining }}</b> hari. Hari dihitung Senin–Jumat.</p>

@if ($errors->any())
    <div class="mb-4 max-w-xl rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('hr.leaves.store') }}" class="max-w-xl space-y-4 rounded-xl bg-white p-6 shadow-sm">
    @csrf
    <div>
        <label class="mb-1 block text-sm font-medium">Jenis</label>
        <select name="type" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            @foreach (config('hr.leave_types') as $key => $label)
                <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="mb-1 block text-sm font-medium">Mulai</label>
            <input type="date" name="start_date" value="{{ old('start_date') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
        <div><label class="mb-1 block text-sm font-medium">Sampai</label>
            <input type="date" name="end_date" value="{{ old('end_date') }}" required class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></div>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Alasan</label>
        <textarea name="reason" rows="3" required maxlength="500" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
    </div>
    <div class="flex gap-3">
        <button class="rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-700">Kirim pengajuan</button>
        <a href="{{ route('hr.leaves.index') }}" class="rounded-md border border-slate-300 px-5 py-2 text-sm hover:bg-slate-100">Batal</a>
    </div>
</form>
@endsection
