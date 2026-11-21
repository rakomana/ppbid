<?php

namespace App\Actions;

use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\ReturnUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a return through its statuses and tells the buyer (in-app) at each step.
 *
 *   requested → approved (with the number of units approved) or rejected (with a reason)
 *   approved  → received → replaced, or approved → replaced (swapped over the counter)
 *   requested → cancelled (by the buyer)
 */
class HandleReturn
{
    /** Which statuses each status may move to. */
    private const NEXT = [
        ReturnRequest::STATUS_REQUESTED => [ReturnRequest::STATUS_APPROVED, ReturnRequest::STATUS_REJECTED, ReturnRequest::STATUS_CANCELLED],
        ReturnRequest::STATUS_APPROVED => [ReturnRequest::STATUS_RECEIVED, ReturnRequest::STATUS_REPLACED],
        ReturnRequest::STATUS_RECEIVED => [ReturnRequest::STATUS_REPLACED],
    ];

    public function approve(ReturnRequest $return, User $admin, int $quantity, ?string $note = null): ReturnRequest
    {
        return $this->move($return, $admin, ReturnRequest::STATUS_APPROVED, $note, function (ReturnRequest $return) use ($quantity) {
            if ($quantity < 1 || $quantity > $return->quantity_requested) {
                throw ValidationException::withMessages(['quantity' => "Approve between 1 and {$return->quantity_requested} units."]);
            }
            $return->quantity_approved = $quantity;
        });
    }

    public function reject(ReturnRequest $return, User $admin, string $note): ReturnRequest
    {
        if (blank($note)) {
            throw ValidationException::withMessages(['note' => 'Say why the return is rejected. The buyer will see this.']);
        }

        return $this->move($return, $admin, ReturnRequest::STATUS_REJECTED, $note);
    }

    public function markReceived(ReturnRequest $return, User $admin, ?string $note = null): ReturnRequest
    {
        return $this->move($return, $admin, ReturnRequest::STATUS_RECEIVED, $note);
    }

    public function markReplaced(ReturnRequest $return, User $admin, ?string $note = null): ReturnRequest
    {
        return $this->move($return, $admin, ReturnRequest::STATUS_REPLACED, $note, function (ReturnRequest $return) {
            $return->replaced_at = now();
        });
    }

    /**
     * The buyer withdraws a return that hasn't been looked at yet.
     */
    public function cancel(ReturnRequest $return, User $buyer): ReturnRequest
    {
        if ($return->user_id !== $buyer->id) {
            throw ValidationException::withMessages(['return' => 'You can only cancel your own returns.']);
        }

        return $this->move($return, null, ReturnRequest::STATUS_CANCELLED, null, notify: false);
    }

    private function move(ReturnRequest $return, ?User $admin, string $to, ?string $note, ?callable $apply = null, bool $notify = true): ReturnRequest
    {
        $return = DB::transaction(function () use ($return, $admin, $to, $note, $apply) {
            $return = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);

            if (! in_array($to, self::NEXT[$return->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "This return is {$return->statusLabel()} and can't be marked ".strtolower(ReturnRequest::STATUS_LABELS[$to]).'.']);
            }

            $apply && $apply($return);

            $return->status = $to;
            if ($admin) {
                $return->handled_by = $admin->id;
            }
            if (filled($note)) {
                $return->admin_note = trim($note);
            }
            $return->save();

            return $return;
        });

        $return->load('listing', 'user');

        if ($notify && $return->user->deactivated_at === null) {
            $return->user->notify(new ReturnUpdated($return));
        }

        return $return;
    }
}
