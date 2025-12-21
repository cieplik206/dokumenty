<?php

namespace App\Actions\Documents;

use App\Models\DocumentIntake;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;
use RuntimeException;

class AnalyzeDocumentIntake
{
    private const MAX_IMAGES = 6;

    private ?string $pdftoppmBinary = null;

    private bool $pdftoppmResolved = false;

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
                $images = array_merge($images, $this->imagesFromPdf($path, $remaining));
            }
        }

        return $images;
    }

    /**
     * @return array<int, Image>
     */
    private function imagesFromPdf(string $pdfPath, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        if (! is_file($pdfPath)) {
            throw new RuntimeException('Nie mozna odczytac pliku PDF.');
        }

        return $this->convertPdfToImages($pdfPath, $limit);
    }

    /**
     * @return array<int, Image>
     */
    private function convertPdfToImages(string $pdfPath, int $limit): array
    {
        $binary = $this->resolvePdftoppmBinary();

        if ($binary === null) {
            throw ValidationException::withMessages([
                'scans' => 'Brak narzedzia pdftoppm do konwersji PDF. Upewnij sie, ze jest dostepny w PATH procesu PHP lub dodaj obrazy.',
            ]);
        }

        $outputPrefix = tempnam(sys_get_temp_dir(), 'doc-pages-');

        if ($outputPrefix === false) {
            throw new RuntimeException('Nie mozna utworzyc plikow tymczasowych.');
        }

        @unlink($outputPrefix);

        $result = Process::run([
            $binary,
            '-f',
            '1',
            '-l',
            (string) $limit,
            '-r',
            '150',
            '-png',
            $pdfPath,
            $outputPrefix,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException('Nie udalo sie przekonwertowac PDF do obrazow.');
        }

        $paths = collect(glob($outputPrefix.'-*.png') ?: [])
            ->sort()
            ->values();

        $images = [];

        foreach ($paths as $path) {
            $images[] = Image::fromLocalPath($path);
        }

        foreach ($paths as $path) {
            @unlink($path);
        }

        return $images;
    }

    private function resolvePdftoppmBinary(): ?string
    {
        if ($this->pdftoppmResolved) {
            return $this->pdftoppmBinary;
        }

        $candidates = [
            'pdftoppm',
            '/opt/homebrew/bin/pdftoppm',
            '/usr/local/bin/pdftoppm',
            '/usr/bin/pdftoppm',
        ];

        foreach ($candidates as $candidate) {
            $result = Process::run([$candidate, '-v']);

            if ($result->successful()) {
                $this->pdftoppmResolved = true;
                $this->pdftoppmBinary = $candidate;

                return $candidate;
            }
        }

        $this->pdftoppmResolved = true;
        $this->pdftoppmBinary = null;

        return null;
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
