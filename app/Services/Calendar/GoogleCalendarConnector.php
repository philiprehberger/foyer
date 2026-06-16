<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Google\Client as GoogleClient;
use Google\Service\Calendar as CalendarService;
use Google\Service\Calendar\Channel;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

/**
 * Real Google Calendar adapter.
 *
 * Scope is calendar.events only — never request full-calendar access. Refresh
 * tokens come from the business's `oauth_refresh_token_ref` (a pointer to the
 * KMS-managed secret store; the controller resolves the actual token before
 * constructing the client).
 *
 * Phase 4 wires the core methods. The HTTP boundary is the only place the
 * Google SDK touches; everything else flows through the CalendarConnector
 * interface so tests can swap in FakeCalendarConnector.
 */
class GoogleCalendarConnector implements CalendarConnector
{
    public function __construct(private readonly CalendarService $service) {}

    public static function forRefreshToken(string $refreshToken): self
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->refreshToken($refreshToken);

        return new self(new CalendarService($client));
    }

    public function createTentativeEvent(
        string $calendarId,
        string $title,
        CarbonInterface $start,
        CarbonInterface $end,
        array $metadata = [],
    ): string {
        $event = new Event([
            'summary' => $title,
            'description' => $metadata['description'] ?? null,
            'start' => $this->date($start),
            'end' => $this->date($end),
            'transparency' => 'opaque',
            'status' => 'tentative',
            'extendedProperties' => [
                'private' => array_merge(['foyer' => 'true'], $metadata),
            ],
        ]);

        $created = $this->service->events->insert($calendarId, $event);

        return $created->getId();
    }

    public function confirmEvent(
        string $calendarId,
        string $eventId,
        string $title,
        array $metadata = [],
    ): void {
        $event = $this->service->events->get($calendarId, $eventId);
        $event->setSummary($title);
        $event->setStatus('confirmed');

        $existing = $event->getExtendedProperties();
        $private = $existing?->getPrivate() ?? [];
        $event->setExtendedProperties([
            'private' => array_merge($private, ['foyer' => 'true'], $metadata),
        ]);

        $this->service->events->update($calendarId, $eventId, $event);
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        $this->service->events->delete($calendarId, $eventId);
    }

    public function listBusy(
        string $calendarId,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $events = $this->service->events->listEvents($calendarId, [
            'timeMin' => $from->toRfc3339String(),
            'timeMax' => $to->toRfc3339String(),
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ]);

        $items = [];
        foreach ($events->getItems() as $event) {
            $start = $event->getStart()?->getDateTime() ?? $event->getStart()?->getDate();
            $end = $event->getEnd()?->getDateTime() ?? $event->getEnd()?->getDate();
            if (! $start || ! $end) {
                continue;
            }
            $items[] = [
                'start' => CarbonImmutable::parse($start),
                'end' => CarbonImmutable::parse($end),
                'event_id' => $event->getId(),
            ];
        }

        return $items;
    }

    public function watchChannel(string $calendarId, string $callbackUrl): array
    {
        $channel = new Channel([
            'id' => 'foyer-'.bin2hex(random_bytes(8)),
            'type' => 'web_hook',
            'address' => $callbackUrl,
        ]);

        $resp = $this->service->events->watch($calendarId, $channel);

        return [
            'channel_id' => $resp->getId(),
            'resource_id' => $resp->getResourceId(),
            'expires_at' => $resp->getExpiration(),
        ];
    }

    private function date(CarbonInterface $when): EventDateTime
    {
        $dt = new EventDateTime;
        $dt->setDateTime($when->toRfc3339String());
        $dt->setTimeZone($when->getTimezone()->getName());

        return $dt;
    }
}
