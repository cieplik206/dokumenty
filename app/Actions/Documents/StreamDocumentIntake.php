<?php

namespace App\Actions\Documents;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamDocumentIntake
{
    private const MAX_IMAGES = 10;

    private ?string $pdftoppmBinary = null;

    private bool $pdftoppmResolved = false;

    /**
     * @param  Collection<int, array<string, mixed>>  $fileParts
     * @param  Collection<int, array<string, mixed>>  $categories
     */
    public function __invoke(Collection $fileParts, Collection $categories): StreamedResponse
    {
        $images = $this->buildImages($fileParts);

        if ($images === []) {
            throw ValidationException::withMessages([
                'scans' => 'Nie znaleziono obslugiwanych obrazow do analizy.',
            ]);
        }

        return Prism::text()
            ->using(Provider::OpenAI, 'gpt-5-mini')
            ->withSystemPrompt($this->systemPrompt())
            ->withPrompt($this->userPrompt($categories), $images)
            ->withClientOptions(['timeout' => 300])
            ->asDataStreamResponse();
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
     * @param  Collection<int, array<string, mixed>>  $fileParts
     * @return array<int, Image>
     */
    private function buildImages(Collection $fileParts): array
    {
        $images = [];

        foreach ($fileParts as $filePart) {
            if (count($images) >= self::MAX_IMAGES) {
                break;
            }

            $mediaType = (string) ($filePart['mediaType'] ?? '');
            $url = (string) ($filePart['url'] ?? '');

            if ($mediaType === '' || $url === '') {
                continue;
            }

            if (Str::startsWith($mediaType, 'image/')) {
                $images[] = $this->imageFromUrl($url, $mediaType);

                continue;
            }

            if ($mediaType === 'application/pdf') {
                $remaining = self::MAX_IMAGES - count($images);
                $images = array_merge($images, $this->imagesFromPdf($url, $remaining));
            }
        }

        return $images;
    }

    private function imageFromUrl(string $url, string $mediaType): Image
    {
        if (Str::startsWith($url, 'data:')) {
            [$mimeType, $base64] = $this->parseDataUrl($url);

            return Image::fromBase64($base64, $mimeType ?? $mediaType);
        }

        return Image::fromUrl($url, $mediaType);
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function parseDataUrl(string $url): array
    {
        $parts = explode(',', $url, 2);

        if (count($parts) !== 2) {
            throw new RuntimeException('Nieprawidlowy format data URL.');
        }

        $meta = $parts[0];
        $data = $parts[1];

        if (! Str::startsWith($meta, 'data:')) {
            throw new RuntimeException('Nieprawidlowy format data URL.');
        }

        $meta = substr($meta, 5);
        $metaParts = explode(';', $meta);
        $mimeType = $metaParts[0] !== '' ? $metaParts[0] : null;

        return [$mimeType, $data];
    }

    /**
     * @return array<int, Image>
     */
    private function imagesFromPdf(string $url, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        if (! Str::startsWith($url, 'data:')) {
            throw new RuntimeException('PDF musi byc przekazany jako data URL.');
        }

        [$mimeType, $base64] = $this->parseDataUrl($url);

        if ($mimeType !== null && $mimeType !== 'application/pdf') {
            throw new RuntimeException('Nieprawidlowy typ PDF.');
        }

        $pdfPath = $this->writeTempFile(base64_decode($base64, true), 'pdf');

        try {
            return $this->convertPdfToImages($pdfPath, $limit);
        } finally {
            @unlink($pdfPath);
        }
    }

    private function writeTempFile(false|string $contents, string $extension): string
    {
        if ($contents === false) {
            throw new RuntimeException('Nie mozna odczytac zawartosci pliku.');
        }

        $path = tempnam(sys_get_temp_dir(), 'doc-');

        if ($path === false) {
            throw new RuntimeException('Nie mozna utworzyc pliku tymczasowego.');
        }

        $finalPath = $path.'.'.$extension;
        rename($path, $finalPath);
        file_put_contents($finalPath, $contents);

        return $finalPath;
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
}
