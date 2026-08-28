<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameTable;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent seeders — safe to run on every container boot.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@dominues.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );
        $admin->update(['role' => 'admin', 'active' => true]);

        $host = User::firstOrCreate(
            ['email' => 'host@dominues.local'],
            [
                'name' => 'Anfitrión Demo',
                'password' => Hash::make('host123'),
                'role' => 'host',
            ]
        );
        $host->update(['role' => 'host', 'active' => true]);

        $client = User::firstOrCreate(
            ['email' => 'jugador@dominues.local'],
            [
                'name' => 'Jugador Demo',
                'password' => Hash::make('jugador123'),
                'role' => 'client',
            ]
        );
        // Seed a starting balance so the demo can play immediately.
        $client->update(['balance' => 1000, 'active' => true]);

        $game = Game::firstOrCreate(
            ['name' => 'Dominó Doble Seis'],
            [
                'mode' => 'dobleseis',
                'min_bet' => 10,
                'max_players' => 4,
                'fee_percent' => 10,
                'active' => true,
            ]
        );

        GameTable::firstOrCreate(
            ['game_id' => $game->id, 'name' => 'Mesa Clásica'],
            ['status' => 'open']
        );
        GameTable::firstOrCreate(
            ['game_id' => $game->id, 'name' => 'Mesa Rápida'],
            ['status' => 'open']
        );

        $defaults = [
            'playthrough_percent' => '100',
            'withdrawal_min' => '5',
            'blockbee_enabled' => '0',
            'game_fee_percent' => '10',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}