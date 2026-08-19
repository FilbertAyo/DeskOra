@php
    $tabs = $tabs ?? [];
    $current = $current ?? '';
@endphp
<ul class="nav nav-tabs border-0 mb-0">
    @foreach($tabs as $item)
        <li class="nav-item">
            <a class="nav-link {{ $current === $item['key'] ? 'active' : '' }}" href="{{ $item['url'] }}">
                {{ $item['label'] }}
                @if(array_key_exists('count', $item))
                    <span class="badge {{ ! empty($item['alert']) && ($item['count'] ?? 0) > 0 ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $item['count'] }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
