<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RpsPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['rps_document_id', 'page_number', 'text'])]
class RpsPage extends Model
{
    /** @use HasFactory<RpsPageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RpsDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(RpsDocument::class, 'rps_document_id');
    }
}
