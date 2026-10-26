<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohSecurityBundle\Controller;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Mailer\DeferredMailer;
use Irstea\BdohSecurityBundle\Entity\Besoin;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Irstea\BdohSecurityBundle\Form\BesoinType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;

#[Route('/besoin')]
class BesoinController extends Controller
{
    public function __construct(private DeferredMailer $mailer)
    {
    }

    /**
     * Creates a new 'Besoin' entity.
     */
    #[Route('/new', name: 'bdoh_security_besoin_new', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_USER')"))]
    public function newAction(Request $request)
    {
        /*
         * @var Utilisateur $user
         */
        $user = $this->get('security.token_storage')->getToken()->getUser();

        // si l'utilisateur a déjà accès à tout on le redirige sur la page de recherche avancée
        if ($user->isATrustedUserOfCurrentObservatory() || $this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('bdoh_consult_advancedSearch');
        }

        $besoin = new Besoin();
        $besoin->setObservatoire($this->get('irstea_bdoh.manager.observatoire')->getCurrent());
        $besoin->setUtilisateur($user);

        $form = $this->createForm(BesoinType::class, $besoin, ['locale' => $this->get('session')->get('_locale') ?? 'fr']);

        // Some POST parameters ? Checks the form.
        if (count($request->request) !== 0) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Persists the new 'Besoin'
                $em = $this->getEm();
                $em->persist($besoin);
                $em->flush();

                // Send a mail to the managers of the observatory
                $userRepo = $this->getSecurityRepo('Utilisateur');
                $validatorMails = $userRepo->getValidatorMails($this->container->get('irstea_bdoh.manager.observatoire')->getCurrent());
                $message = (new Email())
                    ->subject($this->get('translator')->trans('security.utilisateur.newNeedMail.title'))
                    ->from('bdoh@inrae.fr')
                    ->to(...$validatorMails)
                    ->text(
                        $this->renderView(
                            '@IrsteaBdohSecurity/Utilisateur/mail.txt.twig',
                            ['user' => $user, 'type' => 'besoin']
                        )
                    );

                // Après la réponse, et pas sans destinataire (DeferredMailer)
                $this->mailer->send($message);

                // Redirects to home, with a success message
                $this->getSession()->getFlashBag()->add('success', $this->get('translator')->trans('security.besoin.create.isCompleted'));

                return $this->redirect($this->generateUrl('bdoh_home'));
            }
        }

        return $this->render('@IrsteaBdohSecurity/Besoin/new.html.twig', ['form' => $form->createView()]);
    }
}
