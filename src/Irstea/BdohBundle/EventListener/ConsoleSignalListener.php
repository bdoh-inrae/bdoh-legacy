<?php

namespace Irstea\BdohBundle\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Une commande interrompue par un signal (SIGTERM, SIGINT) sort sur 128 + signal, comme un
 * processus tué sous l'ancien BDOH, et non sur 0 comme le veut Symfony 7 : une tâche de la file
 * coupée en cours reste en échec, et relançable, au lieu de passer pour finie. L'exécuteur de la
 * file (RunJobsCommand) pose ses propres gestionnaires de signaux, qui remplacent celui-ci.
 */
#[AsEventListener(event: ConsoleEvents::SIGNAL)]
final class ConsoleSignalListener
{
    public function __invoke(ConsoleSignalEvent $event): void
    {
        $event->setExitCode(128 + $event->getHandlingSignal());
    }
}
