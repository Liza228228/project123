<?php

namespace App\Http\Controllers;

use App\Services\DadataAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DadataAddressController extends Controller
{
    public function suggest(Request $request, DadataAddressService $service): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:255'],
            'count' => ['nullable', 'integer', 'min:1', 'max:15'],
        ]);

        try {
            $suggestions = $service->suggest(
                (string) $validated['query'],
                (int) ($validated['count'] ?? 7)
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Не удалось получить подсказки адреса.',
            ], 502);
        }

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    public function clean(Request $request, DadataAddressService $service): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $result = $service->clean((string) $validated['address']);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Не удалось стандартизовать адрес.',
            ], 502);
        }

        return response()->json([
            'result' => $result,
        ]);
    }
}
