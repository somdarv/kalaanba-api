<?php

declare(strict_types=1);

use Kalaanba\Modules\Club\Domain\ClubNamePolicy;
use Kalaanba\Modules\Club\Domain\ReservedClubName;

/**
 * The reserved-name matcher (ADR-0017 §3).
 *
 * Driven as a table because the decision this class encodes IS a table: which
 * names are somebody else's and which merely share a word with one. The
 * allowed cases matter more than the refused ones. Refusing "Manchester
 * United" is obvious; letting "Kotoko Boys" through is the judgement, and it is
 * the one a future change is most likely to break.
 */
function namePolicy(): ClubNamePolicy
{
    return new ClubNamePolicy(
        [
            new ReservedClubName('Asante Kotoko', ['kotoko', 'porcupine warriors']),
            new ReservedClubName('Manchester United', ['man united', 'man utd', 'red devils']),
            new ReservedClubName('Hearts of Oak', ['phobia']),
            new ReservedClubName('Bayern Munich', ['bayern munchen']),
        ],
        ['fc', 'f.c.', 'sc', 'afc', 'football', 'club', 'team'],
    );
}

it('refuses a name that is exactly a protected club', function (string $candidate, string $expected): void {
    expect(namePolicy()->reservedMatchFor($candidate))->toBe($expected);
})->with([
    ['Asante Kotoko', 'Asante Kotoko'],
    ['asante kotoko', 'Asante Kotoko'],
    ['ASANTE KOTOKO', 'Asante Kotoko'],
    ['  Asante   Kotoko  ', 'Asante Kotoko'],
    ['Manchester United', 'Manchester United'],
    ['Hearts of Oak', 'Hearts of Oak'],
]);

it('refuses a protected name with a generic football word attached', function (string $candidate): void {
    expect(namePolicy()->reservedMatchFor($candidate))->toBe('Asante Kotoko');
})->with([
    'Asante Kotoko FC',
    'Asante Kotoko F.C.',
    'Asante Kotoko Football Club',
    'FC Asante Kotoko',
]);

it('refuses a protected name wherever it appears in a longer one', function (string $candidate, string $expected): void {
    expect(namePolicy()->reservedMatchFor($candidate))->toBe($expected);
})->with([
    ['Tamale Manchester United', 'Manchester United'],
    ['Manchester United Tamale', 'Manchester United'],
    ['Real Asante Kotoko', 'Asante Kotoko'],
    ['Young Hearts of Oak Tamale', 'Hearts of Oak'],
]);

it('refuses an alias only when it is the whole name', function (): void {
    // "kotoko" alone is a claim on the club.
    expect(namePolicy()->reservedMatchFor('Kotoko'))->toBe('Asante Kotoko');
    expect(namePolicy()->reservedMatchFor('Man Utd'))->toBe('Manchester United');
    expect(namePolicy()->reservedMatchFor('Porcupine Warriors'))->toBe('Asante Kotoko');

    // The same word inside a longer name is not. This is the case the whole
    // asymmetry exists for: "kotoko" is the Twi word for porcupine, and a
    // Tamale side called Kotoko Boys is nobody's impersonator.
    expect(namePolicy()->reservedMatchFor('Kotoko Boys'))->toBeNull();
    expect(namePolicy()->reservedMatchFor('Kotoko Stars Tamale'))->toBeNull();
});

it('allows an ordinary grassroots name', function (string $candidate): void {
    expect(namePolicy()->reservedMatchFor($candidate))->toBeNull();
})->with([
    'Taha Stars',
    'Aboabo United',
    'Bantama Boys',
    'Northern Warriors',
    // Shares one word with a protected club and nothing else.
    'Hearts FC',
    'Young Chelsea',
    'United Brothers',
]);

it('does not match a protected name inside a longer word', function (): void {
    // Token-wise, not substring: "Manchester" must stand on its own.
    expect(namePolicy()->reservedMatchFor('Manchesterville Rovers'))->toBeNull();
});

it('folds accents so an umlaut cannot dodge the list', function (): void {
    expect(namePolicy()->reservedMatchFor('Bayern München'))->toBe('Bayern Munich');
});

it('allows everything when no terms are configured', function (): void {
    // The deliberate fail-open. An unseeded config must not refuse every name
    // on the platform; see ClubVocabulary::namePolicy().
    $empty = new ClubNamePolicy([], ['fc']);

    expect($empty->reservedMatchFor('Asante Kotoko'))->toBeNull();
});

it('ignores a name that is nothing but generic words', function (): void {
    // "FC" alone normalises to no tokens at all. It must not match the first
    // reserved entry by accident.
    expect(namePolicy()->reservedMatchFor('FC'))->toBeNull();
});
