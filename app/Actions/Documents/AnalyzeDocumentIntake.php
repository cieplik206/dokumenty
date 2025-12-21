<?php

namespace App\Actions\Documents;

use App\Models\DocumentIntake;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;

class AnalyzeDocumentIntake
{
    private const MAX_IMAGES = 10;

    public function __construct(private DocumentIntakePageGenerator $pageGenerator) {}

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
            ->withMaxTokens(15000)
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
Tytul ma byc opisowy i unikalny (nie kopiuj naglowka dokumentu typu "FAKTURA VAT"). Ma streszczac: typ dokumentu + kluczowy podmiot/przedmiot + data (jesli jest). Przyklady:
- "Faktura Media Markt za MacBook Air 13 z 2025-01-13"
- "Umowa najmu mieszkania Francuska 88/24 z Piotrem Borkiem z 2012-11-11"
Unikaj numerow faktur jako samego tytulu, chyba ze brak innych danych.
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
                $pages = $this->pageGenerator->ensurePages($intake, $media, $remaining);

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
