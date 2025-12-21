<?php

namespace App\Jobs;

use App\Actions\Documents\AnalyzeDocumentIntake;
use App\Models\Category;
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

            $intake->forceFill([
                'status' => DocumentIntake::STATUS_DONE,
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
}
