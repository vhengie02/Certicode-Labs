@props(['pattern', 'id'])

<svg class="class-pattern class-pattern--{{ $pattern }}" aria-hidden="true" focusable="false">
    <defs>
        @include("components.class-patterns.{$pattern}", ['id' => $id])
    </defs>
    <rect width="100%" height="100%" fill="url(#{{ $id }})"/>
</svg>
