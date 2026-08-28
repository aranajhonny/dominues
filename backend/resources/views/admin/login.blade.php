<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso — Dominues Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #151922; color: #e9ecef; }
        .card { background: #242a3a; border-color: #3a4157; }
        .form-control { background: #2b3245; border-color: #3a4157; color: #e9ecef; }
        .form-control:focus { background: #2b3245; color: #e9ecef; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <div class="h3"><span class="text-warning">♦</span> Dominues</div>
                        <div class="text-secondary small">Panel administrativo</div>
                    </div>
                    <form method="POST" action="{{ url('/admin/login') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Correo Electrónico</label>
                            <input name="email" type="email" class="form-control" value="{{ old('email') }}" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input name="password" type="password" class="form-control" required>
                        </div>
                        <button class="btn btn-warning w-100 fw-semibold">Continuar</button>
                    </form>
                    <p class="text-center text-secondary small mt-3 mb-0">Acceso restringido al personal autorizado de Dominues.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>