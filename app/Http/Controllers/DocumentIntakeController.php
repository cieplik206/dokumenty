<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentIntakeRequest;
use App\Jobs\ProcessDocumentIntake;
use App\Models\DocumentIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentIntakeController extends Controller
{
    /**
     * Store a newly created intake request.
     */
    public function store(DocumentIntakeRequest $request): JsonResponse
    {
        $user = $request->user();

        $intake = DocumentIntake::create([
            'user_id' => $user->id,
            'status' => DocumentIntake::STATUS_QUEUED,
        ]);

        foreach ($request->file('scans', []) as $scan) {
            $intake->addMedia($scan)
                ->toMediaCollection('scans');
        }

        ProcessDocumentIntake::dispatch($intake);

        return response()->json($this->formatIntake($intake->refresh()), 202);
    }

    /**
     * Display the specified intake status.
     */
    public function show(Request $request, DocumentIntake $intake): JsonResponse
    {
        if ($intake->user_id !== $request->user()->id) {
            abort(404);
        }

        return response()->json($this->formatIntake($intake->fresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatIntake(?DocumentIntake $intake): array
    {
        if (! $intake instanceof DocumentIntake) {
            return [];
        }

        return [
            'id' => $intake->id,
            'status' => $intake->status,
            'fields' => $intake->fields,
            'extracted_text' => $intake->extracted_text,
            'extracted_content' => $intake->extracted_content,
            'metadata' => $intake->ai_metadata,
            'error_message' => $intake->error_message,
            'started_at' => $intake->started_at?->toISOString(),
            'finished_at' => $intake->finished_at?->toISOString(),
            'created_at' => $intake->created_at?->toISOString(),
        ];
    }
}
