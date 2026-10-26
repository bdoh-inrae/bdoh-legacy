<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Irstea\BdohSecurityBundle\Form\ResettingUtilisateurType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/resetting')]
class ResettingController extends Controller
{
    const SESSION_EMAIL = 'resetting_email/email';

    public function __construct(
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $hasher
    ) {
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/request', name: 'resetting_request', methods: ['GET'])]
    public function requestAction()
    {
        return $this->render('@IrsteaBdohSecurity/Resetting/request.html.twig');
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    #[Route('/send-email', name: 'resetting_send_email', methods: ['POST'])]
    public function sendEmailAction(Request $request)
    {
        $email = $request->request->get('email');

        /** @var $user UserInterface */
        $user = $this->getSecurityRepo('Utilisateur')->findOneByEmail($email);
        if (null === $user) {
            $this->getSession()->getFlashBag()->add(
                'error',
                $this->container->get('translator')->trans('resetting.request.invalid_email', ['%email%' => $email])
            );

            return $this->render('@IrsteaBdohSecurity/Resetting/request.html.twig');
        }

        if (null === $user->getConfirmationToken()) {
            $user->setConfirmationToken($this->generateToken());
        }
        $this->getSession()->set(static::SESSION_EMAIL, $this->getObfuscatedEmail($user));
        $this->sendResettingEmailMessage($user);
        $this->getSecurityRepo('Utilisateur')->updateUser($user);

        return new RedirectResponse($this->container->get('router')->generate('resetting_check_email'));
    }

    /**
     * @param Request $request
     * @param $token
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    #[Route('/reset/{token}', name: 'resetting_reset', methods: ['GET', 'POST'])]
    public function resetAction(Request $request, $token)
    {
        $user = $this->getSecurityRepo('Utilisateur')->findOneByConfirmationToken($token);

        if (null === $user) {
            throw new NotFoundHttpException(sprintf('The user with "confirmation token" does not exist for value "%s"', $token));
        }

        $form = $this->createForm(ResettingUtilisateurType::class, $user);
        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $user->setConfirmationToken(null);
                $user->setPassword($this->hasher->hashPassword($user, $user->getPassword()));

                $this->getSecurityRepo('Utilisateur')->updateUser($user);
                $url = $this->container->get('router')->generate('bdoh_security_login');
                $this->getSession()->getFlashBag()->add('success', $this->get('translator')->trans('resetting.isCompleted'));

                return new RedirectResponse($url);
            }
        }

        return $this->render(
            '@IrsteaBdohSecurity/Resetting/reset.html.twig',
            [
                'token' => $token,
                'form'  => $form->createView(),
            ]
        );
    }

    /**
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    #[Route('/check-email', name: 'resetting_check_email', methods: ['GET'])]
    public function checkEmailAction()
    {
        $session = $this->getSession();
        $email = $session->get(static::SESSION_EMAIL);
        $session->remove(static::SESSION_EMAIL);

        if (empty($email)) {
            // the user does not come from the sendEmail action
            return new RedirectResponse($this->container->get('router')->generate('resetting_request'));
        }

        return $this->render(
            '@IrsteaBdohSecurity/Resetting/checkEmail.html.twig',
            ['email' => $email]
        );
    }

    protected function getObfuscatedEmail(Utilisateur $user)
    {
        $email = $user->getEmail();
        if (false !== $pos = \strpos($email, '@')) {
            $email = '...' . substr($email, $pos);
        }

        return $email;
    }

    public function generateToken()
    {
        return base_convert(bin2hex($this->getRandomNumber()), 16, 36);
    }

    private function getRandomNumber()
    {
        return hash('sha256', uniqid(mt_rand(), true), true);
    }

    public function sendResettingEmailMessage(Utilisateur $user)
    {
        $url = $this->get('router')->generate('resetting_reset', ['token' => $user->getConfirmationToken()], UrlGeneratorInterface::ABSOLUTE_URL);
        $rendered = $this->renderView(
            '@IrsteaBdohSecurity/Resetting/email.txt.twig',
            [
                'user'            => $user,
                'confirmationUrl' => $url,
            ]
        );
        $this->sendEmailMessage($rendered, $user->getEmail());
    }

    /**
     * @param string $renderedTemplate
     * @param string $toEmail
     */
    protected function sendEmailMessage($renderedTemplate, $toEmail)
    {
        // La première ligne du gabarit pour sujet, le reste pour corps. L'ancien convertissait en
        // texte la réponse entière, en-têtes HTTP compris, et y prenait la cinquième ligne.
        $renderedLines = explode("\n", trim($renderedTemplate));
        $subject = $renderedLines[0];
        $body = implode("\n", array_slice($renderedLines, 1));

        $message = (new Email())
            ->subject($subject)
            ->from('bdoh@inrae.fr')
            ->to($toEmail)
            ->text($body);

        $this->mailer->send($message);
    }
}
