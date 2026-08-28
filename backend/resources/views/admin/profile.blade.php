@extends('layouts.admin')

@section('content')
    <div class="card">
        <div class="card-header">Mi perfil</div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3">Nombre</dt>
                <dd class="col-sm-9">{{ auth()->user()->name }}</dd>
                <dt class="col-sm-3">Correo</dt>
                <dd class="col-sm-9">{{ auth()->user()->email }}</dd>
                <dt class="col-sm-3">Rol</dt>
                <dd class="col-sm-9 text-capitalize">{{ auth()->user()->role }}</dd>
                <dt class="col-sm-3">Miembro desde</dt>
                <dd class="col-sm-9">{{ auth()->user()->created_at?->format('d/m/Y') }}</dd>
            </dl>
        </div>
    </div>
@endsection