<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? __($title).' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>
@include('site.partials.google-verification')

<link rel="icon" href="{{ \App\Support\Brand::icon('favicon.ico') }}" sizes="48x48">
<link rel="icon" href="{{ \App\Support\Brand::icon('favicon-32.png') }}" type="image/png" sizes="32x32">
<link rel="icon" href="{{ \App\Support\Brand::icon('favicon-16.png') }}" type="image/png" sizes="16x16">
<link rel="apple-touch-icon" href="{{ \App\Support\Brand::icon('apple-touch-icon.png') }}" sizes="180x180">

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
