<?php

namespace App\Livewire;

use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Transactions extends Component
{
    public string $tab = 'withdrawals'; // withdrawals | deposits
    public string $search = '';
    public string $status = 'all';

    public ?int $reviewingId = null;
    public string $note = '';

    /** Keep tab + filters in the URL so "back" returns to the same context. */
    protected $queryString = ['tab', 'search', 'status'];

    public function setTab(string $tab)
    {
        $this->tab = $tab;
        $this->resetPageable();
    }

    public function openReview(int $id)
    {
        $this->reviewingId = $id;
        $this->note = '';
    }

    public function approve()
    {
        $this->review($this->reviewingId, 'approved', null);
    }

    public function reject()
    {
        $this->validate(['note' => ['required', 'string', 'max:300']]);
        $this->review($this->reviewingId, 'rejected', $this->note);
    }

    protected function review(int $id, string $decision, ?string $note)
    {
        $tx = Transaction::with('user')->findOrFail($id);
        $wallet = app(WalletService::class);

        try {
            DB::transaction(function () use ($tx, $decision, $note, $wallet) {
                if ($tx->type === 'deposit') {
                    $decision === 'approved'
                        ? $wallet->approveDeposit($tx)
                        : $wallet->rejectDeposit($tx, (string) $note);
                } else {
                    $decision === 'approved'
                        ? $wallet->approveWithdrawal($tx)
                        : $wallet->rejectWithdrawal($tx, (string) $note);
                }
            });
            session()->flash('ok', "Operación #{$tx->id} {$decision}.");
        } catch (\Throwable $e) {
            session()->flash('err', $e->getMessage());
        }

        $this->reviewingId = null;
        $this->note = '';
    }

    public function render()
    {
        $query = Transaction::with('user')->where('type', $this->tab);

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        if (trim($this->search) !== '') {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"));
        }

        $transactions = $query->latest()->limit(100)->get();

        return view('livewire.transactions', [
            'transactions' => $transactions,
            'reviewing' => $this->reviewingId ? Transaction::with('user')->find($this->reviewingId) : null,
        ]);
    }

    private function resetPageable(): void
    {
        // placeholder for future pagination state
    }
}