<?php

namespace Irstea\BdohTheiaOzcarBundle\Form;

use Irstea\BdohTheiaOzcarBundle\Service\PasswordEncryptionServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class PasswordEventSubscriber implements EventSubscriberInterface
{
    /** @var PasswordEncryptionServiceInterface */
    private $passwordEncryptionService;

    public function __construct(PasswordEncryptionServiceInterface $passwordEncryptionService)
    {
        $this->passwordEncryptionService = $passwordEncryptionService;
    }

    public function onPreSubmit(FormEvent $event): void
    {
        $clearPassword = trim((string) $event->getData());
        if ($clearPassword) {
            // Encrypte le mot de passe
            $event->setData($this->passwordEncryptionService->encrypt($clearPassword));
        } else {
            // Ignore un champ vide (=n'efface pas le mot de passe existant)
            $form = $event->getForm();
            if ($form->getParent()) {
                $form->getParent()->remove($form->getName());
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [FormEvents::PRE_SUBMIT => 'onPreSubmit'];
    }
}
