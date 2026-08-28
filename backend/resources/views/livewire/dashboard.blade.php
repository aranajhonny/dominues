@extends('layouts.admin')

@section('content')
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-secondary small text-uppercase">Usuarios</div>
            <div class="fs-3 fw-bold">{{ $stats['users'] }}</div>
            <div class="text-secondary small">Activos: {{ $stats['active_users'] }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-secondary small text-uppercase">KYC pendiente</div>
            <div class="fs-3 fw-bold {{ $stats['pending_kyc'] > 0 ? 'text-warning' : '' }}">{{ $stats['pending_kyc'] }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-secondary small text-uppercase">Pendientes</div>
            <div class="fs-3 fw-bold text-warning">{{ $stats['pending_deposits'] }} <span class="fs-6 text-secondary">dep</span></div>
            <div class="fs-5 fw-bold text-danger">{{ $stats['pending_withdrawals'] }} <span class="fs-6 text-secondary">ret</span></div>
        </div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body">
            <div class="text-secondary small text-uppercase">Ingresos hoy (depósitos aprobados)</div>
            <div class="fs-3 fw-bold text-success">$ {{ number_format($stats['income_today'], 2) }}</div>
            <div class="text-secondary small">Retiros hoy: $ {{ number_format($stats['withdrawals_today'], 2) }}</div>
        </div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body">
            <div class="text-secondary small text-uppercase">Partidas en curso</div>
            <div class="fs-3 fw-bold">{{ $stats['playing_matches'] }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body">
            <div class="text-secondary small text-uppercase">Comisiones acumuladas (rake)</div>
            <div class="fs-3 fw-bold text-warning">$ {{ number_format($stats['rake_total'], 2) }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body">
            <div class="text-secondary small text-uppercase">Bonos</div>
            @if($bonusConfigured)
                <div class="fs-3 fw-bold text-success">$ 0.00</div>
            @else
                <div class="fs-5 fw-bold text-secondary">No configurado</div>
                <div class="text-secondary small">Sin módulo de bonos activo; no se reporta como ingreso.</div>
            @endif
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-header">Ranking de ganadores</div>
        <div class="card-body p-0">
            <table class="table mb-0 align-middle">
                <thead><tr class="text-secondary small"><th>#</th><th>Jugador</th><th class="text-end">Ganancias</th></tr></thead>
                <tbody>
                @forelse($ranking as $i => $row)
                    <tr><td>{{ $i + 1 }}</td><td>{{ $row->name }}</td><td class="text-end">$ {{ number_format($row->winnings, 2) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-secondary py-4">Sin partidas finalizadas todavía.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection