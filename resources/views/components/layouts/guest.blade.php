<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0a0a0a">
    <x-theme-script />
    <title>{{ $title ?? 'Login - Multibrand POS' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo1.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen">
    <x-theme-toggle class="fixed top-4 right-4 z-20 bg-white/80 backdrop-blur rounded-2xl" />
    {{ $slot }}
</body>

</html>
