<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\Mailer;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Les courriels d'une requête, envoyés une fois la réponse partie, comme le faisait la file en
 * mémoire de Swiftmailer dans l'ancien BDOH (spool: memory) : une panne du serveur SMTP ne fait pas
 * échouer la page, dont l'écriture en base est déjà faite, et s'écrit dans le journal ; la durée de
 * l'envoi ne dit pas non plus si une adresse a un compte. Un courriel sans destinataire ne part pas,
 * comme avec Swiftmailer, quand Symfony Mailer lèverait une exception.
 */
class DeferredMailer implements EventSubscriberInterface
{
    /** @var Email[] */
    private array $queue = [];

    public function __construct(private MailerInterface $mailer, private LoggerInterface $logger)
    {
    }

    public function send(Email $email): void
    {
        if ($email->getTo() || $email->getCc()) {
            $this->queue[] = $email;
        }
    }

    public function flush(): void
    {
        foreach ($this->queue as $email) {
            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('Courriel non envoyé : ' . $e->getMessage(), ['subject' => $email->getSubject()]);
            }
        }
        $this->queue = [];
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'flush', ConsoleEvents::TERMINATE => 'flush'];
    }
}
