{{--
    In-page section menu: foldable groups of anchor links, with a line drawn from the trunk to the
    section in view. Blade + Alpine port of React Bits' BranchedMenu.

    items: [['label' => 'Group', 'children' => [['value' => 'element-id', 'label' => 'Link', 'icon' => 'svg path d']]]]
--}}
@props(['items', 'label' => 'Page sections'])
@php
    // Keep in step with --bm-row and --bm-indent in resources/css/branched-menu.css.
    $row = 36;
    $indent = 40;
    $pad = 6;
    $trunk = 14;
    $radius = 10;
    $endX = $indent - 8;
    $rowY = fn (int $k) => $pad + $k * $row + $row / 2;
    $curve = fn (int $k) => "A {$radius} {$radius} 0 0 0 " . ($trunk + $radius) . ' ' . $rowY($k) . " H {$endX}";
    $length = fn (int $k) => round($rowY($k) - $radius + M_PI * $radius / 2 + $endX - $trunk - $radius, 2);
@endphp
<nav {{ $attributes->merge(['class' => 'branched-menu']) }} aria-label="{{ $label }}"
     x-data="branchedMenu(@js(['active' => $items[0]['children'][0]['value'], 'open' => array_keys($items)]))">
    <span x-ref="marker" class="branched-menu__marker" aria-hidden="true"></span>
    @foreach ($items as $i => $item)
        @php
            $kids = $item['children'];
            $height = $pad * 2 + count($kids) * $row;
        @endphp
        <div class="branched-menu__section" data-section="{{ $i }}" data-open :data-open="isOpen({{ $i }}) ? '' : null">
            <button type="button" class="branched-menu__head" aria-expanded="true" :aria-expanded="isOpen({{ $i }})" @click="toggle({{ $i }})">{{ $item['label'] }}</button>
            <div class="branched-menu__body">
                <div class="branched-menu__fold">
                    <div class="branched-menu__tree" style="height: {{ $height }}px">
                        <svg class="branched-menu__lines" width="{{ $indent }}" height="{{ $height }}" aria-hidden="true">
                            <path class="branched-menu__base" d="M {{ $trunk }} 0 V {{ $rowY(count($kids) - 1) - $radius }}" />
                            @foreach ($kids as $k => $kid)
                                <path class="branched-menu__base" d="M {{ $trunk }} {{ $rowY($k) - $radius }} {{ $curve($k) }}" />
                            @endforeach
                            @foreach ($kids as $k => $kid)
                                {{-- The gap outruns the path by 2px so an idle line's round caps stay off the trunk. --}}
                                <path class="branched-menu__reach" d="M {{ $trunk }} 0 V {{ $rowY($k) - $radius }} {{ $curve($k) }}"
                                      style="stroke-dasharray: {{ $length($k) }} {{ $length($k) + 2 }}; stroke-dashoffset: {{ $length($k) + 1 }}"
                                      :style="{ strokeDashoffset: active === @js($kid['value']) ? 0 : {{ $length($k) + 1 }} }" />
                            @endforeach
                        </svg>
                        @foreach ($kids as $kid)
                            <a href="#{{ $kid['value'] }}" class="branched-menu__item"
                               :aria-current="active === @js($kid['value']) ? 'true' : null"
                               :data-active="active === @js($kid['value']) ? '' : null"
                               :tabindex="isOpen({{ $i }}) ? 0 : -1"
                               @click="pick(@js($kid['value']))">
                                @isset($kid['icon'])
                                    <svg class="branched-menu__icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $kid['icon'] }}"/></svg>
                                @endisset
                                <span class="branched-menu__label">{{ $kid['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</nav>
