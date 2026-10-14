<?php

declare(strict_types=1);

namespace Console\Entities;

use Console\Services\CalendarService;
use Console\Services\TranslationService;
use Spatie\IcalendarGenerator\Components\Calendar;

class CalendarManifest
{
    public function __construct(
        public LeekDuckEventType $eventType,
        public Calendar $calendar,
    ) {
    }

    /**
     * Create a new CalendarManifest object.
     */
    public static function create(LeekDuckEventType $eventType): self
    {
        $isEverything = $eventType->key === CalendarService::EVERYTHING_CALENDAR_KEY;

        $manifest = new self(
            eventType: $eventType,
            calendar: Calendar::create()
                ->name(
                    name: $isEverything
                        ? TranslationService::string(key: 'calendar.name_everything', english: 'GO Calendar - ' . $eventType->title)
                        : TranslationService::string(key: 'calendar.name', english: 'GO Calendar - {title} ({acronym})', replace: [
                            'title' => $eventType->title,
                            'acronym' => acronymForEventType($eventType),
                        ])
                )
                ->description(
                    description: $isEverything
                        ? TranslationService::string(key: 'calendar.description_everything', english: 'All Pokémon GO events, in your local time, auto-updated and sourced from Leek Duck.')
                        : TranslationService::string(key: 'calendar.description', english: 'All Pokémon GO {title} events, in your local time, auto-updated and sourced from Leek Duck.', replace: [
                            'title' => $eventType->title,
                        ])
                )
                ->refreshInterval(
                    minutes: 1440 // 1 day
                )
        );

        if (CalendarService::isFloating()) {
            $manifest->calendar
                ->withoutAutoTimezoneComponents()
                ->withoutTimezone();
        }

        return $manifest;
    }
}
