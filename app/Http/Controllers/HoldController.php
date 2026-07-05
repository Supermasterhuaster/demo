<?php

namespace App\Http\Controllers;

use App\Services\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HoldController extends Controller
{
    public function __construct(private readonly SlotService $slotService)
    {
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || ! Str::isUuid($key)) {
            return response()->json(['error' => 'Idempotency-Key required'], 422);
        }

        $hold = $this->slotService->createHold($id, $key);

        return response()->json($hold, 201);
    }

    public function confirm(int $id): JsonResponse
    {
        $hold = $this->slotService->confirmHold($id);

        return response()->json($hold);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->slotService->cancelHold($id);

        return response()->json(null, 204);
    }
}
