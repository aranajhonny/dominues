<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(100)
            ->get(['id', 'type', 'status', 'amount', 'method', 'reference', 'meta', 'created_at']);

        return response()->json(['transactions' => $transactions]);
    }
}