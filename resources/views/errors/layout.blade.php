<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') — Congés Anosy</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/anosy.css') }}">
</head>
<body>
<main class="erreur-page">
    <div class="erreur-page__carte">
        <div class="marque">
            <img src="{{ asset('images/logo-anosy.png') }}" alt="Armoiries de la Région Anosy" width="62">
            <div><strong>Région Anosy</strong><span>Congés, absences et missions</span></div>
        </div>
        <p class="erreur-page__code">Erreur @yield('code')</p>
        <h1>@yield('titre')</h1>
        <p>@yield('explication')</p>
        <a class="bouton" href="{{ url('/accueil') }}">Retour à l’accueil</a>
    </div>
</main>
</body>
</html>
