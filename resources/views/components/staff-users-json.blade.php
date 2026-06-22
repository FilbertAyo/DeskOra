@props([
    'users' => collect(),
    'id' => 'teamUsersData',
])

<script type="application/json" id="{{ $id }}" @if($attributes->has('class')) class="{{ $attributes->get('class') }}" @endif>
    @json(
        collect($users)->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
        ])->values()
    )
</script>

@once
    @push('scripts')
        <script src="{{ asset('assets/js/staff-checkbox-select.js') }}"></script>
    @endpush
@endonce
