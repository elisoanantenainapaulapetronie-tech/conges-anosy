<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Justificatifs (PDF ou image) d'une proposition de solde. Réutilise la table pieces_jointes (colonne proposition_id).
 *  - Stockage PRIVÉ (disque « local », hors de public/) : un justificatif n'est servi qu'à une personne autorisée
 *    (SoldePolicy::lireJustificatif), jamais par une adresse devinable ;
 *  - le nom du fichier sur le disque est aléatoire : le nom d'origine n'est gardé que pour l'affichage ;
 *  - le type réel est lu sur le contenu du fichier (pas sur son extension).
 */
class SoldeJustificatifService
{
    public const DOSSIER = 'justificatifs-soldes';

    public const TAILLE_MAX_KO = 5120;

    /** type réel => extension */
    public const TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public static function regles(bool $obligatoire = true): array
    {
        return ['justificatif' => [$obligatoire ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::TAILLE_MAX_KO]];
    }

    public static function messages(): array
    {
        return [
            'justificatif.required' => 'Joignez le document justificatif (PDF, JPG ou PNG).',
            'justificatif.file' => 'Le document n’a pas pu être envoyé.',
            'justificatif.mimes' => 'Formats acceptés : PDF, JPG ou PNG.',
            'justificatif.max' => 'Le document ne doit pas dépasser 5 Mo.',
            'justificatif.uploaded' => 'L’envoi a échoué : le document est peut-être trop lourd.',
        ];
    }

    /**
     * Enregistre le fichier et le rattache à la proposition. Retourne l'identifiant de la pièce.
     * Lève une \RuntimeException si le contenu n'est pas un PDF, un JPEG ou un PNG.
     */
    public static function attacher(int $propositionId, UploadedFile $fichier, Agent $auteur): int
    {
        $mime = self::typeReel($fichier->getRealPath());
        if ($mime === null) {
            throw new \RuntimeException('Le contenu du fichier n’est pas un PDF, un JPG ou un PNG valide.');
        }

        $chemin = self::DOSSIER.'/'.Str::uuid()->toString().'.'.self::TYPES[$mime];
        Storage::disk('local')->putFileAs(self::DOSSIER, $fichier, basename($chemin));

        $nom = Str::limit(preg_replace('/[^\p{L}\p{N}\s._()\-]/u', '', $fichier->getClientOriginalName()) ?: 'justificatif.'.self::TYPES[$mime], 200, '');

        try {
            return (int) DB::table('pieces_jointes')->insertGetId([
                'proposition_id' => $propositionId, 'nom_original' => $nom, 'chemin' => $chemin, 'mime' => $mime,
                'taille_octets' => max(1, (int) $fichier->getSize()), 'medicale' => 0, 'depose_par' => $auteur->id, 'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($chemin);   // pas de fichier orphelin si l'écriture en base échoue
            throw $e;
        }
    }

    /** Pièces d'une ou plusieurs propositions : proposition_id => liste. */
    public static function parProposition(iterable $propositionIds): array
    {
        $ids = collect($propositionIds)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        return DB::table('pieces_jointes')->whereIn('proposition_id', $ids)->orderBy('id')
            ->get(['id', 'proposition_id', 'nom_original', 'mime', 'taille_octets'])->groupBy('proposition_id')->all();
    }

    /** Type réel d'après les premiers octets : PDF, JPEG ou PNG, sinon null. */
    public static function typeReel(string $chemin): ?string
    {
        $debut = @file_get_contents($chemin, false, null, 0, 8);
        if ($debut === false || $debut === '') {
            return null;
        }

        return match (true) {
            str_starts_with($debut, '%PDF-') => 'application/pdf',
            str_starts_with($debut, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($debut, "\x89PNG\r\n\x1A\n") => 'image/png',
            default => null,
        };
    }

    public static function taille(int $octets): string
    {
        return $octets >= 1048576 ? number_format($octets / 1048576, 1, ',', ' ').' Mo' : max(1, (int) round($octets / 1024)).' Ko';
    }
}
