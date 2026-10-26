<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — HRS Salim</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4">
<div class="w-full max-w-sm rounded-xl bg-white p-8 shadow">
    <h1 class="text-xl font-semibold text-slate-900">HRS Salim</h1>
    <p class="mb-6 text-sm text-slate-500">Sistem Informasi SDM — silakan masuk.</p>

    <form method="POST" action="{{ route('hr.login.attempt') }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium">Password</label>
            <input type="password" name="password" required
                   class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1"> Ingat saya
        </label>
        <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Masuk</button>
    </form>
</div>
</body>
</html>
