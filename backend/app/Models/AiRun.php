<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['purpose', 'model', 'prompt_version', 'input_hash', 'status', 'latency_ms', 'usage', 'error', 'subject_type', 'subject_id'])]
class AiRun extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latency_ms' => 'integer',
            'usage' => 'array',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
