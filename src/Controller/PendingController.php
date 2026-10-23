<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Répond aux routes des modules pas encore portés (config/routes_pending/) : elles existent, pour
 * que les pages déjà portées puissent y renvoyer, mais ne servent rien. À retirer avec la dernière.
 */
final class PendingController
{
    public function __invoke(): Response
    {
        return new Response('Pas encore porté.', Response::HTTP_NOT_IMPLEMENTED, ['Content-Type' => 'text/plain']);
    }
}
