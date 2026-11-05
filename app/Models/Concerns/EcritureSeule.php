<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Registre en écriture seule : on ajoute, on ne modifie jamais, on ne supprime jamais.
 * Les déclencheurs SQL font la même chose côté base ; ceci donne une erreur claire côté PHP.
 */
trait EcritureSeule
{
    protected static function bootEcritureSeule(): void
    {
        static::updating(function () {
            throw new LogicException('Cette table est en écriture seule : corrigez par une nouvelle ligne.');
        });
        static::deleting(function () {
            throw new LogicException('Cette table est en écriture seule : suppression interdite.');
        });
    }
}
