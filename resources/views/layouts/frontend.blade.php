<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- <link href="{{ mix('frontend/css/app.css') }}" rel="stylesheet"> --}}

    @stack('styles')

    <title>@yield('title')</title>
</head>

<body class="body body--{{ Str::slug($page->pageTemplate->name) }}">
    @if ($isRevision)
        <div class="alert alert-info sticky-top mb-0">
            This is a preview of the page. <a href="/{{ $page->page->route }}">Go to live version.</a>
        </div>
    @endif

    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="/">YAGO Content</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            @if (isset($pages))
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <x-yago-cms::core.page-nav :pages="$pages" :depth="0" />
                </div>
            @endif
        </div>
    </nav>

    @yield('content')

    {{-- <script src="{{ mix('frontend/js/manifest.js') }}"></script>
    <script src="{{ mix('frontend/js/vendor.js') }}"></script>
    <script src="{{ mix('frontend/js/app.js') }}"></script> --}}

    @stack('scripts')
</body>

</html>
