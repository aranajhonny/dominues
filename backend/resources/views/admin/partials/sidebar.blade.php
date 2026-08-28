<div class="h5 mb-4"><span class="text-warning">♦</span> Dominues</div>
<ul class="nav nav-pills flex-column gap-1">
    <li class="nav-item"><a class="nav-link {{ request()->is('admin') ? 'active' : '' }}" href="{{ url('/admin') }}">📊 Principal</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->is('admin/transactions*') ? 'active' : '' }}" href="{{ url('/admin/transactions') }}">💳 Transacciones</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->is('admin/kyc*') ? 'active' : '' }}" href="{{ url('/admin/kyc') }}">🪪 Verificación de identidad</a></li>
    @if(auth()->user()?->isAdmin())
        <li class="nav-item"><a class="nav-link {{ request()->is('admin/users*') ? 'active' : '' }}" href="{{ url('/admin/users') }}">👥 Usuarios</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->is('admin/games*') ? 'active' : '' }}" href="{{ url('/admin/games') }}">🎮 Juegos y configuración</a></li>
    @endif
    <li class="nav-item"><a class="nav-link {{ request()->is('admin/profile*') ? 'active' : '' }}" href="{{ url('/admin/profile') }}">👤 Perfil</a></li>
</ul>
<form method="POST" action="{{ url('/admin/logout') }}" class="mt-auto">
    @csrf
    <button class="btn btn-outline-light btn-sm w-100">Cerrar sesión</button>
</form>