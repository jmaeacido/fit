<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Find your fit. Choose your size and favorite shirt designs.">
    <meta name="theme-color" content="#253429" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#f8f9f5" media="(prefers-color-scheme: dark)">
    <title>FIT — Make it yours</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="root"></div>
    <script>window.shirtSettings = {{ Illuminate\Support\Js::from($settings) }};</script>
</body>
</html>
