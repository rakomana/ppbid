<?php

namespace App\Models;

use Database\Factories\ReturnRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A winner's request to replace faulty units from a lot they won.
 * Faulty units are always replaced, never refunded.
 *
 * Status flow: requested → approved → received → replaced,
 * or requested → rejected, or requested → cancelled (by the buyer).
 * An admin may go straight from approved to replaced (swap over the counter).
 */
class ReturnRequest extends Model
{
    /** @use HasFactory<ReturnRequestFactory> */
    use HasFactory;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_REPLACED = 'replaced';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses still in progress: their units count against the lot. */
    public const OPEN_STATUSES = [self::STATUS_REQUESTED, self::STATUS_APPROVED, self::STATUS_RECEIVED];

    public const STATUS_LABELS = [
        self::STATUS_REQUESTED => 'Requested',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_RECEIVED => 'Received',
        self::STATUS_REPLACED => 'Replaced',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_REQUESTED => 'amber',
        self::STATUS_APPROVED => 'sky',
        self::STATUS_RECEIVED => 'indigo',
        self::STATUS_REPLACED => 'green',
        self::STATUS_REJECTED => 'red',
        self::STATUS_CANCELLED => 'zinc',
    ];

    public const REASONS = [
        'not_detected' => 'Not detected / won\'t boot',
        'fails_test' => 'Fails memory test',
        'wrong_spec' => 'Wrong spec (size, speed, DDR type, ECC)',
        'damaged' => 'Physical damage',
        'other' => 'Other',
    ];

    /** @var list<string> */
    protected $fillable = [
        'quantity_requested',
        'reason',
        'details',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_approved' => 'integer',
            'replaced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /**
     * @param  Builder<ReturnRequest>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN_STATUSES);
    }

    public static function numberFor(int $id): string
    {
        return 'RET-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function number(): string
    {
        return self::numberFor($this->id);
    }

    /** The replacement note number, e.g. "RN-000045". */
    public function noteNumber(): string
    {
        return 'RN-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** Units this return covers: the approved quantity once approved, else the requested one. */
    public function units(): int
    {
        return $this->quantity_approved ?? $this->quantity_requested;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'zinc';
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }
}
