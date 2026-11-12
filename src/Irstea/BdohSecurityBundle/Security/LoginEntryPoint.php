<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Security;

use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Une page protégée, demandée sans être connecté, renvoie à la page de connexion de l'observatoire,
 * comme le faisait form_login, ou à celle du portail pour une page sans observatoire (les favoris,
 * AccountRoutes) ; le pare-feu garde l'adresse demandée, où l'on revient une fois connecté.
 *
 * Connecté par le cookie de 30 jours, on est connecté quand même : une page refusée répond 403, comme
 * à qui s'est connecté par un lien. Le pare-feu passe ici parce qu'une connexion par cookie n'est pas
 * « complète » ; la page de connexion renverrait à l'accueil, sans rien dire.
 */
class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private HttpUtils $httpUtils,
        private ObservatoireManagerInterface $observatoireManager
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (null !== $authException?->getToken()) {
            throw new AccessDeniedHttpException('Access Denied.', $authException);
        }

        return $this->httpUtils->createRedirectResponse(
            $request,
            AccountRoutes::name('login', $this->observatoireManager->getCurrent())
        );
    }
}
