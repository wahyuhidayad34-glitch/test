@extends('layouts.app')
@section('title', 'Karyawan')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold">Data Karyawan</h1>
    <a href="{{ route('hr.employees.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">+ Tambah karyawan</a>
</div>

<form method="GET" class="mb-4 flex flex-wrap gap-2">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, email, NIK, jabatan…"
           class="w-64 rounded-md border border-slate-300 px-3 py-2 text-sm">
    <select name="department" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
        <option value="">Semua departemen</option>
        @foreach ($departments as $d)
            <option value="{{ $d }}" @selected(request('department') === $d)>{{ $d }}</option>
        @endforeach
    </select>
    <button class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-100">Filter</button>
</form>

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-100 text-xs uppercase text-slate-500">
        <tr>
            <th class="px-4 py-3">NIK</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Departemen</th>
            <th class="px-4 py-3">Jabatan</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Akun</th><th class="px-4 py-3"></th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($employees as $e)
            <tr>
                <td class="px-4 py-3 font-mono text-xs">{{ $e->employee_number }}</td>
                <td class="px-4 py-3"><div class="font-medium">{{ $e->user->name }}</div><div class="text-xs text-slate-500">{{ $e->user->email }}</div></td>
                <td class="px-4 py-3">{{ $e->user->department }}</td>
                <td class="px-4 py-3">{{ $e->position ?? '—' }}</td>
                <td class="px-4 py-3">{{ $e->status_label }}</td>
                <td class="px-4 py-3"><x-badge :status="$e->user->is_active ? 'active' : 'inactive'" /></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('hr.employees.show', $e) }}" class="text-slate-900 underline">Detail</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Belum ada data karyawan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $employees->links() }}</div>
@endsection
