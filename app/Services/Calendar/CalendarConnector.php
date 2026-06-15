<?php

namespace App\Services\Calendar;

use Carbon\CarbonInterface;

/**
 * Wraps the Google Calendar API surface Foyer actually uses.
 *
 * In production this is bound to GoogleCalendarConnector. Tests bind it to
 * FakeCalendarConnector, which is just an in-memory map. Everything that
 * touches the calendar should go through this interface — never new'd up
 * directly — so the slot-hold transaction can be exercised under the
 * "Calendar failed" branch without hitting the network.
 */
interface CalendarConnector
{
    public function createTentativeEvent(
        string $calendarId,
        string $title,
        CarbonInterface $start,
        CarbonInterface $end,
        array $metadata = [],
    ): string;

    public function confirmEvent(
        string $calendarId,
        string $eventId,
        string $title,
        array $metadata = [],
    ): void;

    public function deleteEvent(string $calendarId, string $eventId): void;

    /**
     * @return array<int, array{start: CarbonInterface, end: CarbonInterface, event_id: string}>
     */
    public function listBusy(
        string $calendarId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array;

    public function watchChannel(string $calendarId, string $callbackUrl): array;
}
