<?php

namespace App\Http\Controllers;

use App\Invoices\Invoice;
use App\Models\Listing;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    /**
     * Download the PDF invoice for a won auction. Only the winner and admins may.
     */
    public function __invoke(Listing $listing): Response
    {
        abort_unless(Invoice::existsFor($listing), 404);

        $user = auth()->user();
        abort_unless($user->is_admin || $user->id === $listing->winner_id, 403);

        $invoice = Invoice::for($listing->load('winner'));

        return Pdf::loadView('invoices.pdf', ['invoice' => $invoice])
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true) // embed only the characters used: ~30 KB instead of ~900 KB
            ->download($invoice->filename());
    }
}
