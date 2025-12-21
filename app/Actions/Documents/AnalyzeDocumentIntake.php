<?php

namespace App\Actions\Documents;

use App\Models\DocumentIntake;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;
use Throwable;

class AnalyzeDocumentIntake
{
    private const MAX_IMAGES = 10;

    private const MAX_PDF_PAGES = 10;

    private const PDF_RESOLUTION = 150;

    private const PDF_QUALITY = 80;

    /**
     * @param  Collection<int, array<string, mixed>>  $categories
     * @return array{
     *     fields: array<string, mixed>|null,
     *     extracted_text: string|null,
     *     extracted_content: array<string, mixed>|null,
     *     metadata: array<string, mixed>|null
     * }
     */
    public function __invoke(DocumentIntake $intake, Collection $categories): array
    {
        $images = $this->buildImages($intake);

        if ($images === []) {
            throw ValidationException::withMessages([
                'scans' => 'Nie znaleziono obslugiwanych obrazow do analizy.',
            ]);
        }

        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-5-mini')
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt($this->userPrompt($categories), $images)
            ->withMaxTokens(1500)
            ->withClientOptions(['timeout' => 300])
            ->asText();

        return $this->parseNdjson($response->text);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Jestes asystentem do ekstrakcji danych z dokumentow. Odpowiadasz wylacznie jako NDJSON (po jednym obiekcie JSON na linie).
Kazda linia musi byc poprawnym JSON. Nie dodawaj zadnego innego tekstu ani code fence.
Dozwolone typy:
- {"type":"field","key":"title|notes|category_id|category_name|category_name_new|document_date|received_at|tags","value":...}
- {"type":"extracted_text","value":"..."}
- {"type":"extracted_content","value":{...}}
- {"type":"metadata","value":{...}}
- {"type":"done"}

Wartosci pol nieznanych ustawiaj jako null. Daty w formacie YYYY-MM-DD. Tagi jako tablica stringow bez #.
Ograniczenia dlugosci:
- extracted_text: maksymalnie 4000 znakow (jesli wiecej, utnij).
- extracted_content.summary: maksymalnie 600 znakow.
- extracted_content.key_points: maksymalnie 8 punktow.
- extracted_content.search_text: maksymalnie 1000 znakow.
PROMPT;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $categories
     */
    private function userPrompt(Collection $categories): string
    {
        $categoriesPayload = $categories
            ->map(fn (array $category) => [
                'id' => $category['id'],
                'name' => $category['name'],
                'description' => $category['description'],
            ])
            ->values()
            ->toJson(JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Przeanalizuj zalaczone obrazy dokumentu i wypelnij dane. Dostepne kategorie: {$categoriesPayload}
Jesli brak dopasowania kategorii, zwroc category_id = null oraz category_name_new z propozycja.
Zwracaj dane w kolejnosci:
1) field: title
2) field: notes
3) field: category_id
4) field: category_name
5) field: category_name_new
6) field: document_date
7) field: received_at
8) field: tags
9) extracted_text
10) extracted_content (zawiera: summary, key_points, document_type, entities, dates, amounts, keywords, search_text)
11) metadata (np. confidence 0-1, language, warnings)
12) done
PROMPT;
    }

    /**
     * @return array<int, Image>
     */
    private function buildImages(DocumentIntake $intake): array
    {
        $images = [];

        foreach ($intake->getMedia('scans') as $media) {
            if (count($images) >= self::MAX_IMAGES) {
                break;
            }

            $mediaType = (string) ($media->mime_type ?? '');
            $path = $media->getPath();

            if ($mediaType === '' || $path === '') {
                continue;
            }

            if (Str::startsWith($mediaType, 'image/')) {
                $images[] = Image::fromLocalPath($path);

                continue;
            }

            if ($mediaType === 'application/pdf') {
                $remaining = self::MAX_IMAGES - count($images);
                $pages = $this->ensurePdfPages($intake, $media, $remaining);

                foreach ($pages as $pageMedia) {
                    $pagePath = $pageMedia->getPath();

                    if ($pagePath === '') {
                        continue;
                    }

                    $images[] = Image::fromLocalPath($pagePath);

                    if (count($images) >= self::MAX_IMAGES) {
                        break;
                    }
                }
            }
        }

        return $images;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Media>
     */
    private function ensurePdfPages(DocumentIntake $intake, Media $media, int $limit): \Illuminate\Support\Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $existing = collect($intake->getMedia('pages'))
            ->filter(fn (Media $page) => (int) $page->getCustomProperty('source_media_id') === $media->id)
            ->sortBy(fn (Media $page) => (int) $page->getCustomProperty('page'))
            ->values();

        if ($existing->isNotEmpty()) {
            return $existing->take($limit);
        }

        $pdfPath = $media->getPath();

        if ($pdfPath === '' || ! is_file($pdfPath)) {
            throw new RuntimeException('Nie mozna odczytac pliku PDF.');
        }

        $pdf = (new Pdf($pdfPath))
            ->format(OutputFormat::Png)
            ->quality(self::PDF_QUALITY)
            ->resolution(self::PDF_RESOLUTION)
            ->backgroundColor('white');

        $pageCount = min($pdf->pageCount(), self::MAX_PDF_PAGES, $limit);

        if ($pageCount < 1) {
            return collect();
        }

        $tempDir = storage_path('app/tmp/pdf-pages-'.Str::uuid());

        File::ensureDirectoryExists($tempDir);

        $paths = [];

        try {
            $paths = $pdf
                ->selectPages(...range(1, $pageCount))
                ->save($tempDir, 'page-');
        } catch (Throwable $error) {
            File::deleteDirectory($tempDir);

            throw $error;
        }

        $created = collect();
        $baseName = pathinfo($media->file_name ?? 'page', PATHINFO_FILENAME);
        $baseName = Str::slug($baseName, '_');

        if ($baseName === '') {
            $baseName = 'page';
        }

        foreach ($paths as $index => $path) {
            $pageNumber = $index + 1;
            $fileName = sprintf('%s-%02d.png', $baseName, $pageNumber);

            $pageMedia = $intake->addMedia($path)
                ->usingFileName($fileName)
                ->withCustomProperties([
                    'source_media_id' => $media->id,
                    'page' => $pageNumber,
                ])
                ->toMediaCollection('pages');

            $created->push($pageMedia);
        }

        File::deleteDirectory($tempDir);

        return $created;
    }

    /**
     * @return array{
     *     fields: array<string, mixed>|null,
     *     extracted_text: string|null,
     *     extracted_content: array<string, mixed>|null,
     *     metadata: array<string, mixed>|null
     * }
     */
    private function parseNdjson(string $output): array
    {
        $fields = [];
        $extractedText = null;
        $extractedContent = null;
        $metadata = null;

        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $payload = json_decode($trimmed, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($payload)) {
                continue;
            }

            $type = $payload['type'] ?? null;

            if ($type === 'field' && isset($payload['key'])) {
                $fields[(string) $payload['key']] = $payload['value'] ?? null;

                continue;
            }

            if ($type === 'extracted_text') {
                $value = $payload['value'] ?? null;
                $extractedText = is_string($value) ? $value : null;

                continue;
            }

            if ($type === 'extracted_content') {
                $value = $payload['value'] ?? null;
                $extractedContent = is_array($value) ? $value : null;

                continue;
            }

            if ($type === 'metadata') {
                $value = $payload['value'] ?? null;
                $metadata = is_array($value) ? $value : null;
            }
        }

        return [
            'fields' => $fields === [] ? null : $fields,
            'extracted_text' => $extractedText,
            'extracted_content' => $extractedContent,
            'metadata' => $metadata,
        ];
    }
}
