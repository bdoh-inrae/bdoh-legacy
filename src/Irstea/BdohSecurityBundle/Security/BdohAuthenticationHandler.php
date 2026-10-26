<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use Irstea\BdohBundle\Mailer\DeferredMailer;
use Irstea\BdohBundle\Manager\ObservatoireManagerInterface;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Custom authentication success handler, pour la connexion par lien : renvoie à la page demandée
 * avant la connexion, ou à l'accueil. La date de dernière connexion, elle, vaut pour tous les modes
 * de connexion (LastLoginListener).
 *
 * La première connexion d'un compte inscrit par le formulaire, par le lien de son courriel de
 * bienvenue, confirme son adresse : c'est alors seulement que les gestionnaires de l'observatoire en
 * sont prévenus (l'ancien BDOH les prévenait à l'inscription, adresse confirmée ou non). Les comptes
 * créés autrement (console, administration) ne les préviennent pas, comme dans l'ancien.
 */
class BdohAuthenticationHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private RouterInterface $router,
        private EntityManagerInterface $em,
        private DeferredMailer $mailer,
        private TranslatorInterface $translator,
        private Environment $twig,
        private ObservatoireManagerInterface $observatoireManager,
        private LoggerInterface $logger
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof Utilisateur && Utilisateur::AWAITING_CONFIRMATION === $user->getConfirmationToken()) {
            $user->setConfirmationToken(null);
            $this->em->flush();
            $this->notifyManagers($user);
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

    private function notifyManagers(Utilisateur $user): void
    {
        $observatoire = $this->observatoireManager->getCurrent();
        $managerMails = $observatoire ? $this->em->getRepository(Utilisateur::class)->getValidatorMails($observatoire) : [];
        try {
            $this->mailer->send(
                (new Email())
                    ->subject($this->translator->trans('security.utilisateur.newAccountMail.title'))
                    ->from('bdoh@inrae.fr')
                    ->to(...$managerMails)
                    ->text($this->twig->render('@IrsteaBdohSecurity/Utilisateur/mail.txt.twig', ['user' => $user, 'type' => 'compte']))
            );
        } catch (RfcComplianceException $e) {
            // L'adresse invalide d'un gestionnaire n'empêche pas l'utilisateur de se connecter
            $this->logger->error('Courriel aux gestionnaires non envoyé : ' . $e->getMessage());
        }
    }
}
