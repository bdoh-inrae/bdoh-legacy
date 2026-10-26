<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * La date de dernière connexion, à chaque connexion : par un lien, et par le cookie de 30 jours,
 * qui reconnecte en silence à chaque nouvelle session. L'ancien BDOH la notait à chaque connexion
 * par mot de passe, donc à chaque nouvelle session.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
class LastLoginListener
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof Utilisateur) {
            $user->setDerniereConnection(new \DateTime('now', new \DateTimeZone('UTC')));
            $this->em->flush();
        }
    }
}
