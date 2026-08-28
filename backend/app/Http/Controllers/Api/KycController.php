<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::in(['cedula', 'passport'])],
            'document_number' => ['required', 'string', 'max:60'],
            'document_image_base64' => ['required', 'string', 'max:4000000'],
        ]);

        $user = $request->user();

        if (KycDocument::where('user_id', $user->id)->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'Ya tienes un documento en revisión.'], 422);
        }

        $kyc = KycDocument::create([
            'user_id' => $user->id,
            'document_type' => $data['document_type'],
            'document_number' => $data['document_number'],
            'image_data' => $data['document_image_base64'],
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Documento enviado.',
            'kyc' => $kyc->only(['id', 'document_type', 'document_number', 'status', 'admin_note', 'created_at']),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $latest = KycDocument::where('user_id', $request->user()->id)->latest()->first();

        return response()->json([
            'kyc' => $latest ? $latest->makeHidden('image_data')->only(['id', 'document_type', 'document_number', 'status', 'admin_note', 'created_at', 'reviewed_at']) : null,
        ]);
    }
}