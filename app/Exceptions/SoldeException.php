<?php

namespace App\Exceptions;

use RuntimeException;

/** Règle de gestion des soldes non respectée : le message est écrit pour être affiché tel quel à la personne. */
class SoldeException extends RuntimeException
{
}
