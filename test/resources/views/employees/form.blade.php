@extends('layouts.app')
@php $isEdit = $employee->exists; @endphp
@section('title', $isEdit ? 'Ubah Karyawan' : 'Tambah Karyawan')

@section('content')
<h1 class="mb-4 text-2xl font-semibold">{{ $isEdit ? 'Ubah data karyawan' : 'Tambah karyawan' }}</h1>

@if ($errors->any())
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

@php
    $val = fn ($field, $default = null) => old($field, $default);
    $input = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm';
@endphp

<form method="POST" action="{{ $isEdit ? route('hr.employees.update', $employee) : route('hr.employees.store') }}"
      class="max-w-3xl space-y-6 rounded-xl bg-white p-6 shadow-sm">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="mb-2 font-medium">Akun</legend>
        <div><label class="mb-1 block text-sm">Nama lengkap</label>
            <input name="name" value="{{ $val('name', $employee->user?->name) }}" required class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">Email</label>
            <input type="email" name="email" value="{{ $val('email', $employee->user?->email) }}" required class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">Password {{ $isEdit ? '(kosongkan jika tidak diubah)' : '' }}</label>
            <input type="password" name="password" {{ $isEdit ? '' : 'required' }} minlength="8" class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">Role</label>
            <select name="role" class="{{ $input }}">
                @foreach ($roles as $key => $label)
                    <option value="{{ $key }}" @selected($val('role', $employee->user?->role ?? 'requester') === $key)>{{ $label }}</option>
                @endforeach
            </select></div>
        <div><label class="mb-1 block text-sm">Departemen</label>
            <input name="department" value="{{ $val('department', $employee->user?->department) }}" required class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">Atasan langsung</label>
            <select name="manager_id" class="{{ $input }}">
                <option value="">— Tidak ada —</option>
                @foreach ($managers as $m)
                    <option value="{{ $m->id }}" @selected((int) $val('manager_id', $employee->user?->manager_id) === $m->id)>{{ $m->name }}</option>
                @endforeach
            </select></div>
    </fieldset>

    <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="mb-2 font-medium">Data pribadi</legend>
        <div><label class="mb-1 block text-sm">Jabatan</label>
            <input name="position" value="{{ $val('position', $employee->position) }}" class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">No. HP</label>
            <input name="phone" value="{{ $val('phone', $employee->phone) }}" class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">Jenis kelamin</label>
            <select name="gender" class="{{ $input }}">
                <option value="">—</option>
                <option value="L" @selected($val('gender', $employee->gender) === 'L')>Laki-laki</option>
                <option value="P" @selected($val('gender', $employee->gender) === 'P')>Perempuan</option>
            </select></div>
        <div><label class="mb-1 block text-sm">Tanggal lahir</label>
            <input type="date" name="birth_date" value="{{ $val('birth_date', $employee->birth_date?->toDateString()) }}" class="{{ $input }}"></div>
        <div class="sm:col-span-2"><label class="mb-1 block text-sm">Alamat</label>
            <textarea name="address" rows="2" class="{{ $input }}">{{ $val('address', $employee->address) }}</textarea></div>
    </fieldset>

    <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="mb-2 font-medium">Kepegawaian & gaji</legend>
        <div><label class="mb-1 block text-sm">Tanggal masuk</label>
            <input type="date" name="join_date" value="{{ $val('join_date', $employee->join_date?->toDateString()) }}" required class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">Status kepegawaian</label>
            <select name="employment_status" class="{{ $input }}">
                @foreach (config('hr.employment_statuses') as $key => $label)
                    <option value="{{ $key }}" @selected($val('employment_status', $employee->employment_status) === $key)>{{ $label }}</option>
                @endforeach
            </select></div>
        <div><label class="mb-1 block text-sm">Gaji pokok (Rp)</label>
            <input type="number" min="0" step="1000" name="basic_salary" value="{{ $val('basic_salary', (int) $employee->basic_salary) }}" required class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">Tunjangan tetap (Rp)</label>
            <input type="number" min="0" step="1000" name="allowance" value="{{ $val('allowance', (int) $employee->allowance) }}" class="{{ $input }}"></div>
        <div><label class="mb-1 block text-sm">Jatah cuti tahunan (hari)</label>
            <input type="number" min="0" max="60" name="leave_quota" value="{{ $val('leave_quota', $employee->leave_quota) }}" required class="{{ $input }}"></div>
    </fieldset>

    <div class="flex gap-3">
        <button class="rounded-md bg-slate-900 px-5 py-2 text-sm font-medium text-white hover:bg-slate-700">Simpan</button>
        <a href="{{ $isEdit ? route('hr.employees.show', $employee) : route('hr.employees.index') }}" class="rounded-md border border-slate-300 px-5 py-2 text-sm hover:bg-slate-100">Batal</a>
    </div>
</form>
@endsection
