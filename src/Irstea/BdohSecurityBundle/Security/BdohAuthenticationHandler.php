<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Custom authentication success handler, pour la connexion par lien : renvoie à la page demandée
 * avant la connexion, ou à l'accueil. La date de dernière connexion, elle, vaut pour tous les modes
 * de connexion (LastLoginListener).
 *
 * La première connexion d'un compte inscrit par le formulaire, par le lien de son courriel de
 * bienvenue, confirme son adresse. Elle ne prévient plus les gestionnaires (Lou, 2026-11-12 ;
 * l'ancien BDOH les prévenait à chaque inscription) : un compte sert aux demandes d'accès, qui les
 * préviennent, aux favoris et à l'administration.
 */
class BdohAuthenticationHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private RouterInterface $router,
        private EntityManagerInterface $em,
        private ObservatoireManagerInterface $observatoireManager
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof Utilisateur && Utilisateur::AWAITING_CONFIRMATION === $user->getConfirmationToken()) {
            $user->setConfirmationToken(null);
            $this->em->flush();
        }

        $session = $request->getSession();
        $uri = $session ? $session->get('_security.secured_area.target_path', null) : null;
        if ($uri !== null) {
            $session->remove('_security.secured_area.target_path');
        } else {
            $uri = $this->router->generate(AccountRoutes::name('home', $this->observatoireManager->getCurrent()));
        }

        return new RedirectResponse($uri);
    }
}
