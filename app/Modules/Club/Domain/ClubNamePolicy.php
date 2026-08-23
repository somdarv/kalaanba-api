<?php

declare(strict_types=1);

namespace Kalaanba\Modules\Club\Domain;

/**
 * Decides whether a club name is one that belongs to somebody else
 * (engine doc §11 puts Name under the Club engine; ADR-0017 records why the
 * gate lives here and not in Moderation).
 *
 * Pure: it is handed its terms and its ignored tokens and reads no config, no
 * database and no framework. Resolving those from Admin Configuration is the
 * Application layer's job (`ClubVocabulary`), which keeps this class
 * exhaustively testable as a table of names and verdicts. That class is named
 * without a `{@see}` on purpose: Domain may not depend on Application, and
 * Pint's import fixer turns a fully-qualified docblock reference into a real
 * `use` statement that Deptrac then rejects.
 *
 * It answers WHICH club a name collides with, not merely whether it does, so
 * the caller can decide what that means. A `local` club is refused; an
 * `official` one is allowed to claim it and the collision becomes the thing the
 * document review checks (§10).
 */
final readonly class ClubNamePolicy
{
    /**
     * Lowercase Latin accents folded onto their base letter. Lowercase only:
     * every caller has already lowered the string before this runs.
     */
    private const DIACRITIC_FOLD = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
        'š' => 's', 'ž' => 'z', 'ß' => 'ss',
        // Ghanaian orthography: Twi and Ewe open vowels.
        'ɛ' => 'e', 'ɔ' => 'o', 'ŋ' => 'n', 'ɖ' => 'd', 'ƒ' => 'f',
    ];

    /**
     * @param  list<ReservedClubName>  $reservedNames
     * @param  list<string>  $ignoredTokens  Generic football words dropped before
     *                                       matching, so "Manchester United FC"
     *                                       cannot walk past a block on
     *                                       "Manchester United".
     */
    public function __construct(
        private array $reservedNames = [],
        private array $ignoredTokens = [],
    ) {}

    /**
     * The canonical name this candidate collides with, or null if it is free.
     *
     * Returns the canonical rather than a boolean so an admin reviewing a claim,
     * or an audit entry recording a refusal, can say which club was at stake.
     */
    public function reservedMatchFor(string $candidate): ?string
    {
        $tokens = $this->normalise($candidate);
        if ($tokens === []) {
            return null;
        }

        foreach ($this->reservedNames as $reserved) {
            if ($this->matches($reserved, $tokens)) {
                return $reserved->canonical;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $candidateTokens
     */
    private function matches(ReservedClubName $reserved, array $candidateTokens): bool
    {
        $canonical = $this->normalise($reserved->canonical);
        if ($canonical !== [] && $this->containsSequence($candidateTokens, $canonical)) {
            return true;
        }

        foreach ($reserved->aliases as $alias) {
            $aliasTokens = $this->normalise($alias);
            if ($aliasTokens !== [] && $aliasTokens === $candidateTokens) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $needle appears in $haystack as a run of whole tokens.
     *
     * Token-wise rather than substring, so "Manchester" does not match inside
     * "Manchesterville" and a name is only caught when the protected words
     * actually stand on their own.
     *
     * @param  list<string>  $haystack
     * @param  list<string>  $needle
     */
    private function containsSequence(array $haystack, array $needle): bool
    {
        $span = count($needle);
        $limit = count($haystack) - $span;

        for ($offset = 0; $offset <= $limit; $offset++) {
            if (array_slice($haystack, $offset, $span) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lowercase, strip diacritics and punctuation, drop the ignored tokens,
     * split on whitespace.
     *
     * @return list<string>
     */
    private function normalise(string $value): array
    {
        $folded = $this->stripDiacritics(mb_strtolower(trim($value), 'UTF-8'));

        // Punctuation becomes a space rather than nothing, so "F.C." collapses
        // to the token "f c" and not to the word "fc" glued onto its neighbour.
        $spaced = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $folded) ?? '';

        $ignored = $this->ignoredTokenSet();

        $tokens = [];
        foreach (preg_split('/\s+/u', trim($spaced)) ?: [] as $token) {
            if ($token !== '' && ! isset($ignored[$token])) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * Ignored tokens normalised the same way the candidate is, so an entry
     * written as "f.c." in config still matches the token "f" then "c".
     *
     * @return array<string, true>
     */
    private function ignoredTokenSet(): array
    {
        $set = [];
        foreach ($this->ignoredTokens as $raw) {
            $folded = $this->stripDiacritics(mb_strtolower(trim($raw), 'UTF-8'));
            $spaced = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $folded) ?? '';
            foreach (preg_split('/\s+/u', trim($spaced)) ?: [] as $token) {
                if ($token !== '') {
                    $set[$token] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Fold accented characters onto their base letters, so "Atlético" and
     * "Atletico" are the same name.
     *
     * `ext-intl` is deliberately not used. It is not declared in composer.json
     * and is missing from plenty of PHP builds, and a name policy that quietly
     * stops folding accents on one host is a rule that differs by server. The
     * table covers the Latin accents a Ghanaian or European club name actually
     * uses, which is a much smaller set than full Unicode and does not need an
     * extension to cover it.
     */
    private function stripDiacritics(string $value): string
    {
        return strtr($value, self::DIACRITIC_FOLD);
    }
}
