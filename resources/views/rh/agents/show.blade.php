@extends('layouts.app')
@section('titre', $agent->nom_complet)
@php
    $compte = $agent->compte;
    $libellesCompte = ['actif' => 'Compte actif', 'en_attente_activation' => 'En attente d’activation', 'desactive' => 'Compte désactivé'];
    $classesCompte = ['actif' => 'pastille--ok', 'en_attente_activation' => 'pastille--attente', 'desactive' => 'pastille--off'];
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '—';
    $codeExpire = $compte?->code_activation_expire_le ? \Illuminate\Support\Carbon::parse($compte->code_activation_expire_le) : null;
@endphp
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>{{ $agent->nom_complet }}</h1>
            <p class="poste">
                {{ $agent->fonction?->libelle ?? 'Sans fonction' }} · {{ $agent->structure?->nom }}
                @if ($agent->situation !== 'actif') <span class="pastille pastille--off">{{ ucfirst($agent->situation) }}</span> @endif
            </p>
        </div>
        @can('modifier', $agent)
            <a class="bouton bouton--ligne bouton--discret" href="{{ route('rh.agents.edit', $agent) }}">Modifier la fiche</a>
        @endcan
    </header>

    @include('rh._onglets', ['actif' => 'agents'])

    @if (session('code_activation'))
        <section class="code-box" role="status" aria-labelledby="cb-titre">
            <h2 id="cb-titre">Code d’activation de {{ $agent->nom_complet }}</h2>
            <p class="code-box__code" id="code-valeur">{{ session('code_activation') }}</p>
            <p>Identifiant de connexion : <strong>{{ $agent->identifiant }}</strong> · valable jusqu’au <strong>{{ session('code_expire') }}</strong>.</p>
            <p><strong>Ce code ne sera plus affiché.</strong> Remettez-le à l’agent en main propre : il choisira lui-même son mot de passe sur la page « Activer mon compte ».</p>
            <button type="button" class="bouton bouton--ligne bouton--discret" data-copier="code-valeur">Copier le code</button>
        </section>
    @endif

    <section class="panneau" aria-labelledby="s-fiche">
        <h2 id="s-fiche">Fiche</h2>
        <dl class="fiche">
            <dt>Identifiant</dt><dd>{{ $agent->identifiant }}</dd>
            <dt>Matricule</dt><dd>{{ $agent->matricule ?? '— (aucun)' }}</dd>
            <dt>Sexe</dt><dd>{{ $agent->sexe === 'F' ? 'Féminin' : 'Masculin' }}</dd>
            <dt>Naissance</dt><dd>{{ $fmt($agent->date_naissance) }}</dd>
            <dt>Téléphone</dt><dd>{{ $agent->telephone ?? '—' }}</dd>
            <dt>E-mail</dt><dd>{{ $agent->email ?? '—' }}</dd>
            <dt>Grade / catégorie</dt><dd>{{ $agent->grade_categorie ?? '—' }}</dd>
            <dt>Responsable direct</dt>
            <dd>@if ($agent->responsable) <a href="{{ route('rh.agents.show', $agent->responsable) }}">{{ $agent->responsable->nom_complet }}</a> @else — @endif</dd>
            <dt>Circuit de décision</dt><dd>{{ $agent->rattachement === 'region' ? 'Chef de Région' : 'Secrétaire Général' }}</dd>
            <dt>Entrée</dt><dd>{{ $fmt($agent->date_entree) }}</dd>
            @if ($agent->date_sortie) <dt>Sortie</dt><dd>{{ $fmt($agent->date_sortie) }}</dd> @endif
        </dl>
    </section>

    <section class="panneau" aria-labelledby="s-compte" id="compte">
        <h2 id="s-compte">Compte de connexion</h2>

        @if (! $compte)
            <p class="vide">Cet agent n’a pas encore de compte.</p>
        @else
            <dl class="fiche">
                <dt>État</dt>
                <dd><span class="pastille {{ $classesCompte[$compte->statut] ?? '' }}">{{ $libellesCompte[$compte->statut] ?? $compte->statut }}</span></dd>
                @if ($compte->statut === 'en_attente_activation' && $codeExpire)
                    <dt>Code valable jusqu’au</dt>
                    <dd>{{ $codeExpire->format('d/m/Y') }} @if ($codeExpire->isPast()) <span class="pastille pastille--alerte">expiré</span> @endif</dd>
                @endif
                @if ($compte->derniere_connexion_le)
                    <dt>Dernière connexion</dt><dd>{{ \Illuminate\Support\Carbon::parse($compte->derniere_connexion_le)->format('d/m/Y à H:i') }}</dd>
                @endif
            </dl>
        @endif

        <div class="actions-ligne">
            @can('genererCodeActivation', $agent)
                @if ($agent->situation === 'actif')
                    <form method="POST" action="{{ route('rh.comptes.code', $agent) }}" data-envoi
                          @if ($compte) onsubmit="return confirm('Générer un nouveau code ? L’ancien code et le mot de passe en attente seront annulés.')" @endif>
                        @csrf
                        <button type="submit" class="bouton bouton--ligne">{{ $compte ? 'Générer un nouveau code d’activation' : 'Créer le compte et générer le code' }}</button>
                    </form>
                @endif
            @endcan

            @can('changerEtatCompte', $agent)
                @if ($compte?->statut === 'actif')
                    <form method="POST" action="{{ route('rh.comptes.desactiver', $agent) }}"
                          onsubmit="return confirm('Désactiver ce compte ? L’agent ne pourra plus se connecter.')">
                        @csrf
                        <button type="submit" class="bouton bouton--ligne bouton--danger">Désactiver le compte</button>
                    </form>
                @elseif ($compte?->statut === 'desactive')
                    <form method="POST" action="{{ route('rh.comptes.reactiver', $agent) }}">
                        @csrf
                        <button type="submit" class="bouton bouton--ligne bouton--discret">Réactiver le compte</button>
                    </form>
                @endif
            @endcan
        </div>
        <p class="panneau__note" style="margin:14px 0 0">La RH ne voit et ne choisit jamais le mot de passe : l’agent le crée lui-même avec le code.</p>
    </section>

    <section class="panneau" aria-labelledby="s-roles" id="roles">
        <h2 id="s-roles">Rôles</h2>
        <p class="panneau__note">Tout agent est « Agent ». Les rôles ci-dessous s’y ajoutent, pour une période et sur acte.</p>

        @if ($habilitations->isEmpty())
            <p class="vide">Aucun rôle particulier : cet agent n’a que le rôle « Agent ».</p>
        @else
            <div class="table-enveloppe">
                <table class="tableau">
                    <caption class="sr-only">Rôles de l’agent</caption>
                    <thead><tr><th scope="col">Rôle</th><th scope="col">Périmètre</th><th scope="col">Période</th><th scope="col">Acte</th><th scope="col">Attribué par</th><th scope="col"><span class="sr-only">Action</span></th></tr></thead>
                    <tbody>
                    @foreach ($habilitations as $h)
                        @php $enCours = $h->date_debut <= $aujourdhui && ($h->date_fin === null || $h->date_fin >= $aujourdhui); @endphp
                        <tr class="{{ $enCours ? '' : 'ligne-passee' }}">
                            <td><strong>{{ $h->role_libelle }}</strong> @if ($enCours) <span class="pastille pastille--ok">en cours</span> @elseif ($h->date_debut > $aujourdhui) <span class="pastille pastille--attente">à venir</span> @else <span class="pastille pastille--off">terminé</span> @endif</td>
                            <td>{{ $h->structure_nom ?? 'Toute la Région' }}</td>
                            <td>{{ $fmt($h->date_debut) }} → {{ $h->date_fin ? $fmt($h->date_fin) : 'sans fin' }}</td>
                            <td>{{ $h->acte_reference ?? '—' }}</td>
                            <td>{{ $h->attribuee_par_nom ?: 'Initialisation' }}</td>
                            <td>
                                @can('attribuerRole', $agent)
                                    @if ($enCours || $h->date_debut > $aujourdhui)
                                        <form method="POST" action="{{ route('rh.habilitations.retirer', [$agent, $h->id]) }}"
                                              onsubmit="return confirm('Retirer ce rôle immédiatement ?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="lien-bouton">Retirer</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @can('attribuerRole', $agent)
            @if ($agent->situation === 'actif')
                <h3 class="sous-titre">Attribuer un rôle</h3>
                <form method="POST" action="{{ route('rh.habilitations.store', $agent) }}" data-envoi>
                    @csrf
                    <div class="grille-form">
                        <x-rh-liste nom="role_id" label="Rôle" :requis="true" vide="— Choisir —"
                                    :options="$roles->pluck('libelle', 'id')->all()" />
                        <x-rh-liste nom="structure_id" label="Périmètre (service ou direction)" vide="Toute la Région"
                                    :options="$structures->all()" aide="Obligatoire pour « Responsable hiérarchique »." />
                        <x-rh-champ nom="date_debut" label="À partir du" type="date" :requis="true" :valeur="$aujourdhui" />
                        <x-rh-champ nom="date_fin" label="Jusqu’au" type="date" aide="Laisser vide si le rôle n’a pas de fin prévue." />
                        <x-rh-champ nom="acte_reference" label="Acte de référence" :requis="true" placeholder="Ex. Note de service n° 12" classe="grille-form__large" />
                    </div>
                    <button type="submit" class="bouton bouton--ligne">Attribuer le rôle</button>
                </form>
            @endif
        @else
            <p class="panneau__note" style="margin:14px 0 0">Seul le RH responsable attribue ou retire les rôles, et jamais les siens.</p>
        @endcan
    </section>

    @if ($equipe->isNotEmpty())
        <section class="panneau" aria-labelledby="s-equipe">
            <h2 id="s-equipe">Agents placés sous sa responsabilité ({{ $equipe->count() }})</h2>
            <ul class="liste-simple">
                @foreach ($equipe as $e)
                    <li><a href="{{ route('rh.agents.show', $e) }}">{{ $e->nom_complet }}</a><span class="discret">{{ $e->identifiant }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="panneau" aria-labelledby="s-histo">
        <h2 id="s-histo">Historique d’affectation</h2>
        <div class="table-enveloppe">
            <table class="tableau">
                <caption class="sr-only">Historique d’affectation</caption>
                <thead><tr><th scope="col">Période</th><th scope="col">Service</th><th scope="col">Responsable</th><th scope="col">Circuit</th><th scope="col">Motif</th></tr></thead>
                <tbody>
                @foreach ($affectations as $a)
                    <tr>
                        <td>{{ $fmt($a->date_debut) }} → {{ $a->date_fin ? $fmt($a->date_fin) : 'en cours' }}</td>
                        <td>{{ $a->structure_nom }}</td>
                        <td>{{ $a->responsable_nom ?: '—' }}</td>
                        <td>{{ $a->rattachement === 'region' ? 'Chef de Région' : 'Secrétaire Général' }}</td>
                        <td>{{ $a->motif ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="panneau__note" style="margin:12px 0 0">Les circuits des demandes sont figés à la soumission : un changement de poste n’altère pas les demandes déjà déposées.</p>
    </section>
@endsection
