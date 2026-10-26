<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * Un lien de connexion pour un compte, écrit sur la sortie : à lui transmettre quand le courriel ne
 * lui parvient pas. Il vaut celui de la page de connexion (15 minutes, un seul usage) ; l'adresse du
 * site vient de DEFAULT_URI. Sert aussi à dev/compare/compare.py, pour se connecter sans courriel.
 */
class LoginLinkCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RouterInterface $router,
        // Le gestionnaire du pare-feu, désigné par son nom : hors d'une requête, Symfony ne le déduit pas
        #[Autowire(service: 'security.authenticator.login_link_handler.secured_area')]
        private LoginLinkHandlerInterface $loginLinkHandler
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('security:users:login-link')
            ->setDescription("Crée un lien de connexion pour un compte, à lui transmettre si le courriel ne lui parvient pas")
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail du compte')
            ->addArgument('observatoire', InputArgument::REQUIRED, "L'observatoire de la page de connexion, tel qu'il est écrit dans l'adresse (DRAIX…)");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->em->getRepository(Utilisateur::class)->findOneByEmail($input->getArgument('email'));
        if (null === $user) {
            $output->writeln('<error>Aucun compte pour cette adresse.</error>');

            return Command::FAILURE;
        }

        $slug = strtoupper($input->getArgument('observatoire'));
        if (null === $this->em->getRepository(Observatoire::class)->findOneBySlug($slug)) {
            $output->writeln('<error>Aucun observatoire ' . $slug . '.</error>');

            return Command::FAILURE;
        }

        $this->router->getContext()->setParameter('_observatoire', $slug);
        $output->writeln($this->loginLinkHandler->createLoginLink($user)->getUrl());

        return Command::SUCCESS;
    }
}
