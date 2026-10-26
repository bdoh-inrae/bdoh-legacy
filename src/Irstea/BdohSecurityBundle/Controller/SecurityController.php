<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Mailer\DeferredMailer;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes as CoreSecurity;

/**
 * La connexion, par un lien envoyé par courriel, sans mot de passe (dev/decisions.md) : la page de
 * connexion demande l'adresse et y envoie un lien ; le lien ouvre une page dont le bouton connecte.
 * Le pare-feu (login_link de config/packages/security.yaml) vérifie le lien quand on l'envoie.
 */
class SecurityController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/login', name: 'bdoh_security_login', methods: ['GET'])]
    public function loginAction(Request $request)
    {
        // si l'utilisateur est déjà identifié on le redirige sur l'accueil de l'observatoire
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('bdoh_home');
        }

        $session = $request->getSession();

        // Un lien refusé (expiré, déjà servi) : le message du pare-feu
        if ($request->attributes->has(CoreSecurity::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(CoreSecurity::AUTHENTICATION_ERROR);
        } else {
            $error = $session->get(CoreSecurity::AUTHENTICATION_ERROR);
            $session->remove(CoreSecurity::AUTHENTICATION_ERROR);
        }

        return $this->render(
            '@IrsteaBdohSecurity/Security/login.html.twig',
            [
                'last_username' => $session->get(CoreSecurity::LAST_USERNAME),
                'error'         => $error,
            ]
        );
    }

    /**
     * Envoie un lien de connexion à l'adresse saisie, si un compte y correspond. La réponse est la
     * même dans tous les cas, pour ne pas dire quelles adresses ont un compte, et le courriel part
     * après elle (DeferredMailer). Les envois sont limités par adresse IP, et par adresse e-mail
     * depuis une même adresse IP : une limite par adresse e-mail seule laisserait n'importe qui
     * empêcher un compte de se connecter.
     */
    #[Route('/login', name: 'bdoh_security_login_send', methods: ['POST'])]
    public function sendLoginLinkAction(
        Request $request,
        LoginLinkHandlerInterface $loginLinkHandler,
        DeferredMailer $mailer,
        RateLimiterFactoryInterface $loginLinkIpLimiter,
        RateLimiterFactoryInterface $loginLinkEmailLimiter
    ) {
        $email = trim((string) $request->request->get('email'));
        $translator = $this->get('translator');
        $flashes = $this->getSession()->getFlashBag();
        $request->getSession()->set(CoreSecurity::LAST_USERNAME, $email);

        if (!$this->isCsrfTokenValid('login_link', (string) $request->request->get('_csrf_token'))) {
            $flashes->add('error', $translator->trans('security.login_link.invalidForm'));

            return $this->redirectToRoute('bdoh_security_login');
        }

        $ip = (string) $request->getClientIp();
        if (!$loginLinkIpLimiter->create($ip)->consume()->isAccepted()
            || !$loginLinkEmailLimiter->create(mb_strtolower($email) . '|' . $ip)->consume()->isAccepted()) {
            $flashes->add('error', $translator->trans('security.tooManyRequests'));

            return $this->redirectToRoute('bdoh_security_login');
        }

        $user = $this->findUser($email);
        if (null !== $user) {
            // Sans la requête, qui ferait perdre au routeur l'observatoire courant
            $link = $loginLinkHandler->createLoginLink($user);
            $mailer->send(
                (new Email())
                    ->subject($translator->trans('security.login_link.mail.title'))
                    ->from('bdoh@inrae.fr')
                    ->to($user->getEmail())
                    ->text($this->renderView('@IrsteaBdohSecurity/Security/loginLinkMail.txt.twig', [
                        'user' => $user,
                        'link' => $link->getUrl(),
                    ]))
            );
        }

        // L'adresse est échappée : les messages s'affichent en HTML (toastr)
        $flashes->add('success', $translator->trans('security.login_link.sent(%email%)', ['%email%' => htmlspecialchars($email)]));

        return $this->redirectToRoute('bdoh_security_login');
    }

    /**
     * Le compte d'une adresse, à la casse près si elle n'en désigne qu'un : l'adresse saisie ne
     * s'écrit pas toujours comme à l'inscription.
     */
    private function findUser(string $email): ?Utilisateur
    {
        if ('' === $email) {
            return null;
        }
        $repository = $this->getSecurityRepo('Utilisateur');
        $users = $repository->findBy(['email' => $email]) ?: $repository->createQueryBuilder('u')
            ->where('LOWER(u.email) = LOWER(:email)')
            ->setParameter('email', $email)
            ->getQuery()
            ->getResult();

        return 1 === count($users) ? $users[0] : null;
    }

    /**
     * La page qu'ouvre le lien : son bouton envoie le lien au pare-feu, qui connecte. Une messagerie
     * qui ouvre le lien pour l'analyser ne fait qu'afficher cette page, sans le consommer. La route
     * accepte POST pour que le routeur, qui passe avant le pare-feu, laisse passer l'envoi du bouton.
     */
    #[Route('/login_check', name: 'bdoh_security_login_check', methods: ['GET', 'POST'])]
    public function checkLoginLinkAction(Request $request)
    {
        $link = [
            'user'    => (string) $request->query->get('user'),
            'expires' => (string) $request->query->get('expires'),
            'hash'    => (string) $request->query->get('hash'),
        ];
        if (in_array('', $link, true)) {
            return $this->redirectToRoute('bdoh_security_login');
        }

        return $this->render('@IrsteaBdohSecurity/Security/login-check.html.twig', ['link' => $link]);
    }
}
