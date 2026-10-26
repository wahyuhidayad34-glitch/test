@extends('layouts.app')
@section('title', $employee->user->name)

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold">{{ $employee->user->name }}</h1>
        <p class="text-sm text-slate-500">{{ $employee->employee_number }} · {{ $employee->position ?? 'Tanpa jabatan' }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('hr.employees.edit', $employee) }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Ubah</a>
        <form method="POST" action="{{ route('hr.employees.toggle-active', $employee) }}"
              onsubmit="return confirm('Yakin mengubah status akun karyawan ini?')">
            @csrf
            <button class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-100">
                {{ $employee->user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
            </button>
        </form>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-medium">Profil</h2>
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
            <dt class="text-slate-500">Email</dt><dd class="col-span-2">{{ $employee->user->email }}</dd>
            <dt class="text-slate-500">Role</dt><dd class="col-span-2">{{ $employee->user->role }}</dd>
            <dt class="text-slate-500">Departemen</dt><dd class="col-span-2">{{ $employee->user->department }}</dd>
            <dt class="text-slate-500">Atasan</dt><dd class="col-span-2">{{ $employee->user->manager?->name ?? '—' }}</dd>
            <dt class="text-slate-500">No. HP</dt><dd class="col-span-2">{{ $employee->phone ?? '—' }}</dd>
            <dt class="text-slate-500">Gender</dt><dd class="col-span-2">{{ ['L' => 'Laki-laki', 'P' => 'Perempuan'][$employee->gender] ?? '—' }}</dd>
            <dt class="text-slate-500">Tgl lahir</dt><dd class="col-span-2">{{ $employee->birth_date?->translatedFormat('d F Y') ?? '—' }}</dd>
            <dt class="text-slate-500">Alamat</dt><dd class="col-span-2">{{ $employee->address ?? '—' }}</dd>
            <dt class="text-slate-500">Akun</dt><dd class="col-span-2"><x-badge :status="$employee->user->is_active ? 'active' : 'inactive'" /></dd>
        </dl>
    </section>

    <section class="rounded-xl bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-medium">Kepegawaian</h2>
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
            <dt class="text-slate-500">Tgl masuk</dt><dd class="col-span-2">{{ $employee->join_date->translatedFormat('d F Y') }}</dd>
            <dt class="text-slate-500">Status</dt><dd class="col-span-2">{{ $employee->status_label }}</dd>
            <dt class="text-slate-500">Gaji pokok</dt><dd class="col-span-2">Rp {{ number_format($employee->basic_salary, 0, ',', '.') }}</dd>
            <dt class="text-slate-500">Tunjangan</dt><dd class="col-span-2">Rp {{ number_format($employee->allowance, 0, ',', '.') }}</dd>
            <dt class="text-slate-500">Sisa cuti</dt><dd class="col-span-2">{{ $remainingLeave }} dari {{ $employee->leave_quota }} hari ({{ now()->year }})</dd>
        </dl>
        <h3 class="mb-2 mt-5 text-sm font-medium">Cuti terakhir</h3>
        <ul class="space-y-1 text-sm">
            @forelse ($recentLeaves as $l)
                <li class="flex items-center justify-between">
                    <span>{{ $l->type_label }} · {{ $l->start_date->translatedFormat('d M') }}–{{ $l->end_date->translatedFormat('d M Y') }}</span>
                    <x-badge :status="$l->status" />
                </li>
            @empty
                <li class="text-slate-500">Belum ada pengajuan.</li>
            @endforelse
        </ul>
    </section>
</div>
<a href="{{ route('hr.employees.index') }}" class="mt-6 inline-block text-sm underline">← Kembali ke daftar</a>
@endsection
