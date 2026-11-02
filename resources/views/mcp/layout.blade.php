<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        :root { color-scheme: light dark; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: Canvas; color: CanvasText; }
        main { width: min(26rem, calc(100% - 2rem)); padding: 2rem 0; }
        h1 { font-size: 1.375rem; margin: 0 0 .25rem; }
        p { line-height: 1.5; margin: .5rem 0; }
        .muted { opacity: .75; font-size: .9375rem; }
        label { display: block; font-weight: 600; margin: 1rem 0 .375rem; }
        input[type=text], input[type=password] { box-sizing: border-box; width: 100%; padding: .625rem .75rem; font: inherit; border: 1px solid GrayText; border-radius: .375rem; background: Field; color: FieldText; }
        .actions { display: flex; gap: .75rem; margin-top: 1.5rem; }
        form.inline { flex: 1; }
        button { width: 100%; padding: .625rem 1rem; font: inherit; font-weight: 600; border-radius: .375rem; border: 1px solid GrayText; background: ButtonFace; color: ButtonText; cursor: pointer; }
        button.primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
        button:focus-visible, input:focus-visible { outline: 3px solid #60a5fa; outline-offset: 2px; }
        .error { border-left: 4px solid #dc2626; padding: .5rem .75rem; margin: 1rem 0; }
        .client { border: 1px solid GrayText; border-radius: .375rem; padding: .75rem; margin: 1rem 0; }
        .client dt { font-weight: 600; font-size: .875rem; }
        .client dd { margin: 0 0 .5rem; overflow-wrap: anywhere; }
        .redirect { font-family: ui-monospace, monospace; }
    </style>
</head>
<body>
<main>
    @yield('content')
</main>
</body>
</html>
