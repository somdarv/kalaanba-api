<?php

declare(strict_types=1);

use Kalaanba\Modules\Zone\Domain\AreaSuggestionStatus;

it('marks pending as non-terminal', function (): void {
    expect(AreaSuggestionStatus::Pending->isTerminal())->toBeFalse();
});

it('marks approved and rejected as terminal', function (): void {
    expect(AreaSuggestionStatus::Approved->isTerminal())->toBeTrue()
        ->and(AreaSuggestionStatus::Rejected->isTerminal())->toBeTrue();
});

it('parses from canonical string keys', function (): void {
    expect(AreaSuggestionStatus::from('pending'))->toBe(AreaSuggestionStatus::Pending)
        ->and(AreaSuggestionStatus::from('approved'))->toBe(AreaSuggestionStatus::Approved)
        ->and(AreaSuggestionStatus::from('rejected'))->toBe(AreaSuggestionStatus::Rejected);
});
