<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Find your fit. Choose your size and favorite shirt designs.">
    <title>FIT — Make it yours</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="root"></div>
    <script>window.shirtSettings = {{ Illuminate\Support\Js::from($settings) }};</script>
</body>
</html>
