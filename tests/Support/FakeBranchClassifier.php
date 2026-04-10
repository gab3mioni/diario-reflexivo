<?php

namespace Tests\Support;

use App\Contracts\Chat\BranchClassifierContract;
use App\Services\Chat\BranchClassifierException;

class FakeBranchClassifier implements BranchClassifierContract
{
    public ?string $nextBranchResult = '';
    public string $nextContinuationResult = 'exit';
    public bool $shouldThrow = false;
    public int $branchCalls = 0;
    public int $continuationCalls = 0;

    public function classifyBranch(string $question, string $answer, array $candidates): string
    {
        $this->branchCalls++;

        if ($this->shouldThrow) {
            throw new BranchClassifierException('fake classifier failure');
        }

        return (string) $this->nextBranchResult;
    }

    public function classifyContinuation(string $question, string $answer): string
    {
        $this->continuationCalls++;

        if ($this->shouldThrow) {
            throw new BranchClassifierException('fake classifier failure');
        }

        return $this->nextContinuationResult === 'continue' ? 'continue' : 'exit';
    }
}
