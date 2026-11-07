<?php

return [
    /*
     | Déconnexion automatique : nombre de minutes SANS ACTIVITÉ après lesquelles la session est fermée.
     | Valable pour tous les espaces (Agent, RH, Responsable…). Pour la changer : INACTIVITE_MINUTES dans .env.
     | Ce réglage remplace SESSION_LIFETIME du .env (voir DroitsServiceProvider).
     */
    'inactivite_minutes' => max(5, (int) env('INACTIVITE_MINUTES', 30)),
];
