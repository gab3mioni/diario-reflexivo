<?php

namespace App\Services\Chat;

final class ResolveResult
{
    public function __construct(
        public readonly ?string $nextNodeId,
        public readonly string $classifierStatus,
        public readonly ?string $classifierReason,
    ) {
    }
}
