<?php

namespace Tests\Support;

use App\Contracts\Chat\BranchClassifierContract;
use App\Services\Chat\BranchClassifierException;
use App\Services\Chat\BranchDecision;
use App\Services\Chat\ContinuationDecision;
use App\Services\Chat\EngagementDecision;

class FakeBranchClassifier implements BranchClassifierContract
{
    public ?string $nextBranchResult = '';

    public string $nextContinuationResult = 'exit';

    public ?string $nextEngagementLevel = EngagementDecision::LEVEL_MEDIUM;

    public string $nextEngagementDecision = EngagementDecision::DECISION_CONTINUE;

    public ?string $nextEngagementRationale = null;

    public bool $shouldThrow = false;

    public int $branchCalls = 0;

    public int $continuationCalls = 0;

    public int $engagementCalls = 0;

    /** @var list<array{question: string, answer: string, recent_turns: array<int, string>}> */
    public array $engagementInvocations = [];

    public ?int $promptVersionId = null;

    public function classifyBranch(string $question, string $answer, array $candidates): BranchDecision
    {
        $this->branchCalls++;

        if ($this->shouldThrow) {
            throw new BranchClassifierException('fake classifier failure');
        }

        return new BranchDecision(
            edgeId: (string) $this->nextBranchResult,
            promptVersionId: $this->promptVersionId,
        );
    }

    public function classifyContinuation(string $question, string $answer): ContinuationDecision
    {
        $this->continuationCalls++;

        if ($this->shouldThrow) {
            throw new BranchClassifierException('fake classifier failure');
        }

        return new ContinuationDecision(
            decision: $this->nextContinuationResult === 'continue' ? 'continue' : 'exit',
            promptVersionId: $this->promptVersionId,
        );
    }

    public function classifyEngagement(string $question, string $answer, array $recentTurns = []): EngagementDecision
    {
        $this->engagementCalls++;
        $this->engagementInvocations[] = [
            'question' => $question,
            'answer' => $answer,
            'recent_turns' => $recentTurns,
        ];

        if ($this->shouldThrow) {
            throw new BranchClassifierException('fake classifier failure');
        }

        return new EngagementDecision(
            engagementLevel: $this->nextEngagementLevel,
            decision: $this->nextEngagementDecision,
            rationale: $this->nextEngagementRationale,
            promptVersionId: $this->promptVersionId,
        );
    }
}
