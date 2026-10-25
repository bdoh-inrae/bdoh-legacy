<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\Persistence\ManagerRegistry;
use Irstea\BdohDataBundle\Entity\ChroniqueConvertie;
use Irstea\BdohDataBundle\Entity\Repository\ChroniqueConvertieRepository;
use Irstea\BdohDataBundle\EventListener\MeasuresUpdateEvent;
use Irstea\BdohDataBundle\Events;
use Irstea\BdohLoggerBundle\Logger\BdohLogger;
use Irstea\BdohSecurityBundle\Entity\Repository\UtilisateurRepository;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * {@inheritdoc}
 */
class ConvertChroniqueCommand extends AbstractJobCommand
{
    const COMMAND_NAME = 'bdoh:convert:chronique';

    public function __construct(
        ManagerRegistry $doctrine,
        TokenStorageInterface $tokenStorage,
        private BdohLogger $logger,
        private EventDispatcherInterface $dispatcher
    ) {
        parent::__construct($doctrine, $tokenStorage);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Calcule les mesures d\'une chronique convertie.')
            ->addArgument('chronique_id', InputArgument::REQUIRED, 'Identifiant de la chronique')
            ->addArgument('user_id', InputArgument::REQUIRED, 'Initiateur de la comande')
            ->addOption('propagate', 'p', InputOption::VALUE_NONE, 'Propager aux chroniques filles');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws EntityNotFoundException
     * @throws InvalidArgumentException
     *
     * @return int Exit code
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $chroniqueId = $input->getArgument('chronique_id');
        $userId = $input->getArgument('user_id');
        $propagate = $input->getOption('propagate');

        return $this->doctrine
            ->getManagerForClass(ChroniqueConvertie::class)
            ->wrapInTransaction(
                function (EntityManagerInterface $manager) use ($chroniqueId, $userId, $propagate) {
                    /** @var ChroniqueConvertieRepository $chroRepo */
                    $chroRepo = $manager->getRepository(ChroniqueConvertie::class);

                    /** @var ChroniqueConvertie $chronique */
                    $chronique = $chroRepo->findOneById($chroniqueId);

                    if (!$chronique) {
                        throw new EntityNotFoundException(sprintf('%s:%d', ChroniqueConvertie::class, $chroniqueId));
                    }

                    /** @var UtilisateurRepository $userRepo */
                    $userRepo = $manager->getRepository(Utilisateur::class);

                    /** @var Utilisateur $user */
                    $user = $userRepo->find($userId);

                    if (!$user) {
                        throw new EntityNotFoundException(sprintf('%s:%d', Utilisateur::class, $userId));
                    }

                    $result = $chroRepo->convertChronique($chronique);

                    if ($result === 0) {
                        $manager->refresh($chronique);

                        // met à jour la date de conversion de la chronique (date de mise à jour)
                        $date = new \DateTime('now', new \DateTimeZone('UTC'));
                        $chronique->setMiseAJour($date);
                        // et met à jour l'autorisation des lq ld sur la chronique convertie en fonction de la chronique mère
                        $chronique->setAllowValueLimits((bool) $chronique->getConversion()->getEntree()->getAllowValueLimits());
                        $manager->persist($chronique);
                        $manager->flush();

                        // historique de conversion
                        $this->logger->createHistoriqueConversionChronique(
                            $user,
                            $date,
                            $chronique->getObservatoire(),
                            $chronique
                        );

                        // MeasuresUpdateEvent pour les taux de remplissage et la propagation
                        $event = new MeasuresUpdateEvent(
                            $chronique,
                            $user,
                            new \DateTime($chronique->getDateDebutMesures() ?? 'now'),
                            new \DateTime($chronique->getDateFinMesures() ?? 'now'),
                            $propagate
                        );
                        $this->dispatcher->dispatch($event, Events::MEASURES_UPDATE);
                    }

                    return $result;
                }
            );
    }
}
