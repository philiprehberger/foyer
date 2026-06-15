<?php

namespace App\Services\Calendar;

use Carbon\CarbonInterface;

/**
 * In-memory CalendarConnector for tests. Records every operation so tests
 * can assert against them, and lets tests force a failure mode for the
 * "Calendar API failed mid-hold" path.
 */
class FakeCalendarConnector implements CalendarConnector
{
    /** @var array<string, array<int, array{start: CarbonInterface, end: CarbonInterface, title: string, status: string}>> */
    public array $events = [];

    public bool $createShouldFail = false;
    public int $autoIncrement = 0;

    public function createTentativeEvent(
        string $calendarId,
        string $title,
        CarbonInterface $start,
        CarbonInterface $end,
        array $metadata = [],
    ): string {
        if ($this->createShouldFail) {
            throw new \RuntimeException('FakeCalendarConnector: createTentativeEvent forced failure');
        }

        $eventId = 'evt_'.(++$this->autoIncrement);
        $this->events[$calendarId][$eventId] = [
            'start' => $start, 'end' => $end, 'title' => $title, 'status' => 'tentative',
        ];

        return $eventId;
    }

    public function confirmEvent(
        string $calendarId,
        string $eventId,
        string $title,
        array $metadata = [],
    ): void {
        if (isset($this->events[$calendarId][$eventId])) {
            $this->events[$calendarId][$eventId]['title'] = $title;
            $this->events[$calendarId][$eventId]['status'] = 'confirmed';
        }
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        unset($this->events[$calendarId][$eventId]);
    }

    public function listBusy(string $calendarId, CarbonInterface $from, CarbonInterface $to): array
    {
        $out = [];
        foreach ($this->events[$calendarId] ?? [] as $eventId => $row) {
            if ($row['end'] <= $from || $row['start'] >= $to) {
                continue;
            }
            $out[] = [
                'start' => $row['start'],
                'end' => $row['end'],
                'event_id' => $eventId,
            ];
        }

        return $out;
    }

    public function watchChannel(string $calendarId, string $callbackUrl): array
    {
        return [
            'channel_id' => 'fake-channel',
            'resource_id' => 'fake-resource',
            'expires_at' => time() + 86400,
        ];
    }
}
