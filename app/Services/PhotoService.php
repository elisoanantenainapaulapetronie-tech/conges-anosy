<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Photos de profil des agents.
 *  - Stockage PRIVÉ (disque « local », hors de public/) : une photo n'est servie qu'à une personne autorisée.
 *  - Chaque envoi est reconverti en JPEG carré 400 x 400 : les métadonnées (GPS, appareil) disparaissent
 *    et le poids reste petit, quelle que soit la photo de départ.
 */
class PhotoService
{
    public const DOSSIER = 'photos-agents';

    public const TAILLE = 400;

    public static function regles(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120',
                'dimensions:min_width=150,min_height=150,max_width=6000,max_height=6000'],
        ];
    }

    public static function messages(): array
    {
        return [
            'photo.required' => 'Choisissez une photo.',
            'photo.file' => 'Le fichier n’a pas pu être envoyé.',
            'photo.mimes' => 'Format accepté : JPEG, PNG ou WebP.',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
            'photo.dimensions' => 'La photo doit mesurer au moins 150 × 150 pixels et au plus 6 000 × 6 000.',
            'photo.uploaded' => 'L’envoi a échoué : la photo est peut-être trop lourde.',
        ];
    }

    /** La RH responsable peut réserver la photo à la RH (paramètre photo_modifiable_par_agent = 0). */
    public static function modifiableParAgent(): bool
    {
        return Parametres::valeur('photo_modifiable_par_agent', '1') === '1';
    }

    public static function enregistrer(Agent $agent, UploadedFile $fichier): void
    {
        try {
            $jpeg = self::versJpegCarre($fichier->getRealPath());
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['photo' => $e->getMessage()]);
        }

        $chemin = self::DOSSIER.'/'.$agent->id.'.jpg';
        Storage::disk('local')->put($chemin, $jpeg);

        DB::table('agents')->where('id', $agent->id)
            ->update(['photo_chemin' => $chemin, 'photo_mise_a_jour_le' => now()]);
    }

    public static function supprimer(Agent $agent): void
    {
        if ($agent->photo_chemin) {
            Storage::disk('local')->delete($agent->photo_chemin);
        }

        DB::table('agents')->where('id', $agent->id)
            ->update(['photo_chemin' => null, 'photo_mise_a_jour_le' => null]);
    }

    /**
     * Lit une image JPEG, PNG ou WebP, la redresse (orientation du téléphone), la recadre en carré
     * et la renvoie en JPEG 400 x 400. Indépendante de Laravel : testable seule.
     *
     * @throws \RuntimeException message lisible par l'utilisateur
     */
    public static function versJpegCarre(string $chemin): string
    {
        @ini_set('memory_limit', '384M'); // une photo de 6 000 x 6 000 px occupe environ 150 Mo une fois décodée

        $info = @getimagesize($chemin);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new \RuntimeException('Ce fichier n’est pas une image JPEG, PNG ou WebP valide.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($chemin));
        if (! $source) {
            throw new \RuntimeException('Cette image est illisible ou endommagée. Essayez une autre photo.');
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($chemin);
            $angle = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($angle !== 0 && ($tournee = imagerotate($source, $angle, 0))) {
                $source = $tournee;
            }
        }

        $largeur = imagesx($source);
        $hauteur = imagesy($source);
        $cote = min($largeur, $hauteur);
        $x = intdiv($largeur - $cote, 2);
        // Portrait : on garde le haut de l'image, là où se trouve le visage.
        $y = $hauteur > $largeur ? (int) round(($hauteur - $cote) * 0.25) : 0;

        $cible = imagecreatetruecolor(self::TAILLE, self::TAILLE);
        imagefill($cible, 0, 0, imagecolorallocate($cible, 255, 255, 255)); // fond blanc pour les PNG transparents
        imagecopyresampled($cible, $source, 0, 0, $x, $y, self::TAILLE, self::TAILLE, $cote, $cote);

        ob_start();
        imagejpeg($cible, null, 85);

        return (string) ob_get_clean();
    }
}
