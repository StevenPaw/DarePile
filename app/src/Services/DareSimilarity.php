<?php

namespace App\Services;

use App\Models\Card;

/**
 * Finds official cards whose dare text is similar to a given text.
 *
 * The score (0–100) combines two measures so that both rephrasings and small
 * spelling differences are caught:
 *  - word overlap (Dice coefficient on normalised, stop-word-free words), weighted 60 %
 *  - character similarity (similar_text on the normalised string), weighted 40 %
 */
class DareSimilarity
{
    /** Minimum score for a card to count as "similar". */
    public const THRESHOLD = 40;

    /** Common German filler words that carry no meaning for comparison. */
    private const STOP_WORDS = [
        'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'eines',
        'und', 'oder', 'mit', 'an', 'am', 'auf', 'aus', 'bei', 'bis', 'fuer', 'in', 'im', 'zu', 'zum',
        'zur', 'von', 'vom', 'wie', 'so', 'als', 'du', 'dich', 'dir', 'dein', 'deine', 'deinen',
        'deinem', 'deiner', 'ich', 'mich', 'mir', 'es', 'ist', 'sei', 'nicht', 'noch', 'nach', 'ueber',
        'unter', 'vor', 'auch', 'jemand', 'jemanden', 'jemandem', 'selbst', 'euch', 'eure', 'euren',
        'einander', 'gegenseitig', 'hier', 'diese', 'dieser', 'aufgabe', 'aufgaben',
        // Generic verbs that start many dares
        'gib', 'gebe', 'lass', 'lasse', 'mach', 'mache', 'nimm', 'nehm', 'nehme',
        // Placeholders ([Player], [Time]) appear in many dares and say nothing about the content
        'player', 'time',
    ];

    /** @var array<int, array{card: Card, normalised: string, words: string[]}>|null */
    private static ?array $officialCache = null;

    /**
     * @return array<int, array{card: Card, score: int}> Matches sorted by score, best first
     */
    public static function findSimilarOfficial(
        string $dare,
        int $limit = 5,
        int $threshold = self::THRESHOLD,
        ?int $excludeId = null
    ): array {
        $normalised = self::normalise($dare);
        $words = self::words($normalised);
        $matches = [];

        foreach (self::officialCards() as $id => $official) {
            if ($id === $excludeId) {
                continue;
            }
            $score = self::score($normalised, $words, $official['normalised'], $official['words']);
            if ($score >= $threshold) {
                $matches[] = ['card' => $official['card'], 'score' => $score];
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $limit);
    }

    public static function compare(string $a, string $b): int
    {
        $na = self::normalise($a);
        $nb = self::normalise($b);

        return self::score($na, self::words($na), $nb, self::words($nb));
    }

    /** Forget cached official cards, e.g. after a card was promoted. */
    public static function reset(): void
    {
        self::$officialCache = null;
    }

    /**
     * @param string[] $wordsA
     * @param string[] $wordsB
     */
    private static function score(string $a, array $wordsA, string $b, array $wordsB): int
    {
        if ($a === '' || $b === '') {
            return 0;
        }
        if ($a === $b) {
            return 100;
        }

        $wordScore = 0.0;
        if ($wordsA && $wordsB) {
            $common = count(array_intersect($wordsA, $wordsB));
            $wordScore = 2 * $common / (count($wordsA) + count($wordsB)) * 100;
        }

        similar_text($a, $b, $charScore);

        // Word overlap is the better signal for meaning, characters catch typos
        return (int) round($wordScore * 0.6 + $charScore * 0.4);
    }

    private static function normalise(string $text): string
    {
        $text = mb_strtolower($text);
        // Placeholders like [Player] or [Time] become plain words
        $text = preg_replace('/\[(\w+)\]/u', ' $1 ', $text) ?? $text;
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Unique meaningful words with a crude stem (common German endings removed)
     * so that "tanze" / "tanzen" / "tanzt" match.
     *
     * @return string[]
     */
    private static function words(string $normalised): array
    {
        $words = [];
        foreach (explode(' ', $normalised) as $word) {
            if ($word === '' || in_array($word, self::STOP_WORDS, true)) {
                continue;
            }
            if (strlen($word) > 4) {
                $word = preg_replace('/(en|er|es|em|st|e|n|s|t)$/', '', $word) ?? $word;
            }
            $words[$word] = true;
        }

        return array_keys($words);
    }

    /**
     * @return array<int, array{card: Card, normalised: string, words: string[]}>
     */
    private static function officialCards(): array
    {
        if (self::$officialCache === null) {
            self::$officialCache = [];
            foreach (Card::get()->filter('Official', true) as $card) {
                $normalised = self::normalise((string) $card->Dare);
                self::$officialCache[$card->ID] = [
                    'card' => $card,
                    'normalised' => $normalised,
                    'words' => self::words($normalised),
                ];
            }
        }

        return self::$officialCache;
    }
}
