<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Transacciones</h4>
    <div class="d-flex gap-2">
        <select wire:model.live="status" class="form-select form-select-sm w-auto">
            <option value="all">Todos los estados</option>
            <option value="pending">Pendientes</option>
            <option value="approved">Aprobadas</option>
            <option value="rejected">Rechazadas</option>
        </select>
        <input wire:model.live.debounce.300ms="search" class="form-control form-control-sm" placeholder="Buscar por jugador…" style="width:220px">
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <button class="nav-link {{ $tab === 'withdrawals' ? 'active text-warning' : 'text-secondary' }}" wire:click="setTab('withdrawals')">Retiros</button>
    </li>
    <li class="nav-item">
        <button class="nav-link {{ $tab === 'deposits' ? 'active text-warning' : 'text-secondary' }}" wire:click="setTab('deposits')">Depósitos</button>
    </li>
</ul>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
            <tr class="text-secondary small">
                <th>ID</th><th>Fecha</th><th>Jugador</th><th>Método</th><th class="text-end">Monto</th><th>Estado</th><th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
                <tr>
                    <td>#{{ $tx->id }}</td>
                    <td class="small">{{ $tx->created_at->format('d/m H:i') }}</td>
                    <td>{{ $tx->user?->name }} <span class="text-secondary small">{{ $tx->user?->email }}</span></td>
                    <td class="small">{{ $tx->method ?? ($tx->type === 'withdrawal' ? 'retiro' : '—') }}</td>
                    <td class="text-end fw-semibold">$ {{ number_format(abs($tx->amount), 2) }}</td>
                    <td>
                        <span class="badge {{ match($tx->status) { 'approved' => 'bg-success', 'rejected' => 'bg-danger', default => 'bg-warning text-dark' } }}">
                            {{ $tx->status }}
                        </span>
                        @if($tx->reference && $tx->status === 'rejected')
                            <div class="text-danger small mt-1">{{ $tx->reference }}</div>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($tx->status === 'pending')
                            <button class="btn btn-success btn-sm" wire:click="openReview({{ $tx->id }})" @if($reviewingId) disabled @endif>Revisar</button>
                        @else
                            <span class="text-secondary small">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">No hay {{ $tab }} en esta vista.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($reviewing)
    <div class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,.55)">
        <div class="modal-dialog">
            <div class="modal-content" style="background:#242a3a">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Operación #{{ $reviewing->id }} — {{ $reviewing->type }}</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="$set('reviewingId', null)"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1"><strong>Jugador:</strong> {{ $reviewing->user?->name }} ({{ $reviewing->user?->email }})</p>
                    <p class="mb-1"><strong>Monto:</strong> $ {{ number_format(abs($reviewing->amount), 2) }}</p>
                    <p class="mb-0"><strong>Método:</strong> {{ $reviewing->method ?? '—' }} · <strong>Ref:</strong> {{ $reviewing->reference ?? '—' }}</p>
                    <div class="mt-3">
                        <label class="form-label small">Motivo (obligatorio al rechazar)</label>
                        <input wire:model="note" class="form-control" placeholder="Motivo del rechazo…">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button class="btn btn-danger" wire:click="reject">Rechazar</button>
                    <button class="btn btn-success" wire:click="approve">Aprobar</button>
                </div>
            </div>
        </div>
    </div>
@endif
</div>