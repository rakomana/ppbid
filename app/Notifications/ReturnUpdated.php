<?php

namespace App\Notifications;

use App\Models\ReturnRequest;

/**
 * Sent to the buyer each time an admin moves their return on.
 */
class ReturnUpdated extends ReturnNotification
{
    protected function kind(): string
    {
        return 'return_'.$this->return->status;
    }

    protected function title(): string
    {
        return match ($this->return->status) {
            ReturnRequest::STATUS_APPROVED => 'Return approved',
            ReturnRequest::STATUS_REJECTED => 'Return rejected',
            ReturnRequest::STATUS_RECEIVED => 'Faulty units received',
            ReturnRequest::STATUS_REPLACED => 'Units replaced',
            default => 'Return updated',
        };
    }

    protected function message(): string
    {
        $return = $this->return;
        $title = "“{$this->listing->title}”";
        $units = fn (int $n) => $n.' '.str('unit')->plural($n);
        $note = filled($return->admin_note) ? " Note: {$return->admin_note}" : '';

        return match ($return->status) {
            ReturnRequest::STATUS_APPROVED => ($return->quantity_approved < $return->quantity_requested
                ? "{$return->quantity_approved} of the {$units($return->quantity_requested)} you logged on {$title} were approved for replacement."
                : "Your return of {$units($return->quantity_approved)} on {$title} was approved.")
                .' Bring or send the faulty units in to be replaced.'.$note,
            ReturnRequest::STATUS_REJECTED => "Your return on {$title} was rejected.{$note}",
            ReturnRequest::STATUS_RECEIVED => "We've received the {$units($return->units())} for {$title}. Replacements are on the way.{$note}",
            ReturnRequest::STATUS_REPLACED => "{$units($return->units())} on {$title} replaced. Your replacement note is on the listing page.{$note}",
            default => "Your return on {$title} is now {$return->statusLabel()}.",
        };
    }
}
