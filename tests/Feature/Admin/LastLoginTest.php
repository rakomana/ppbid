<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Last login time (users.last_login_at)
|--------------------------------------------------------------------------
*/

it('starts empty and is set when the user logs in', function () {
    $user = bidder();
    expect($user->last_login_at)->toBeNull();

    $this->freezeSecond();
    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    expect($user->fresh()->last_login_at->equalTo(now()))->toBeTrue();
});

it('updates on every login without touching updated_at', function () {
    $user = bidder();
    $updatedAt = $user->updated_at;

    $this->travel(2)->days();
    Auth::login($user);

    $fresh = $user->fresh();
    expect($fresh->last_login_at->isToday())->toBeTrue()
        ->and($fresh->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('does not record a failed login', function () {
    $user = bidder();

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('does not record a login for a deactivated account', function () {
    $user = bidder(['deactivated_at' => now()]);

    Volt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('does not count "Log in as" or returning to admin as a login', function () {
    $admin = admin();
    $alice = bidder();

    $this->actingAs($admin)->post(route('admin.users.impersonate', $alice));
    $this->post(route('impersonate.stop'));

    expect($alice->fresh()->last_login_at)->toBeNull()
        ->and($admin->fresh()->last_login_at)->toBeNull();
});

it('shows last login on the admin users list and edit page', function () {
    $admin = admin();
    $seen = bidder(['name' => 'Seen Recently']);
    $seen->forceFill(['last_login_at' => now()->subHours(3)])->save();
    $never = bidder(['name' => 'Never Seen']);

    $this->actingAs($admin)->get('/admin/users')
        ->assertSee('Last login')
        ->assertSee('3 hours ago')
        ->assertSee($seen->last_login_at->format('j M Y H:i'))
        ->assertSee('Never');

    $this->actingAs($admin)->get(route('admin.users.edit', $seen))
        ->assertSee('last login '.$seen->last_login_at->format('j M Y H:i'));
    $this->actingAs($admin)->get(route('admin.users.edit', $never))
        ->assertSee('last login never');
});
