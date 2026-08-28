<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Verificación de identidad</h4>
    <select wire:model.live="filter" class="form-select form-select-sm w-auto">
        <option value="pending">Solo pendientes</option>
        <option value="all">Todos</option>
    </select>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
            <tr class="text-secondary small">
                <th>ID</th><th>Fecha</th><th>Jugador</th><th>Documento</th><th>Estado</th><th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            @forelse($documents as $doc)
                <tr>
                    <td>#{{ $doc->id }}</td>
                    <td class="small">{{ $doc->created_at->format('d/m H:i') }}</td>
                    <td>{{ $doc->user?->name }} <span class="text-secondary small">{{ $doc->user?->email }}</span></td>
                    <td class="small">{{ strtoupper($doc->document_type) }} · {{ $doc->document_number }}</td>
                    <td>
                        <span class="badge {{ match($doc->status) { 'approved' => 'bg-success', 'rejected' => 'bg-danger', default => 'bg-warning text-dark' } }}">
                            {{ $doc->status }}
                        </span>
                        @if($doc->admin_note)
                            <div class="text-danger small mt-1">{{ $doc->admin_note }}</div>
                        @endif
                    </td>
                    <td class="text-end">
                        <button class="btn btn-outline-light btn-sm" wire:click="openFicha({{ $doc->id }})">Abrir ficha</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-4">Sin documentos en esta vista.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($ficha)
    <div class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,.55)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background:#242a3a">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Ficha KYC #{{ $ficha->id }} — {{ $ficha->user?->name }}</h5>
                    <button type="button" class="btn-close btn-close-white" wire:click="closeFicha"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-7">
                            <img src="data:image/png;base64,{{ $ficha->image_data }}" class="img-fluid border rounded" alt="Documento">
                        </div>
                        <div class="col-md-5">
                            <p><strong>Email:</strong> {{ $ficha->user?->email }}</p>
                            <p><strong>Tipo:</strong> {{ strtoupper($ficha->document_type) }}</p>
                            <p><strong>Número:</strong> {{ $ficha->document_number }}</p>
                            <p><strong>Estado:</strong> {{ $ficha->status }}</p>
                            @if($ficha->status === 'pending')
                                <div class="mt-3">
                                    <label class="form-label small">Motivo (obligatorio al rechazar)</label>
                                    <input wire:model="note" class="form-control" placeholder="Motivo del rechazo…">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    @if($ficha->status === 'pending')
                        <button class="btn btn-danger" wire:click="review({{ $ficha->id }}, 'rejected')">Rechazar</button>
                        <button class="btn btn-success" wire:click="review({{ $ficha->id }}, 'approved')">Aprobar</button>
                    @else
                        <span class="text-secondary small">Documento ya revisado ({{ $ficha->reviewed_at?->format('d/m H:i') }}).</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
</div>