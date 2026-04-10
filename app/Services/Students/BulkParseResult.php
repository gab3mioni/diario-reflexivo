<?php

namespace App\Services\Students;

class BulkParseResult
{
    /**
     * @param array<int, array{line:int, name:string, email:string, subject_id:int}> $valid
     * @param array<int, array{line:int, reason:string}> $invalidFormat
     * @param array<int, array{line:int, email:string, subject_name:string}> $invalidSubject
     * @param array<int, array{line:int, email:string}> $duplicateInBatch
     * @param array<int, array{line:int, email:string}> $emailExists
     */
    public function __construct(
        public readonly array $valid = [],
        public readonly array $invalidFormat = [],
        public readonly array $invalidSubject = [],
        public readonly array $duplicateInBatch = [],
        public readonly array $emailExists = [],
    ) {}

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'invalid_format' => $this->invalidFormat,
            'invalid_subject' => $this->invalidSubject,
            'duplicate_in_batch' => $this->duplicateInBatch,
            'email_exists' => $this->emailExists,
        ];
    }
}
