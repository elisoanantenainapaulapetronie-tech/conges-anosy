{{-- Paramètres provisoires « à confirmer par la RH ». À inclure dans le tableau de bord RH :  @include('rh._valeurs-provisoires') --}}
@php
    $provisoires = \Illuminate\Support\Facades\DB::table('parametres')->where('a_confirmer', 1)->orderBy('cle')->get();
    $peutConfirmer = auth()->user()->agent->aLeRole('rh_responsable');
@endphp
@if ($provisoires->isNotEmpty())
    <section class="panneau" aria-labelledby="t-provisoires">
        <h2 id="t-provisoires">Valeurs provisoires à confirmer par la RH</h2>
        <p class="panneau__note">Ces valeurs ont été fixées par défaut pour le fonctionnement de l’application. Tant qu’elles ne sont pas confirmées, elles restent signalées « provisoires ». @unless ($peutConfirmer) Seule la RH responsable peut les confirmer. @endunless</p>
        <ul class="liste-simple">
            @foreach ($provisoires as $p)
                <li>
                    <span><strong>{{ $p->cle }}</strong> <span class="pastille pastille--attente">provisoire</span><br><span class="discret">{{ $p->description }}</span></span>
                    @if ($peutConfirmer)
                        <form method="POST" action="{{ route('rh.parametres.confirmer', $p->cle) }}" data-envoi style="display:flex;gap:.5rem;align-items:center">
                            @csrf
                            <label class="discret" for="v-{{ $p->cle }}">Valeur</label>
                            <input id="v-{{ $p->cle }}" name="valeur" type="text" inputmode="decimal" size="6" value="{{ $p->valeur }}" placeholder="non fixée">
                            <button type="submit" class="bouton bouton--ligne">Confirmer</button>
                        </form>
                    @else
                        <span>{{ $p->valeur ?? 'non fixée' }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
