@props(['user', 'size' => 'sm'])

{{-- "Log in as" for admins. Not shown for admins, deactivated users or yourself. --}}
@if (! $user->is_admin && ! $user->isDeactivated() && ! $user->is(auth()->user()))
    <form method="POST" action="{{ route('admin.users.impersonate', $user) }}" class="inline-flex" data-impersonate-form>
        @csrf
        <flux:button type="submit" :size="$size" icon="eye" title="See the app exactly as {{ $user->name }} sees it">
            Log in as
        </flux:button>
    </form>
@endif
