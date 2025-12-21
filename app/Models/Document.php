<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Document extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_READY = 'ready';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'binder_id',
        'status',
        'category_id',
        'title',
        'reference_number',
        'issuer',
        'document_date',
        'received_at',
        'notes',
        'tags',
        'extracted_content',
        'ai_metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'received_at' => 'date',
            'extracted_content' => 'array',
            'ai_metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Binder, Document>
     */
    public function binder(): BelongsTo
    {
        return $this->belongsTo(Binder::class);
    }

    /**
     * @return BelongsTo<Category, Document>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isPaper(): bool
    {
        return $this->binder_id !== null;
    }

    public function isElectronic(): bool
    {
        return $this->binder_id === null;
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopePaper(Builder $query): Builder
    {
        return $query->whereNotNull('binder_id');
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeElectronic(Builder $query): Builder
    {
        return $query->whereNull('binder_id');
    }

    /**
     * @param  Builder<Document>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Document>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $like = '%'.$search.'%';

                $builder
                    ->where('title', 'like', $like)
                    ->orWhere('reference_number', 'like', $like)
                    ->orWhere('issuer', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('tags', 'like', $like)
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($like) {
                        $categoryQuery->where('name', 'like', $like);
                    });
            });
        }

        if (! empty($filters['binder'])) {
            $query->where('binder_id', $filters['binder']);
        }

        if (! empty($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('document_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('document_date', '<=', $filters['to']);
        }

        return $query;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('scans')
            ->useDisk('private');

        $this->addMediaCollection('pages')
            ->useDisk('private');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if (
            $media instanceof Media
            && $media->collection_name === 'scans'
            && $media->mime_type === 'application/pdf'
        ) {
            return;
        }

        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 150, 200)
            ->format('jpg')
            ->quality(80)
            ->performOnCollections('pages', 'scans')
            ->nonQueued();
    }
}
