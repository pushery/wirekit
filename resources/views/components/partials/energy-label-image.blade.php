{{-- optimistic-ui: n/a — sub-component
     The image inside energy-label, which draws a class and changes nothing. --}}
{{-- The image of energy-label: the range of the scale in black on white on the blunt side, then
     the arrow in the color of the class, with the class in white and a black edge, centered in the
     rectangular part. Hidden from assistive technology, which the link's words serve. Laid out
     left to right whatever the page's direction, since `right` and `left` name where it points.
     Expects $energyClasses (the scale, best first), $energyClass, $energyStep (1 to 7),
     $energyDirection and $energySize. --}}
<span class="wk-energy-image {{ $energySize }}" data-wk-direction="{{ $energyDirection }}" data-wk-energy-step="{{ $energyStep }}" dir="ltr" aria-hidden="true"><span class="wk-energy-range"><span>{{ $energyClasses[0] }}</span><svg viewBox="0 0 6 20" preserveAspectRatio="none" focusable="false"><path d="M3 1.5V18.5M1 3.5L3 1.5L5 3.5M1 16.5L3 18.5L5 16.5" fill="none" stroke="currentColor" stroke-width="1" vector-effect="non-scaling-stroke"/></svg><span>{{ $energyClasses[6] }}</span></span><span class="wk-energy-arrow"><span class="wk-energy-body"><span class="wk-energy-letter">{{ $energyClass }}</span></span><svg class="wk-energy-tip" viewBox="0 0 10 20" preserveAspectRatio="none" focusable="false"><polygon points="0,0 10,10 0,20"/><polyline points="0,0 10,10 0,20"/></svg></span></span>
