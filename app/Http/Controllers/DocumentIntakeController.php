<?php

namespace App\Http\Controllers;

use App\Actions\Documents\StreamDocumentIntake;
use App\Http\Requests\DocumentIntakeRequest;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentIntakeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DocumentIntakeRequest $request, StreamDocumentIntake $streamDocumentIntake): StreamedResponse
    {
        $messages = collect($request->validated('messages', []));

        /** @var Collection<int, array<string, mixed>> $fileParts */
        $fileParts = $messages
            ->flatMap(fn (array $message) => $message['parts'] ?? [])
            ->filter(fn (array $part) => ($part['type'] ?? null) === 'file')
            ->values();

        if ($fileParts->isEmpty()) {
            throw ValidationException::withMessages([
                'scans' => 'Dodaj przynajmniej jeden obraz do analizy.',
            ]);
        }

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
            ]);

        return $streamDocumentIntake($fileParts, $categories);
    }
}
