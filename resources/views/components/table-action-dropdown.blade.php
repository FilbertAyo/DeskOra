@props(['label' => 'Actions'])

<div class="btn-group-dropdown">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        {{ $label }}
    </button>
    <div class="dropdown-menu">
        {{ $slot }}
    </div>
</div>
