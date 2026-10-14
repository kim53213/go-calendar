<?php

declare(strict_types=1);

namespace Console\Entities;

use Console\Services\CalendarService;
use Console\Services\TranslationService;

class LeekDuckEventType
{
    /**
     * The base local file path to the generated ICS assets.
     */
    public const BASE_LOCAL_PATH = __DIR__ . '/../../dist/';

    /**
     * The repository the hosted ICS assets are released from, when not running in GitHub Actions.
     */
    public const DEFAULT_REPOSITORY = 'othyn/go-calendar';

    public function __construct(
        public string $key,
        public string $name,
        public string $title,
        public ?string $short,
        public string $filename,
        public string $filepath,
        public string $url,
    ) {
    }

    /**
     * The base download URL to the hosted ICS assets, following whichever repository (or fork) built them.
     */
    public static function baseDownloadUrl(): string
    {
        $repository = getenv(name: 'GITHUB_REPOSITORY');

        return 'github.com/' . (empty($repository) ? self::DEFAULT_REPOSITORY : $repository) . '/releases/latest/download/';
    }

    /**
     * The data heading into the event type seems consistent, but given its external, never leave it to chance.
     */
    protected static function sanitise(string $type): string
    {
        return trim(
            strtolower(
                preg_replace(
                    pattern: '/[\-\ ]/i',
                    replacement: '_',
                    subject: preg_replace(
                        pattern: '/[^a-z0-9\_\-\ ]/i',
                        replacement: '',
                        subject: $type
                    )
                )
            )
        );
    }

    /**
     * Create an LeekDuckEventType object from the type name.
     */
    public static function create(string $name, string $heading): self
    {
        $key = self::sanitise(
            type: $name
        );

        $filename = $name === CalendarService::EVERYTHING_CALENDAR_NAME
            ? 'gocal.ics'
            : "gocal__{$key}.ics";

        $translated = $key === CalendarService::EVERYTHING_CALENDAR_KEY
            ? ['title' => TranslationService::string(key: 'calendar.everything', english: $heading), 'short' => null]
            : TranslationService::eventType(key: $key, heading: $heading);

        return new self(
            key: $key,
            name: $name,
            title: $translated['title'],
            short: $translated['short'],
            filename: $filename,
            filepath: self::BASE_LOCAL_PATH . $filename,
            url: self::baseDownloadUrl() . $filename
        );
    }
}
