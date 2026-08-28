<?php

namespace App\Livewire;

use App\Models\Game;
use App\Models\GameTable;
use App\Models\Setting;
use Livewire\Component;

class GameConfig extends Component
{
    public string $bootMessage = '';
    public array $settings = [];

    public function mount()
    {
        $this->settings = [
            'playthrough_percent' => (string) Setting::get('playthrough_percent', '100'),
            'withdrawal_min' => (string) Setting::get('withdrawal_min', '5'),
            'blockbee_enabled' => (string) Setting::get('blockbee_enabled', '0'),
        ];
    }

    public function saveSettings()
    {
        $this->authorizeAdmin();
        $this->validate([
            'settings.playthrough_percent' => ['required', 'integer', 'min:0', 'max:1000'],
            'settings.withdrawal_min' => ['required', 'numeric', 'min:1'],
            'settings.blockbee_enabled' => ['required', 'in:0,1'],
        ]);

        foreach ($this->settings as $key => $value) {
            Setting::set($key, $value);
        }

        session()->flash('ok', 'Configuración guardada.');
    }

    public function toggleGame(int $id)
    {
        $this->authorizeAdmin();
        $game = Game::findOrFail($id);
        $game->update(['active' => ! $game->active]);
    }

    public function addGame()
    {
        $this->authorizeAdmin();
        $this->validate([
            'bootMessage' => ['required', 'string', 'max:120'],
        ]);

        Game::create([
            'name' => $this->bootMessage,
            'mode' => 'dobleseis',
            'min_bet' => 10,
            'max_players' => 4,
            'fee_percent' => 10,
            'active' => true,
        ]);

        $this->bootMessage = '';
        session()->flash('ok', 'Juego creado.');
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Solo el administrador puede configurar el sistema.');
        }
    }

    public function render()
    {
        $this->authorizeAdmin();

        return view('livewire.game-config', [
            'games' => Game::withCount('matches')->orderBy('id')->get(),
            'tables' => GameTable::with('game')->latest()->limit(50)->get(),
        ]);
    }
}