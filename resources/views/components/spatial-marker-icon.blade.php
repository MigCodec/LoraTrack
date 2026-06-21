@props(['type' => 'asset'])

<svg {{ $attributes->class(['spatial-marker-icon', 'is-'.$type])->merge(['width' => 28, 'height' => 34, 'viewBox' => '0 0 28 34', 'fill' => 'none', 'aria-hidden' => 'true']) }}>
    <path class="spatial-marker-pin" d="M14 1.5C7.1 1.5 2 6.6 2 13.1c0 8.4 12 18.9 12 18.9s12-10.5 12-18.9C26 6.6 20.9 1.5 14 1.5Z"/>
    @if($type === 'asset')
        <path class="spatial-marker-symbol" d="M9 11.5h10v7H9zM11.5 11.5v-2h5v2M9 14.5h10M12 14.5v1.5h4v-1.5"/>
    @elseif($type === 'scanner')
        <circle class="spatial-marker-symbol" cx="14" cy="14" r="2"/><path class="spatial-marker-symbol" d="M10.5 10.5a5 5 0 0 0 0 7M17.5 10.5a5 5 0 0 1 0 7"/>
    @else
        <path class="spatial-marker-symbol" d="M14 9v10M10.5 12.5 14 9l3.5 3.5M10 19h8"/>
    @endif
</svg>
