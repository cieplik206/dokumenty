<?php

namespace App\Actions\Documents;

use App\Models\DocumentIntake;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Imagick;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\PdfToImage\Enums\OutputFormat;
use Spatie\PdfToImage\Pdf;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class DocumentIntakePageGenerator
{
    public const MAX_PDF_PAGES = 10;

    private const PDF_RESOLUTION = 75;

    private const PDF_QUALITY = 70;

    private static bool $ghostscriptChecked = false;

    /**
     * @return Collection<int, Media>
     */
    public function ensurePages(DocumentIntake $intake, Media $media, int $limit): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        if ($media->mime_type !== 'application/pdf') {
            return collect();
        }

        $this->ensureGhostscriptAvailable();

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
            ->format(OutputFormat::Jpg)
            ->quality(self::PDF_QUALITY)
            ->resolution(self::PDF_RESOLUTION)
            ->backgroundColor('white');

        $pageCount = min($pdf->pageCount(), self::MAX_PDF_PAGES, $limit);

        if ($pageCount < 1) {
            return collect();
        }

        $tempDir = storage_path('app/tmp/pdf-pages-'.Str::uuid());

        File::ensureDirectoryExists($tempDir);

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
            $this->normalizeJpegBitDepth($path);

            $pageNumber = $index + 1;
            $fileName = sprintf('%s-%02d.jpg', $baseName, $pageNumber);

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

    private function normalizeJpegBitDepth(string $path): void
    {
        $imagick = new Imagick;
        $imagick->readImage($path);
        $imagick->setImageDepth(8);
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(self::PDF_QUALITY);
        $imagick->writeImage($path);
        $imagick->clear();
        $imagick->destroy();
    }

    private function ensureGhostscriptAvailable(): void
    {
        if (self::$ghostscriptChecked) {
            return;
        }

        self::$ghostscriptChecked = true;

        $finder = new ExecutableFinder;
        $gsPath = $finder->find('gs', null, [
            '/opt/homebrew/bin',
            '/usr/local/bin',
            '/usr/bin',
            '/bin',
        ]);

        if (! $gsPath) {
            return;
        }

        $gsDir = dirname($gsPath);
        $currentPath = (string) getenv('PATH');
        $segments = $currentPath !== '' ? explode(':', $currentPath) : [];

        if (! in_array($gsDir, $segments, true)) {
            $segments[] = $gsDir;
            $currentPath = implode(':', $segments);
            putenv('PATH='.$currentPath);
        }

        if (getenv('MAGICK_GHOSTSCRIPT_PATH') === false) {
            putenv('MAGICK_GHOSTSCRIPT_PATH='.$gsDir);
        }
    }
}
