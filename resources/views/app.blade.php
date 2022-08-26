<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if (Auth::check()) 
                <meta name="user_id" content="{{ Auth::user()->id }}" />
        @endif 

        {{-- <script src="./simplepeer.min.js"></script> --}}

        {{-- <script src="{{ asset(mix('js/scripts/pages/chat.js')) }}"></script> --}}


        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap">

        <!-- Scripts -->
        @routes
        @vite('resources/js/app.js')
        @inertiaHead
    </head>
    
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
