<div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Usuarios</h4>
    <input wire:model.live.debounce.300ms="search" class="form-control form-control-sm" placeholder="Buscar…" style="width:240px">
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead>
            <tr class="text-secondary small">
                <th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th class="text-end">Saldo</th><th class="text-end">Reservado</th><th>Operaciones</th><th>Estado</th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>#{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td class="small">{{ $user->email }}</td>
                    <td>
                        <select class="form-select form-select-sm w-auto" wire:change="setRole({{ $user->id }}, $event.target.value)">
                            @foreach(['admin','host','business','client'] as $role)
                                <option value="{{ $role }}" @selected($user->role === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="text-end">$ {{ number_format($user->balance, 2) }}</td>
                    <td class="text-end text-warning">$ {{ number_format($user->reserved_balance, 2) }}</td>
                    <td class="small text-secondary">{{ $user->transactions_count }}</td>
                    <td>
                        <button class="btn btn-sm {{ $user->active ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                wire:click="toggleActive({{ $user->id }})">
                            {{ $user->active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">Sin usuarios.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>