<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;

class Users extends Component
{
    public string $search = '';

    protected $queryString = ['search'];

    public function setRole(int $id, string $role)
    {
        $this->authorizeRole();
        $user = User::findOrFail($id);
        $user->update(['role' => $role]);
        session()->flash('ok', "Rol de {$user->name} actualizado a {$role}.");
    }

    public function toggleActive(int $id)
    {
        $this->authorizeRole();
        $user = User::findOrFail($id);
        $user->update(['active' => ! $user->active]);
        session()->flash('ok', "Estado de {$user->name} actualizado.");
    }

    private function authorizeRole(): void
    {
        // Role & state management is admin-only.
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Solo el administrador puede gestionar usuarios.');
        }
    }

    public function render()
    {
        $this->authorizeRole();

        $query = User::query()->withCount('transactions');

        if (trim($this->search) !== '') {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"));
        }

        return view('livewire.users', ['users' => $query->latest()->limit(100)->get()]);
    }
}