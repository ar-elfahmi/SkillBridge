<?php

declare(strict_types=1);

namespace App\Modules\Competency\Services;

use App\Enums\VerificationStatus;

final class EvidenceVerificationResult
{
    public function __construct(
        public VerificationStatus $status,
        public ?int $page,
        public string $normalizedEvidence,
    ) {}

    public function isVerified(): bool
    {
        return $this->status === VerificationStatus::Verified;
    }
}
