@props(['label' => 'Actions'])

<div class="text-center text-sm-start">
    <div class="btn-group btn-group-sm">
        <button type="button"
                class="btn btn-light-secondary dropdown-toggle"
                data-bs-toggle="dropdown"
                data-bs-display="static"
                aria-expanded="false">
            {{ $label }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-lg-start">
            {{ $slot }}
        </ul>
    </div>
</div>
