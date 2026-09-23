<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConferenceSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class SessionKitExportController extends Controller
{
    public function __invoke(): Response
    {
        $sessions = ConferenceSession::query()
            ->active()
            ->ordered()
            ->with(['room', 'speakers', 'mcs', 'notes.author'])
            ->get();

        return Pdf::loadView('exports.session-kit', ['sessions' => $sessions])
            ->setPaper('a4')
            ->download('mc-kit.pdf');
    }
}
