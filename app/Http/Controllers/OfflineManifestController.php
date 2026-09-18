<?php

namespace App\Http\Controllers;

use App\Models\ConferenceSession;
use Illuminate\Http\JsonResponse;

class OfflineManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $conferenceSessions = ConferenceSession::query()
            ->active()
            ->ordered()
            ->get(['id', 'updated_at']);

        $urls = [
            route('agenda'),
            route('sessions.index'),
            route('live'),
            ...$conferenceSessions
                ->map(fn (ConferenceSession $conferenceSession): string => route('sessions.show', $conferenceSession))
                ->all(),
        ];

        $version = hash('sha256', $conferenceSessions
            ->map(fn (ConferenceSession $conferenceSession): string => $conferenceSession->id.':'.$conferenceSession->updated_at?->toIso8601String())
            ->join('|'));

        return response()
            ->json([
                'version' => $version,
                'urls' => $urls,
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
