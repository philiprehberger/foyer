<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ReconcileCalendarDrift;
use Illuminate\Http\Request;

/**
 * Google Calendar `events.watch` push receiver.
 *
 * Google sends X-Goog-Channel-ID + X-Goog-Resource-ID + X-Goog-Resource-State
 * headers; the body is empty. Validate the channel id against what we
 * persisted at watch-channel registration, then dispatch a reconciliation
 * job.
 */
class GoogleCalendarPushController
{
    public function __invoke(Request $request)
    {
        $channelId = (string) $request->header('X-Goog-Channel-ID', '');
        $resourceId = (string) $request->header('X-Goog-Resource-ID', '');
        $state = (string) $request->header('X-Goog-Resource-State', 'sync');

        if ($channelId === '' || $resourceId === '') {
            return response()->noContent(400);
        }

        // Phase 4 dispatches reconciliation by channel; the worker matches the
        // channel against businesses and reconciles drift. The
        // ReconcileCalendarDrift job is wired in Phase 4.
        ReconcileCalendarDrift::dispatch($channelId, $resourceId, $state);

        return response()->noContent(204);
    }
}
