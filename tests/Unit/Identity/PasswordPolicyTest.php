<?php

declare(strict_types=1);

use Kalaanba\Modules\Identity\Domain\Registration\PasswordPolicy;

it('accepts passwords meeting every rule', function (): void {
    $policy = new PasswordPolicy(
        minLength: 10,
        requireMixedCase: true,
        requireNumber: true,
        requireSymbol: true,
    );

    expect($policy->evaluate('Abcdef1234!'))->toBe([]);
});

it('returns auth.password.too_short below the configured minimum', function (): void {
    $policy = new PasswordPolicy(10, false, false, false);

    expect($policy->evaluate('short1'))->toBe(['auth.password.too_short']);
});

it('flags missing mixed case when required', function (): void {
    $policy = new PasswordPolicy(8, true, false, false);

    expect($policy->evaluate('alllower1'))->toBe(['auth.password.require_mixed_case']);
});

it('flags missing digits when required', function (): void {
    $policy = new PasswordPolicy(8, false, true, false);

    expect($policy->evaluate('NoDigitsHere'))->toBe(['auth.password.require_number']);
});

it('flags missing symbols when required', function (): void {
    $policy = new PasswordPolicy(8, false, false, true);

    expect($policy->evaluate('NoSymbol12'))->toBe(['auth.password.require_symbol']);
});

it('accumulates every violation in one pass', function (): void {
    $policy = new PasswordPolicy(12, true, true, true);

    expect($policy->evaluate('short'))
        ->toBe([
            'auth.password.too_short',
            'auth.password.require_mixed_case',
            'auth.password.require_number',
            'auth.password.require_symbol',
        ]);
});

it('does not enforce disabled rules', function (): void {
    $policy = new PasswordPolicy(4, false, false, false);

    expect($policy->evaluate('abcd'))->toBe([]);
});
