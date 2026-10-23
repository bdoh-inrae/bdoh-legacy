<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security;

use Doctrine\ORM\EntityManager;
use Irstea\BdohSecurityBundle\Entity\Repository\UtilisateurRepository;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Custom authentication success handler.
 *
 * @DI\Service("irstea_bdoh_security.security.success_handler", public=false)
 */
class BdohAuthenticationHandler implements AuthenticationSuccessHandlerInterface
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var UtilisateurRepository
     */
    private $em;

    /**
     * Constructor.
     *
     * @param RouterInterface $router
     * @param EntityManager   $em
     * @DI\InjectParams({
     *     "router" = @DI\Inject("router"),
     *     "em" = @DI\Inject("doctrine.orm.entity_manager"),
     * })
     */
    public function __construct(RouterInterface $router, EntityManager $em)
    {
        $this->router = $router;
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        $user = $token->getUser();
        if ($user instanceof Utilisateur) {
            $user->setDerniereConnection(new \DateTime('now', new \DateTimeZone('UTC')));
            $this->em->persist($user);
            $this->em->flush();
        }

        $session = $request->getSession();
        $uri = $session ? $session->get('_security.secured_area.target_path', null) : null;
        if ($uri !== null) {
            $session->remove('_security.secured_area.target_path');
        } else {
            $uri = $this->router->generate('bdoh_home');
        }

        return new RedirectResponse($uri);
    }
}
