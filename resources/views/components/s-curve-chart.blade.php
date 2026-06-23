@props([
    'payload' => [],
    'title' => 'S Curve',
    'subtitle' => 'Planned vs Actual cumulative progress.',
    'mode' => 'main',
    'height' => '260px',
    'compact' => false,
])

@php
    $chartId = 's-curve-' . \Illuminate\Support\Str::uuid();
@endphp

<section
    {{ $attributes->class(['s-curve-card', 's-curve-card-compact' => $compact]) }}
    data-scurve-chart
    data-scurve-mode="{{ $mode }}"
>
    <div class="s-curve-head">
        <div>
            <h3>{{ $title }}</h3>
            <p>{{ $subtitle }}</p>
        </div>
        <div class="s-curve-legend">
            <span><i class="actual"></i> Actual</span>
            <span><i class="planned"></i> Planned</span>
        </div>
    </div>

    <div class="s-curve-kpis">
        <div><span>Planned</span><strong data-scurve-plan>--</strong></div>
        <div><span>Actual</span><strong data-scurve-actual>--</strong></div>
        <div><span>Gap</span><strong data-scurve-gap>--</strong></div>
        <div><span>Source</span><strong data-scurve-source>--</strong></div>
    </div>

    <div class="s-curve-chart-shell" style="height: {{ $height }};">
        <canvas id="{{ $chartId }}"></canvas>
        <div class="s-curve-empty" data-scurve-empty hidden>
            <i class="fa-solid fa-chart-simple"></i>
            <strong>No stage data</strong>
            <span>Project stages with dates will appear here.</span>
        </div>
    </div>

    <script type="application/json" class="s-curve-payload">@json($payload)</script>
</section>
