<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ReplacementNoteController extends Controller
{
    /**
     * Download the PDF replacement note for a completed return. Only the buyer and admins may.
     */
    public function __invoke(ReturnRequest $returnRequest): Response
    {
        abort_unless($returnRequest->status === ReturnRequest::STATUS_REPLACED, 404);

        $user = auth()->user();
        abort_unless($user->is_admin || $user->id === $returnRequest->user_id, 403);

        $returnRequest->load('listing', 'user');

        return Pdf::loadView('returns.note-pdf', ['return' => $returnRequest])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true)
            ->download("ppbid-{$returnRequest->noteNumber()}.pdf");
    }
}
