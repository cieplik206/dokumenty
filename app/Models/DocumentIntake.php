<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DocumentIntake extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\DocumentIntakeFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_FINALIZED = 'finalized';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'document_id',
        'status',
        'original_name',
        'storage_type',
        'fields',
        'extracted_text',
        'extracted_content',
        'ai_metadata',
        'error_message',
        'started_at',
        'finished_at',
        'finalized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'extracted_content' => 'array',
            'ai_metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, DocumentIntake>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Document, DocumentIntake>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('scans')
            ->useDisk('private');
    }
}
