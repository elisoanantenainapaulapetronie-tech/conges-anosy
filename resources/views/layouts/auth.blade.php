<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Le lien de réinitialisation ne doit jamais être transmis à un autre site --}}
    <meta name="referrer" content="no-referrer">
    <title>@yield('titre') — Congés Anosy</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @include('partials.polices')
    <link rel="stylesheet" href="{{ asset('css/anosy.css') }}">
</head>
<body>
<div class="auth">
    <section class="auth__visuel">
        <img class="auth__img" src="{{ asset('images/'.trim($__env->yieldContent('photo', 'fort-dauphin-baie.jpg'))) }}" alt="">
        <div class="auth__voile"></div>
        <div class="auth__texte">
            <p class="auth__lieu">@yield('lieu', 'Baie de Fort-Dauphin')</p>
            <p class="auth__accroche">@yield('accroche', 'Congés, absences et missions du personnel de la Région Anosy')</p>
            <p class="auth__sous">@yield('accroche_sous', 'Déposez vos demandes, suivez leur validation et consultez votre solde depuis un seul espace.')</p>
        </div>
    </section>
    <div class="auth__tranche" aria-hidden="true"><i></i><i></i></div>

    <main class="auth__main">
        <div class="auth__boite">
            <div class="marque">
                <img src="{{ asset('images/logo-anosy.png') }}" alt="Armoiries de la Région Anosy" width="62">
                <div><strong>Région Anosy</strong><span>Congés, absences et missions</span></div>
            </div>

            @yield('contenu')

            <p class="auth__pied">Un souci d’accès ? Contactez le service Gestion des ressources humaines.</p>
        </div>
    </main>
</div>
<script src="{{ asset('js/auth.js') }}" defer></script>
</body>
</html>
