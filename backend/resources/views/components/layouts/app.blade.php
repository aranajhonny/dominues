<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dominues Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #151922; color: #e9ecef; }
        .sidebar { min-height: 100vh; background: #1b1f2a; border-right: 1px solid #2b3245; }
        .sidebar .nav-link { color: #9aa3b2; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #f5b942; background: #242a3a; }
        .card { background: #242a3a; border-color: #3a4157; }
        .card-header { background: #1f2432; border-color: #3a4157; font-weight: 600; }
        .table { --bs-table-bg: transparent; color: #e9ecef; }
        .form-control, .form-select { background: #2b3245; border-color: #3a4157; color: #e9ecef; }
        .form-control:focus, .form-select:focus { background: #2b3245; color: #e9ecef; }
    </style>
    @livewireStyles
</head>
<body>
<div class="d-flex">
    <nav class="sidebar d-flex flex-column p-3" style="width: 240px;">
        @include('admin.partials.sidebar')
    </nav>
    <main class="flex-grow-1 p-4">
        @if (session('ok'))
            <div class="alert alert-success py-2">{{ session('ok') }}</div>
        @endif
        @if (session('err'))
            <div class="alert alert-danger py-2">{{ session('err') }}</div>
        @endif
        {{ $slot }}
    </main>
</div>
@livewireScripts
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>