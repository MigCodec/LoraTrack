@props(['type' => 'asset'])

<svg {{ $attributes->class(['spatial-marker-icon', 'is-'.$type])->merge(['width' => 28, 'height' => 34, 'viewBox' => '0 0 28 34', 'fill' => 'none', 'aria-hidden' => 'true']) }}>
    <path class="spatial-marker-pin" d="M14 1.5C7.1 1.5 2 6.6 2 13.1c0 8.4 12 18.9 12 18.9s12-10.5 12-18.9C26 6.6 20.9 1.5 14 1.5Z"/>
    @if($type === 'asset')
        <path class="spatial-marker-symbol" d="M14 8.5 19 11.3v6.1l-5 2.8-5-2.8v-6.1L14 8.5ZM9 11.3l5 2.8 5-2.8M14 14.1v6.1"/>
    @elseif($type === 'scanner')
        <circle class="spatial-marker-symbol" cx="14" cy="14" r="2"/><path class="spatial-marker-symbol" d="M10.5 10.5a5 5 0 0 0 0 7M17.5 10.5a5 5 0 0 1 0 7"/>
    @else
        <path class="spatial-marker-symbol" d="M14 9v10M10.5 12.5 14 9l3.5 3.5M10 19h8"/>
    @endif
</svg>
