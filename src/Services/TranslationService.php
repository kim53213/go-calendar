<?php

declare(strict_types=1);

namespace Console\Services;

class TranslationService
{
    /**
     * The base local path to the translation tables, one directory per locale.
     */
    public const BASE_PATH = __DIR__ . '/../../translations/';

    /**
     * Locale used when GOCAL_LOCALE is not set. Use 'en' to get the original English calendars.
     */
    public const DEFAULT_LOCALE = 'zh-Hant';

    /**
     * Matches whitespace between two CJK characters, or on either side of full width punctuation, which only
     * exists because English words were swapped out for Chinese ones.
     */
    protected const CJK_GAP_PATTERN = '/(?<=\p{Han})\s+(?=\p{Han})|\s+(?=[\x{3000}-\x{303F}\x{FF00}-\x{FFEF}])|(?<=[\x{3000}-\x{303F}\x{FF00}-\x{FFEF}])\s+/u';

    /**
     * Parsed translations.json for the current locale.
     */
    protected static array $table;

    /**
     * Compiled term regex and its lookup map.
     */
    protected static ?string $termPattern = null;

    protected static array $terms;

    public static function locale(): string
    {
        $locale = getenv(name: 'GOCAL_LOCALE');

        return empty($locale) ? self::DEFAULT_LOCALE : $locale;
    }

    public static function isTranslating(): bool
    {
        return self::locale() !== 'en';
    }

    /**
     * Loads translations.json (and pokemon.json, if built) for the current locale.
     *
     * @throws \JsonException
     */
    protected static function table(): array
    {
        if (empty(self::$table)) {
            $directory = self::BASE_PATH . self::locale() . '/';

            self::$table = json_decode(
                json: file_get_contents(
                    filename: $directory . 'translations.json'
                ),
                associative: true,
                flags: JSON_THROW_ON_ERROR
            );

            self::$table['pokemon'] = file_exists(filename: $directory . 'pokemon.json')
                ? json_decode(
                    json: file_get_contents(
                        filename: $directory . 'pokemon.json'
                    ),
                    associative: true,
                    flags: JSON_THROW_ON_ERROR
                )
                : [];
        }

        return self::$table;
    }

    /**
     * Translate a fixed UI string, replacing any {placeholders}. Falls back to the given English string.
     */
    public static function string(string $key, string $english, array $replace = []): string
    {
        $string = self::isTranslating()
            ? (self::table()['strings'][$key] ?? $english)
            : $english;

        foreach ($replace as $placeholder => $value) {
            $string = str_replace(search: "{{$placeholder}}", replace: (string) $value, subject: $string);
        }

        return $string;
    }

    /**
     * Translate an event type heading by its sanitised key, e.g. 'community_day'.
     *
     * @return array{title: string, short: ?string}
     */
    public static function eventType(string $key, string $heading): array
    {
        if (! self::isTranslating()) {
            return ['title' => $heading, 'short' => null];
        }

        $translated = self::table()['event_types'][$key] ?? null;

        return [
            'title' => $translated['title'] ?? self::text(text: $heading),
            'short' => $translated['short'] ?? null,
        ];
    }

    /**
     * Translate free text, such as an event name. Whole sentence patterns are tried first, then known terms and
     * Pokémon names are swapped in, then connecting words and punctuation. Anything unknown is left in English.
     */
    public static function text(string $text): string
    {
        if (! self::isTranslating()) {
            return $text;
        }

        $table = self::table();

        foreach ($table['patterns'] ?? [] as [$pattern, $replacement]) {
            $replaced = preg_replace(pattern: "/{$pattern}/u", replacement: $replacement, subject: $text, limit: 1, count: $count);

            if ($count > 0) {
                $text = $replaced;
                break;
            }
        }

        $text = preg_replace_callback(
            pattern: self::termPattern(),
            callback: fn (array $match): string => ' ' . self::$terms[$match[0]] . ' ',
            subject: $text
        );

        $text = str_replace(
            search: array_keys($table['connectors'] ?? []),
            replace: array_values($table['connectors'] ?? []),
            subject: $text
        );

        return trim(
            preg_replace(
                pattern: '/ {2,}/',
                replacement: ' ',
                subject: preg_replace(pattern: self::CJK_GAP_PATTERN, replacement: '', subject: $text)
            )
        );
    }

    /**
     * Builds one regex of every term and Pokémon name, longest first so 'Mega Raids' wins over 'Mega', and
     * 'Mewtwo' over 'Mew'. Only whole words are matched.
     */
    protected static function termPattern(): string
    {
        if (self::$termPattern === null) {
            $table = self::table();

            // Hand written terms win over generated Pokémon names
            self::$terms = array_merge($table['pokemon'], $table['terms'] ?? []);

            $keys = array_keys(self::$terms);

            usort($keys, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

            self::$termPattern = '/(?<![\p{L}\p{N}])(?:'
                . implode(separator: '|', array: array_map(callback: fn (string $key): string => preg_quote(str: $key, delimiter: '/'), array: $keys))
                . ')(?![\p{L}\p{N}])/u';
        }

        return self::$termPattern;
    }
}
