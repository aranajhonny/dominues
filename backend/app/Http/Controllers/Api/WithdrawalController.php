<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(private WalletService $wallet) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
        ]);

        $user = $request->user();

        // 1. KYC must be approved before any withdrawal (documented critical control).
        if ($user->kyc_status !== 'approved') {
            return response()->json(['message' => 'Verificación de identidad requerida antes de retirar.'], 422);
        }

        // 2. Playthrough requirement: % of net approved deposits must have been played.
        $min = (float) Setting::get('withdrawal_min', '5');
        $percent = (int) Setting::get('playthrough_percent', '100');

        if ((float) $data['amount'] < $min) {
            return response()->json(['message' => "El monto mínimo de retiro es $ {$min}."], 422);
        }

        $netDeposits = (float) Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')->where('status', 'approved')->sum('amount');
        $played = (float) abs(Transaction::where('user_id', $user->id)
            ->where('type', 'game_stake')->where('status', 'completed')->sum('amount'));
        $required = round($netDeposits * $percent / 100, 2);

        if ($played < $required) {
            return response()->json([
                'message' => "No cumples el requisito de apuesta (jugado $played de $required).",
            ], 422);
        }

        try {
            $withdrawal = $this->wallet->requestWithdrawal($user, (float) $data['amount']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Retiro solicitado. El monto queda reservado.',
            'withdrawal' => $withdrawal->only(['id', 'amount', 'status', 'created_at']),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $withdrawals = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'withdrawal')
            ->latest()
            ->limit(50)
            ->get(['id', 'amount', 'status', 'reference', 'created_at']);

        return response()->json(['withdrawals' => $withdrawals]);
    }

    public function requirements(Request $request): JsonResponse
    {
        $user = $request->user();
        $percent = (int) Setting::get('playthrough_percent', '100');

        $netDeposits = (float) Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')->where('status', 'approved')->sum('amount');
        $played = (float) abs(Transaction::where('user_id', $user->id)
            ->where('type', 'game_stake')->where('status', 'completed')->sum('amount'));
        $required = round($netDeposits * $percent / 100, 2);

        return response()->json([
            'requirements' => [
                'percent' => $percent,
                'jokado' => $played,
                'required' => $required,
                'met' => $played >= $required,
            ],
        ]);
    }
}