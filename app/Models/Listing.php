<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    /**
     * Fields the admin form may set. Status and winner are managed by the app.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'min_price',
        'quantity',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'ending_soon_notified_at' => 'datetime',
            'min_price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    /**
     * Bids that count: withdrawn (voided) bids are left out, so every top bid,
     * leader, minimum next bid and winner is worked out from these.
     *
     * @return HasMany<Bid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class)->whereNull('bids.voided_at');
    }

    /**
     * Every bid, including withdrawn ones, for the bid history.
     *
     * @return HasMany<Bid, $this>
     */
    public function allBids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Active (not deactivated) users with a bid that counts on this listing.
     *
     * @return Collection<int, User>
     */
    public function activeBidders()
    {
        return User::query()
            ->whereIn('id', $this->bids()->select('user_id'))
            ->whereNull('deactivated_at')
            ->get();
    }

    /**
     * @return HasMany<ReturnRequest, $this>
     */
    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    /**
     * Units of the lot the winner can still log a return for:
     * the lot quantity less units already in an open return.
     */
    public function returnableQuantity(): int
    {
        return max(0, $this->quantity - (int) $this->returnRequests()->open()
            ->sum(DB::raw('coalesce(quantity_approved, quantity_requested)')));
    }

    /**
     * The last moment the winner may log a return.
     */
    public function returnDeadline(): CarbonInterface
    {
        return $this->ends_at->copy()->addDays(config('returns.window_days'));
    }

    /**
     * Closed, won, and still inside the return window.
     */
    public function acceptsReturns(): bool
    {
        return $this->status === self::STATUS_CLOSED
            && $this->winner_id !== null
            && $this->returnDeadline()->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /**
     * Listings still taking bids: active and not yet past their end time.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE)->where('ends_at', '>', now());
    }

    /**
     * Adds leader_id: the user id of the highest bid (earliest wins a tie), same order as highestBid().
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeWithLeaderId(Builder $query): void
    {
        $query->addSelect(['leader_id' => Bid::query()
            ->select('user_id')
            ->whereColumn('bids.listing_id', 'listings.id')
            ->whereNull('bids.voided_at')
            ->orderByDesc('amount')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(1),
        ]);
    }

    /**
     * The highest bid; on a tie, the earliest bid wins.
     */
    public function highestBid(): ?Bid
    {
        return $this->bids()
            ->orderByDesc('amount')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->ends_at->isFuture();
    }

    /**
     * The lowest amount the next bid may be.
     */
    public function minimumNextBid(): float
    {
        $highest = $this->highestBid();

        if (! $highest) {
            return (float) $this->min_price;
        }

        return round((float) $highest->amount + (float) config('auction.min_increment'), 2);
    }

    /**
     * Human-readable time left, e.g. "2 hours 5 minutes left", or "Ended".
     */
    public function timeRemaining(): string
    {
        if (! $this->ends_at->isFuture()) {
            return 'Ended';
        }

        return $this->ends_at->diffForHumans(now(), [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 2,
        ]).' left';
    }
}
