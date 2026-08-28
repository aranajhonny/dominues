<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepositController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'method' => ['required', Rule::in(['pago_movil', 'bank_transfer', 'instapago', 'payku', 'blockbee'])],
            'reference' => ['required', 'string', 'max:120'],
            'proof_base64' => ['nullable', 'string', 'max:4000000'],
        ]);

        if ($data['method'] === 'blockbee' && \App\Models\Setting::get('blockbee_enabled', '0') !== '1') {
            return response()->json(['message' => 'BlockBee está temporalmente desactivado.'], 422);
        }

        $deposit = $this->wallet->requestDeposit(
            $request->user(),
            (float) $data['amount'],
            $data['method'],
            $data['reference'],
            $data['proof_base64'] ?? null
        );

        return response()->json([
            'message' => 'Solicitud registrada. Se acreditará al aprobarse.',
            'deposit' => $deposit->only(['id', 'amount', 'method', 'status', 'reference', 'created_at']),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $deposits = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'deposit')
            ->latest()
            ->limit(50)
            ->get(['id', 'amount', 'method', 'status', 'reference', 'created_at']);

        return response()->json(['deposits' => $deposits]);
    }
}