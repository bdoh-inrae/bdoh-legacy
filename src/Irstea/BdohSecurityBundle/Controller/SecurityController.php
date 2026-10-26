<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Irstea\BdohBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
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
     * même dans tous les cas, pour ne pas dire quelles adresses ont un compte ; les envois sont
     * limités par adresse IP et par adresse e-mail.
     */
    #[Route('/login', name: 'bdoh_security_login_send', methods: ['POST'])]
    public function sendLoginLinkAction(
        Request $request,
        LoginLinkHandlerInterface $loginLinkHandler,
        MailerInterface $mailer,
        RateLimiterFactoryInterface $loginLinkIpLimiter,
        RateLimiterFactoryInterface $loginLinkEmailLimiter
    ) {
        $email = trim((string) $request->request->get('email'));
        $translator = $this->get('translator');
        $request->getSession()->set(CoreSecurity::LAST_USERNAME, $email);

        if (!$loginLinkIpLimiter->create($request->getClientIp())->consume()->isAccepted()
            || !$loginLinkEmailLimiter->create(mb_strtolower($email))->consume()->isAccepted()) {
            $this->getSession()->getFlashBag()->add('error', $translator->trans('security.tooManyRequests'));

            return $this->redirectToRoute('bdoh_security_login');
        }

        $user = '' === $email ? null : $this->getSecurityRepo('Utilisateur')->findOneByEmail($email);
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

        $this->getSession()->getFlashBag()->add('success', $translator->trans('security.login_link.sent(%email%)', ['%email%' => $email]));

        return $this->redirectToRoute('bdoh_security_login');
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
