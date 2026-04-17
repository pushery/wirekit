{{-- WireKit Liquid Glass Extension — include in layout <head>.
     Loads glass CSS, JS, and injects SVG filter definitions for Tier 2. --}}
<link rel="stylesheet" href="{{ asset('vendor/wirekit/glass/wirekit-glass.css') }}">
<script src="{{ asset('vendor/wirekit/glass/wirekit-glass.js') }}" defer></script>

<svg xmlns="http://www.w3.org/2000/svg"
     style="position:absolute;width:0;height:0;overflow:hidden"
     aria-hidden="true">
    <defs>
        <filter id="wk-glass-refract"
                x="-10%" y="-10%" width="120%" height="120%"
                color-interpolation-filters="sRGB">
            <feTurbulence type="fractalNoise"
                         baseFrequency="0.015 0.015"
                         numOctaves="1"
                         seed="2"
                         result="noise"/>
            <feDisplacementMap in="SourceGraphic"
                              in2="noise"
                              scale="2"
                              xChannelSelector="R"
                              yChannelSelector="G"/>
        </filter>
    </defs>
</svg>
