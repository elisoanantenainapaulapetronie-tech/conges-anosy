{{-- Polices : fichiers locaux s'ils existent dans public/fonts, sinon Google Fonts (si Internet). --}}
@if (file_exists(public_path('fonts/PublicSans-Variable.ttf')) && file_exists(public_path('fonts/SourceSerif4-Variable.ttf')))
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
@else
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap">
@endif
