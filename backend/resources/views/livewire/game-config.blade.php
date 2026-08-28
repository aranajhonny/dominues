<div class="row">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Juegos y mesas</span>
                <div class="d-flex gap-2">
                    <input wire:model="bootMessage" class="form-control form-control-sm" placeholder="Nombre del nuevo juego…" style="width:220px">
                    <button class="btn btn-warning btn-sm" wire:click="addGame">Crear</button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                    <tr class="text-secondary small">
                        <th>Juego</th><th>Modo</th><th class="text-end">Apuesta mín</th><th class="text-end">Jugadores</th><th class="text-end">Comisión</th><th>Partidas</th><th>Estado</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($games as $game)
                        <tr>
                            <td class="fw-semibold">{{ $game->name }}</td>
                            <td class="small">{{ $game->mode }}</td>
                            <td class="text-end">$ {{ number_format($game->min_bet, 2) }}</td>
                            <td class="text-end">{{ $game->max_players }}</td>
                            <td class="text-end">{{ number_format($game->fee_percent, 0) }}%</td>
                            <td class="small">{{ $game->matches_count }}</td>
                            <td>
                                <button class="btn btn-sm {{ $game->active ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                        wire:click="toggleGame({{ $game->id }})">
                                    {{ $game->active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Mesas registradas</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead><tr class="text-secondary small"><th>Mesa</th><th>Juego</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse($tables as $table)
                        <tr><td>{{ $table->name }}</td><td class="small">{{ $table->game?->name }}</td><td><span class="badge bg-{{ $table->status === 'open' ? 'success' : 'secondary' }}">{{ $table->status }}</span></td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-secondary py-4">Las mesas se crean en vivo desde el juego.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Configuración del sistema</div>
            <div class="card-body">
                <form wire:submit="saveSettings">
                    <div class="mb-3">
                        <label class="form-label">Requisito de apuesta (% de depósitos netos)</label>
                        <input type="number" wire:model="settings.playthrough_percent" class="form-control">
                        <div class="form-text text-secondary">Porcentaje del depósito neto que debe jugarse antes de retirar.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Monto mínimo de retiro (USD)</label>
                        <input type="number" step="0.01" wire:model="settings.withdrawal_min" class="form-control">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" wire:model="settings.blockbee_enabled" value="1" id="bb">
                        <label class="form-check-label" for="bb">Habilitar BlockBee (reactivar solo tras prueba integral)</label>
                    </div>
                    <button class="btn btn-warning w-100 fw-semibold">Guardar configuración</button>
                </form>
            </div>
        </div>
    </div>
</div>