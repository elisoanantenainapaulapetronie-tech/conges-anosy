{{-- Lien de réinitialisation remis par la RH : affiché une seule fois (juste après sa génération), + bouton pour en générer un.
     À inclure dans la fiche de l'agent, section « Compte de connexion » :  @include('rh.agents._lien-reinitialisation', ['agent' => $agent, 'compte' => $compte]) --}}
@if (session('lien_reinitialisation'))
    <div class="code-box" role="status">
        <p><strong>Lien de réinitialisation</strong> (valable jusqu’au {{ session('lien_expire') }}, une seule utilisation) :</p>
        <p class="code-box__code" style="font-size:.95rem;word-break:break-all">{{ session('lien_reinitialisation') }}</p>
        <p><strong>Ce lien ne sera plus affiché.</strong> Remettez-le à l’agent en main propre ; il choisira lui-même son nouveau mot de passe.</p>
    </div>
@endif
@if ($compte?->statut === 'actif' && $agent->situation === 'actif')
    @can('genererLienReinitialisation', $agent)
        <form method="POST" action="{{ route('rh.comptes.lien', $agent) }}" data-envoi
              onsubmit="return confirm('Générer un lien de réinitialisation ? Il remplace tout lien en cours pour ce compte.')">
            @csrf
            <button type="submit" class="bouton bouton--ligne bouton--discret">Générer un lien de réinitialisation du mot de passe</button>
        </form>
    @endcan
@endif
