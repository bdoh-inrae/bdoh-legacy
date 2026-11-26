<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

/**
 * Pendant les essais (.env.example), l'adresse de MAILER_DIVERT_TO reçoit tous les courriels, et un destinataire
 * n'a le sien que s'il est dans MAILER_ALLOWED_RECIPIENTS (mailer.envelope_listener, config/services.yaml). Sa copie
 * d'un courriel bien arrivé et celle d'un courriel retenu se ressemblaient : l'objet d'un courriel dont un
 * destinataire n'est pas dans la liste le dit désormais en tête, en majuscules. Les adresses se comparent comme le
 * fait l'EnvelopeListener de Symfony, juste avant lui, qui remplace ensuite les destinataires.
 */
#[AsEventListener(priority: -250)]
final class TrialMailMarker
{
    /**
     * @param list<string> $divertTo
     * @param list<string> $allowed
     */
    public function __construct(
        #[Autowire(env: 'csv:MAILER_DIVERT_TO')]
        private readonly array $divertTo,
        #[Autowire(env: 'csv:MAILER_ALLOWED_RECIPIENTS')]
        private readonly array $allowed,
    ) {
    }

    public function __invoke(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if ($this->divertTo === [] || $event->isQueued() || !$message instanceof Email) {
            return;
        }
        $held = [];
        foreach ($event->getEnvelope()->getRecipients() as $recipient) {
            if (!$this->reaches($recipient->getAddress())) {
                $held[] = $recipient->getAddress();
            }
        }
        if ($held !== []) {
            $message->subject(sprintf('[ESSAIS : NON ENVOYÉ À %s] %s', implode(', ', $held), $message->getSubject() ?? ''));
        }
    }

    // L'adresse de détournement reçoit tout ; une autre, si une expression de la liste la décrit en entier
    private function reaches(string $address): bool
    {
        foreach ($this->divertTo as $divert) {
            if (strcasecmp($divert, $address) === 0) {
                return true;
            }
        }
        foreach ($this->allowed as $pattern) {
            if (preg_match('{\A'.$pattern.'\z}', $address)) {
                return true;
            }
        }

        return false;
    }
}
