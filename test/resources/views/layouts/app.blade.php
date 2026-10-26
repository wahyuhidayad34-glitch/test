<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — HRS Salim</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
@php
    $u = auth()->user();
    $nav = [
        ['hr.dashboard', 'Dashboard', 'hr.dashboard', true],
        ['hr.employees.index', 'Karyawan', 'hr.employees.*', $u->isHrStaff()],
        ['hr.attendance.index', 'Absensi', 'hr.attendance.*', true],
        ['hr.leaves.index', 'Cuti & Izin', 'hr.leaves.*', true],
        ['hr.payrolls.index', 'Penggajian', 'hr.payrolls.*', true],
    ];
@endphp
<div class="min-h-screen md:flex">
    <aside class="bg-slate-900 text-slate-200 md:w-60 md:min-h-screen md:flex md:flex-col">
        <div class="px-5 py-4 text-lg font-semibold tracking-tight text-white">HRS Salim</div>
        <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:flex-col md:overflow-visible md:pb-0">
            @foreach ($nav as [$route, $label, $pattern, $visible])
                @if ($visible)
                    <a href="{{ route($route) }}"
                       class="whitespace-nowrap rounded-md px-3 py-2 text-sm {{ request()->routeIs($pattern) ? 'bg-slate-700 text-white' : 'hover:bg-slate-800' }}">
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="mt-auto hidden border-t border-slate-700 p-4 text-sm md:block">
            <div class="font-medium text-white">{{ $u->name }}</div>
            <div class="text-slate-400">{{ ucfirst($u->role === 'requester' ? 'karyawan' : $u->role) }}{{ $u->department ? ' · '.$u->department : '' }}</div>
            <form method="POST" action="{{ route('hr.logout') }}" class="mt-3">
                @csrf
                <button class="text-slate-300 underline hover:text-white">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 flex-1 p-4 md:p-8">
        <div class="mb-4 flex items-center justify-between md:hidden">
            <span class="text-sm text-slate-500">{{ $u->name }}</span>
            <form method="POST" action="{{ route('hr.logout') }}">@csrf<button class="text-sm text-slate-500 underline">Keluar</button></form>
        </div>

        @if (session('success'))
            <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>
