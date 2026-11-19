<?php

use App\Livewire\Admin;
use App\Livewire\Dashboard;
use App\Livewire\Listings;
use App\Livewire\MyBids;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/listings')->middleware('auth')->name('home');

Route::get('dashboard', Dashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    Route::get('listings', Listings\Index::class)->name('listings.index');
    Route::get('listings/{listing}', Listings\Show::class)->name('listings.show');

    Route::get('my-bids', MyBids::class)->name('my-bids');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/listings')->name('home');

    Route::get('listings', Admin\Listings\Index::class)->name('listings.index');
    Route::get('listings/create', Admin\Listings\Form::class)->name('listings.create');
    Route::get('listings/{listing}/edit', Admin\Listings\Form::class)->name('listings.edit');

    Route::get('users', Admin\Users\Index::class)->name('users.index');
    Route::get('users/create', Admin\Users\Form::class)->name('users.create');
    Route::get('users/{user}/edit', Admin\Users\Form::class)->name('users.edit');
});

require __DIR__.'/auth.php';
