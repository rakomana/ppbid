<?php

use App\Livewire\Admin\Users\Form;
use App\Livewire\Admin\Users\Index;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| No public registration
|--------------------------------------------------------------------------
*/

it('has no public registration page', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password'])->assertNotFound();
    expect(User::count())->toBe(0);

    $this->get('/login')->assertOk()->assertDontSee('Sign up')->assertSee('Ask an administrator');
});

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

it('returns 403 to non-admins on user admin pages and actions', function () {
    $other = bidder();
    $this->actingAs(bidder());

    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/users/create')->assertForbidden();
    $this->get("/admin/users/{$other->id}/edit")->assertForbidden();

    Livewire::test(Form::class)->assertForbidden();
    Livewire::test(Index::class)->assertForbidden();
});

it('shows a Users link in the admin navigation only', function () {
    $this->actingAs(admin())->get('/dashboard')->assertSee(route('admin.users.index'));
    $this->actingAs(bidder())->get('/dashboard')->assertDontSee(route('admin.users.index'));
});

/*
|--------------------------------------------------------------------------
| Listing users
|--------------------------------------------------------------------------
*/

it('lists users with bidder number, role and bid count, and can search', function () {
    $admin = admin();
    bidder(['name' => 'Thandi Mokoena', 'email' => 'thandi@example.com']);
    $sipho = bidder(['name' => 'Sipho Dlamini', 'email' => 'sipho@example.com']);

    $this->actingAs($admin);

    Livewire::test(Index::class)
        ->assertSee('Thandi Mokoena')
        ->assertSee('sipho@example.com')
        ->assertSee($sipho->bidder_number)
        ->assertSee('Admin')
        ->set('search', 'thandi')
        ->assertSee('Thandi Mokoena')
        ->assertDontSee('Sipho Dlamini')
        ->set('search', $sipho->bidder_number)
        ->assertSee('Sipho Dlamini')
        ->assertDontSee('Thandi Mokoena');
});

/*
|--------------------------------------------------------------------------
| Adding users
|--------------------------------------------------------------------------
*/

it('lets an admin add a bidder who can then log in', function () {
    $this->actingAs(admin());

    Livewire::test(Form::class)
        ->set('name', 'Thandi Mokoena')
        ->set('email', '  Thandi@Example.com ')
        ->set('password', 'secret-pass-123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'thandi@example.com')->sole();

    expect($user)
        ->name->toBe('Thandi Mokoena')
        ->is_admin->toBeFalse()
        ->email_verified_at->not->toBeNull()
        ->and(Hash::check('secret-pass-123', $user->password))->toBeTrue();

    auth()->logout();

    Volt::test('auth.login')
        ->set('email', 'thandi@example.com')
        ->set('password', 'secret-pass-123')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('lets an admin add another admin', function () {
    $this->actingAs(admin());

    Livewire::test(Form::class)
        ->set('name', 'Second Admin')
        ->set('email', 'second@example.com')
        ->set('password', 'secret-pass-123')
        ->set('is_admin', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('email', 'second@example.com')->sole()->is_admin)->toBeTrue();
});

it('validates new users', function () {
    $this->actingAs(admin());
    bidder(['email' => 'taken@example.com']);

    Livewire::test(Form::class)
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->set('password', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'email' => 'email', 'password' => 'required']);

    Livewire::test(Form::class)
        ->set('name', 'Someone')
        ->set('email', 'taken@example.com')
        ->set('password', 'short')
        ->call('save')
        ->assertHasErrors(['email' => 'unique', 'password']);

    expect(User::count())->toBe(2);
});

it('generates a password', function () {
    $this->actingAs(admin());

    $component = Livewire::test(Form::class)->call('generatePassword');

    expect($component->get('password'))->toHaveLength(12);
});

/*
|--------------------------------------------------------------------------
| Editing users
|--------------------------------------------------------------------------
*/

it('lets an admin edit a user and keeps the password when left blank', function () {
    $this->actingAs(admin());
    $user = bidder(['name' => 'Old Name', 'email' => 'old@example.com', 'password' => 'original-pass']);

    Livewire::test(Form::class, ['user' => $user])
        ->assertSet('name', 'Old Name')
        ->assertSet('password', '')
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('New Name')
        ->and($user->email)->toBe('new@example.com')
        ->and(Hash::check('original-pass', $user->password))->toBeTrue();
});

it('lets an admin reset a password', function () {
    $this->actingAs(admin());
    $user = bidder(['password' => 'original-pass']);

    Livewire::test(Form::class, ['user' => $user])
        ->set('password', 'brand-new-pass')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

it('lets a user keep their own email when editing', function () {
    $this->actingAs(admin());
    $user = bidder(['email' => 'same@example.com']);

    Livewire::test(Form::class, ['user' => $user])
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();
});

it('does not let an admin remove their own admin access', function () {
    $admin = admin();
    $this->actingAs($admin);

    Livewire::test(Form::class, ['user' => $admin])
        ->set('is_admin', false)
        ->call('save')
        ->assertHasErrors('is_admin');

    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('lets an admin demote another admin', function () {
    $this->actingAs(admin());
    $other = admin();

    Livewire::test(Form::class, ['user' => $other])
        ->set('is_admin', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($other->fresh()->is_admin)->toBeFalse();
});
