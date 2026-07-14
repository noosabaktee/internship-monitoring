@php
    $logoPath = public_path('logo.png');
    $logoSrc = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
@endphp

<table class="brand-row">
    <tr>
        @if ($logoSrc)
            <td class="brand-logo"><img src="{{ $logoSrc }}" alt="Kalbe"></td>
        @endif
        <td class="brand">Kalbe Internship Monitoring</td>
    </tr>
</table>
