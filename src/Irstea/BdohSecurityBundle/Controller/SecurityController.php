<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Irstea\BdohBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security as CoreSecurity;

/**
 * Class SecurityController.
 */
class SecurityController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/login', name: 'bdoh_security_login', methods: ['GET'])]
    public function loginAction(Request $request)
    {
        // si l'utilisateur est déjà identifié on le redirige sur l'accueil de l'observatoire
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('bdoh_home');
        }

        $session = $request->getSession();

        // get the login error if there is one
        if ($request->attributes->has(CoreSecurity::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(CoreSecurity::AUTHENTICATION_ERROR);
        } else {
            $error = $session->get(CoreSecurity::AUTHENTICATION_ERROR);
            $session->remove(CoreSecurity::AUTHENTICATION_ERROR);
        }

        return $this->render(
            '@IrsteaBdohSecurity/Security/login.html.twig',
            [
                // last username entered by the user
                'last_username' => $session->get(CoreSecurity::LAST_USERNAME),
                'error'         => $error,
            ]
        );
    }
}
