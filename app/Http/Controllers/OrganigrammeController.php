<?php

namespace App\Http\Controllers;

use App\Services\OrganigrammeService;
use Illuminate\Http\Request;

/**
 * Organigramme de la Région : lisible par tout agent connecté (c'est l'organisation officielle).
 * Les alertes, les structures désactivées et les boutons de modification sont réservés à la RH.
 */
class OrganigrammeController extends Controller
{
    public function index(Request $request)
    {
        $moi = $request->user()->agent;
        $estRh = $moi->estRh();
        $inactives = $estRh && $request->boolean('inactives');

        $donnees = OrganigrammeService::construire($inactives, $moi->structure_id ? (int) $moi->structure_id : null);

        return view('organigramme.index', $donnees + [
            'estRh' => $estRh,
            'peutModifier' => $moi->aLeRole('rh_responsable'),
            'inactives' => $inactives,
        ]);
    }
}
