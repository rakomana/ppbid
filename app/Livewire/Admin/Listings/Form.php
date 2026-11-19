<?php

namespace App\Livewire\Admin\Listings;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Listing $listing = null;

    public string $title = '';

    public string $description = '';

    public string $min_price = '';

    public string $quantity = '1';

    public string $ends_at = '';

    /**
     * Runs on every request, including "Save",
     * not only on the initial page load that the route middleware covers.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }

    public function mount(?Listing $listing = null): void
    {
        if (! $listing?->exists) {
            $this->ends_at = now()
                ->addHours(config('auction.default_duration_hours'))
                ->format('Y-m-d\TH:i');

            return;
        }

        abort_if($listing->status === Listing::STATUS_CLOSED, 403, 'Closed listings cannot be edited.');

        $this->listing = $listing;
        $this->title = $listing->title;
        $this->description = (string) $listing->description;
        $this->min_price = (string) $listing->min_price;
        $this->quantity = (string) $listing->quantity;
        $this->ends_at = $listing->ends_at->format('Y-m-d\TH:i');
    }

    /**
     * Once a listing has bids, its price and quantity are locked.
     */
    #[Computed]
    public function hasBids(): bool
    {
        return $this->listing?->bids()->exists() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'min_price' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:99999999.99'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'ends_at' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'ends_at.after' => 'The expiry must be in the future.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'min_price' => 'minimum price',
            'ends_at' => 'expiry',
        ];
    }

    public function save()
    {
        $data = $this->validate();
        $data['description'] = $data['description'] ?: null;

        if (! $this->listing) {
            $listing = Listing::create($data);

            session()->flash('status', "“{$listing->title}” has been created.");

            return $this->redirectRoute('admin.listings.index', navigate: true);
        }

        DB::transaction(function () use ($data) {
            // Lock the row so a bid can't land between the "has bids" check and the save.
            $listing = Listing::query()->lockForUpdate()->findOrFail($this->listing->id);

            if ($listing->status === Listing::STATUS_CLOSED) {
                throw ValidationException::withMessages([
                    'title' => 'This listing has been closed and can no longer be edited.',
                ]);
            }

            if ($listing->bids()->exists()) {
                $priceChanged = round((float) $data['min_price'], 2) !== round((float) $listing->min_price, 2);
                $quantityChanged = (int) $data['quantity'] !== $listing->quantity;

                if ($priceChanged || $quantityChanged) {
                    $this->min_price = (string) $listing->min_price;
                    $this->quantity = (string) $listing->quantity;
                    unset($this->hasBids);

                    throw ValidationException::withMessages([
                        'min_price' => 'The minimum price and quantity cannot be changed once a listing has bids.',
                    ]);
                }

                unset($data['min_price'], $data['quantity']);
            }

            $listing->fill($data);

            // A new end time means a new "ending soon" reminder.
            if ($listing->isDirty('ends_at')) {
                $listing->ending_soon_notified_at = null;
            }

            $listing->save();
        });

        session()->flash('status', "“{$this->title}” has been updated.");

        return $this->redirectRoute('admin.listings.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.listings.form')
            ->title($this->listing ? 'Admin: Edit listing' : 'Admin: Create listing');
    }
}
