<?php


namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\TypeDemande;
use App\Services\DecompteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use App\Services\SoumissionService;



class DemandeController extends Controller
{


public function soumettre(Request $request, Demande $demande): RedirectResponse
{
    Gate::authorize('soumettre', $demande);

    $d = SoumissionService::soumettre($demande, $request->user()->agent);

    return redirect()->route('demandes.show', $d)->with('statut', 'Demande envoyée sous la référence '.$d->reference.'.');
}

public function index(Request $request)
{
    $demandes = Demande::with('typeDemande:id,libelle')
        ->where('agent_id' , $request->user()->agent_id)
        ->latest('id')
        ->get();

    return view('demandes.index', ['demandes' => $demandes]);
}

public function create()
{
    Gate::authorize('creer', Demande::class);

    return view('demandes.formulaire', ['demande' => new Demande, 'types' => $this->types()]);
}

public function store(Request $request): RedirectResponse
{
    Gate::authorize('creer', Demande::class);

    $demande = Demande::create($this->valider($request) + [
        'agent_id' => $request->user()->agent_id,
        'statut' => 'brouillon',
    ]);

    return redirect()->route('demandes.show', $demande)->with('succes', 'Brouillon enregisté.');
}

public function show(Demande $demande)
{
    Gate::authorize('voir', $demande);

    $demande->load(['typeDemande', 'etapes', 'historique', 'piecesJointes']);

    return view('demandes.show', ['demande' => $demande]);
}

public function edit(Demande $demande)
{
    Gate::authorize('modifier', $demande);

    return view('demandes.formulaire', ['demande' => $demande, 'types' => $this->types()]);
}

public function update(Request $request, Demande $demande):RedirectResponse
{
    Gate::authorize('modifier', $demande);

    $demande->update($this->valider($request));

    return redirect()->route('demandes.show', $demande)->with('succes', 'Brouillon mis à jour.');
}

private function types()
{
    return TypeDemande::where('actif', true)->orderBy('libelle')->get(['id', 'code', 'libelle']);
}


private function valider(Request $request): array
{
    $donnees = $request->validate([
        'type_demande_id' => ['required', 'integer', Rule::exists('types_demande', 'id')->where('actif', true)],
        'date_debut' => ['required', 'date_format:Y-m-d'],
        'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
        'motif' => ['nullable', 'string', 'max:2000'],
        'contact_pendant_absence' => ['nullable', 'string', 'max:255'],

    ], [
        'type_demande-id.required' => 'Choisissez le type de demande.',
        'date_debut.required' => 'Choisissez la date de début.',
        'date_fin.required' => 'Choisissez la date de fin.',
        'date_fin.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
    ]);

    try {
    $calcul = DecompteService::compter($donnees['date_debut'], $donnees['date_fin'], 'journee', 'journee');
} catch (InvalidArgumentException $e) {
    throw ValidationException::withMessages(['date_fin' => $e->getMessage()]);
}

if ($calcul['total'] <= 0) {
    throw ValidationException::withMessages([
        'date_fin' => 'Cette période ne contient aucun jour décompté (week-end ou jour férié). Choisissez au moins un jour ouvré.',
    ]);
}

    $donnees['duree_jours'] = $calcul['total'];
    $donnees['urgence'] = $request->boolean('urgence');

    return $donnees;
  }

}