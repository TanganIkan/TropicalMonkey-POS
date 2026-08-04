<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Login - Multibrand POS' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased text-gray-900 flex items-center justify-center min-h-screen"
    style="background-color: #F8F9FF;">

    <div class="w-full max-w-md">
        {{ $slot }}
    </div>

</body>

</html>