<?php

namespace App\Jobs;

use App\Actions\Documents\AnalyzeDocumentIntake;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentIntake;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Prism\Prism\Exceptions\PrismException;
use Throwable;

class ProcessDocumentIntake implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public DocumentIntake $intake) {}

    /**
     * Execute the job.
     */
    public function handle(AnalyzeDocumentIntake $analyzeDocumentIntake): void
    {
        $intake = $this->intake->fresh();

        if (! $intake instanceof DocumentIntake) {
            return;
        }

        if ($intake->status === DocumentIntake::STATUS_DONE) {
            return;
        }

        $intake->forceFill([
            'status' => DocumentIntake::STATUS_PROCESSING,
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            $categories = Category::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                ]);

            $result = $analyzeDocumentIntake($intake, $categories);
            $fields = $result['fields'] ?? [];

            $title = data_get($fields, 'title');
            $title = is_string($title) ? trim($title) : '';

            if ($title === '') {
                $fallbackName = $intake->original_name
                    ? pathinfo($intake->original_name, PATHINFO_FILENAME)
                    : null;
                $title = $fallbackName !== '' && $fallbackName !== null ? $fallbackName : 'Dokument';
            }

            $tags = data_get($fields, 'tags');

            if (is_array($tags)) {
                $tags = collect($tags)->filter(fn ($tag) => is_string($tag) && $tag !== '')->join(', ');
            }

            $categoryId = data_get($fields, 'category_id');
            $categoryId = is_numeric($categoryId) ? (int) $categoryId : null;

            if (! $categories->pluck('id')->contains($categoryId)) {
                $categoryId = null;
            }

            $documentData = [
                'status' => Document::STATUS_DRAFT,
                'binder_id' => null,
                'category_id' => $categoryId,
                'title' => $title,
                'reference_number' => data_get($fields, 'reference_number'),
                'issuer' => data_get($fields, 'issuer'),
                'document_date' => data_get($fields, 'document_date'),
                'received_at' => data_get($fields, 'received_at'),
                'notes' => data_get($fields, 'notes'),
                'tags' => $tags,
                'extracted_content' => $result['extracted_content'],
                'ai_metadata' => $result['metadata'],
            ];

            $document = $intake->document instanceof Document
                ? tap($intake->document)->update($documentData)
                : Document::create($documentData);

            $intake->refresh();

            foreach ($intake->getMedia('scans') as $media) {
                $media->move($document, 'scans');
            }

            foreach ($intake->getMedia('pages') as $media) {
                $media->move($document, 'pages');
            }

            $intake->forceFill([
                'status' => DocumentIntake::STATUS_DONE,
                'document_id' => $document->id,
                'fields' => $result['fields'],
                'extracted_text' => $result['extracted_text'],
                'extracted_content' => $result['extracted_content'],
                'ai_metadata' => $result['metadata'],
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $error) {
            $message = $error->getMessage();

            if ($error instanceof PrismException && str_contains(strtolower($message), 'max tokens')) {
                $message = 'Dokument jest zbyt obszerny do analizy. Sprobuj mniejszego pliku lub mniej stron.';
            }

            $intake->forceFill([
                'status' => DocumentIntake::STATUS_FAILED,
                'error_message' => $message,
                'finished_at' => now(),
            ])->save();

            throw $error;
        }
    }

    public function failed(Throwable $exception): void
    {
        $intake = $this->intake->fresh();

        if (! $intake instanceof DocumentIntake) {
            return;
        }

        if ($intake->status === DocumentIntake::STATUS_DONE) {
            return;
        }

        $message = $exception->getMessage();

        $intake->forceFill([
            'status' => DocumentIntake::STATUS_FAILED,
            'error_message' => $message !== '' ? $message : 'Analiza zakonczona niepowodzeniem.',
            'finished_at' => now(),
        ])->save();
    }
}
