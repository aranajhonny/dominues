<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');      // deposit | withdrawal | game_stake | game_win | refund
            $table->string('status')->default('pending'); // pending | approved | rejected | completed
            $table->decimal('amount', 14, 2);
            $table->string('method')->nullable();    // payment method for deposits / withdrawals
            $table->string('reference')->nullable(); // bank reference / operator note
            $table->string('match_id')->nullable()->index(); // game matches.id when game-related
            $table->longText('meta')->nullable();    // json: proof, admin_note, etc.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // A match can have several stakes (one per player) + one payout.
            $table->index(['user_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};