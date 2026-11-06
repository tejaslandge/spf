<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

@php($brandIcon = \App\Support\Branding::logoUrl())
@if ($brandIcon)
    <link rel="icon" href="{{ $brandIcon }}">
    <link rel="apple-touch-icon" href="{{ $brandIcon }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
<script>
    // Light-first design system: keep Flux components in light mode.
    // The sidebar opts into dark styling locally with its own `dark` class.
    window.Flux.applyAppearance('light');
</script>
