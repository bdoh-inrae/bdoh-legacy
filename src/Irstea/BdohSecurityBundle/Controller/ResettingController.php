<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Irstea\BdohBundle\Controller\Controller;

/**
 * Le mot de passe oublié n'existe plus : on se connecte par un lien envoyé par courriel
 * (dev/differences.md). Ses adresses, que d'anciens courriels contiennent, mènent à la page de
 * connexion.
 */
#[Route('/resetting')]
class ResettingController extends Controller
{
    #[Route('/{rest}', name: 'resetting_request', requirements: ['rest' => '.*'], defaults: ['rest' => ''], methods: ['GET', 'POST'])]
    public function requestAction()
    {
        return $this->redirectToRoute('bdoh_security_login', [], 301);
    }
}
