<?php

namespace App\Livewire;

use App\Models\KycDocument;
use Livewire\Component;

class KycList extends Component
{
    public string $filter = 'pending'; // pending | all
    public ?int $fichaId = null;
    public ?string $note = null;

    protected $queryString = ['filter'];

    public function openFicha(int $id)
    {
        $this->fichaId = $id;
        $this->note = null;
    }

    public function closeFicha()
    {
        $this->fichaId = null;
        $this->note = null;
    }

    public function review(int $id, string $decision)
    {
        $ficha = KycDocument::with('user')->findOrFail($id);

        if ($decision === 'rejected') {
            $this->validate(['note' => ['required', 'string', 'max:300']]);
        } elseif ($ficha->status !== 'pending') {
            session()->flash('err', 'El documento ya fue revisado.');
            return;
        }

        $ficha->update([
            'status' => $decision,
            'admin_note' => $decision === 'rejected' ? $this->note : null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        session()->flash('ok', 'Documento ' . ($decision === 'approved' ? 'aprobado' : 'rechazado') . '.');
        $this->closeFicha();
    }

    public function render()
    {
        $query = KycDocument::with('user')->latest();

        if ($this->filter === 'pending') {
            $query->where('status', 'pending');
        }

        return view('livewire.kyc-list', [
            'documents' => $query->limit(100)->get(),
            'ficha' => $this->fichaId ? KycDocument::with('user')->find($this->fichaId) : null,
        ]);
    }
}