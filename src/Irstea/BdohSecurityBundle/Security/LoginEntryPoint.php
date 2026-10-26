<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * Une page protégée, demandée sans être connecté, renvoie à la page de connexion de l'observatoire,
 * comme le faisait form_login ; le pare-feu garde l'adresse demandée, où l'on revient une fois connecté.
 */
class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private HttpUtils $httpUtils)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->httpUtils->createRedirectResponse($request, 'bdoh_security_login');
    }
}
