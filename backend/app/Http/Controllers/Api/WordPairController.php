<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WordPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WordPairController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = WordPair::where('user_id', $request->user()->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('placeholder', 'like', "%{$search}%")
                  ->orWhere('original_value', 'like', "%{$search}%")
                  ->orWhere('entity_type', 'like', "%{$search}%");
            });
        }

        $pairs = $query->with('fileRecord:id,filename')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($pairs);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'placeholder' => 'required|string|max:100',
            'original_value' => 'required|string|max:500',
            'entity_type' => 'required|string|max:50',
        ]);

        $pair = WordPair::create([
            'user_id' => $request->user()->id,
            'file_record_id' => null,
            'placeholder' => $request->input('placeholder'),
            'original_value' => $request->input('original_value'),
            'entity_type' => $request->input('entity_type'),
        ]);

        return response()->json($pair, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $pair = WordPair::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $request->validate([
            'placeholder' => 'sometimes|string|max:100',
            'original_value' => 'sometimes|string|max:500',
            'entity_type' => 'sometimes|string|max:50',
        ]);

        $pair->update($request->only(['placeholder', 'original_value', 'entity_type']));

        return response()->json($pair);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $pair = WordPair::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $pair->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
