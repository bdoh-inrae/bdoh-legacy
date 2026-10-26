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
use Irstea\BdohSecurityBundle\Entity\Besoin;
use Irstea\BdohSecurityBundle\Entity\Observatoire;
use Irstea\BdohSecurityBundle\Entity\Role;
use Irstea\BdohSecurityBundle\Entity\RoleChronique;
use Irstea\BdohSecurityBundle\Entity\RoleObservatoire;
use Irstea\BdohSecurityBundle\Entity\RoleSite;
use Irstea\BdohSecurityBundle\Entity\Utilisateur;
use Irstea\BdohSecurityBundle\Form\EditUtilisateurType;
use Irstea\BdohSecurityBundle\Form\NewUtilisateurType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * This controller allows :
 *      => anonymous to create a new account ;
 *      => existing User to edit its account .
 */
#[Route('/user')]
class UtilisateurController extends Controller
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    /**
     * Envoie un courriel s'il a un destinataire : Swiftmailer n'envoyait rien, sans erreur, à une
     * liste vide (aucun gestionnaire), quand Symfony Mailer lèverait une exception.
     */
    private function send(Email $message): void
    {
        if ($message->getTo() || $message->getCc()) {
            $this->mailer->send($message);
        }
    }
    /**
     * Creates a new 'Utilisateur' entity.
     * This action needs no role !
     *
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/new', name: 'bdoh_security_utilisateur_new', methods: ['GET', 'POST'])]
    public function newAction(Request $request, LoginLinkHandlerInterface $loginLinkHandler, RateLimiterFactoryInterface $signupIpLimiter)
    {
        // si l'utilisateur est déjà identifié on le redirige sur la page d'édition de son compte
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('bdoh_security_utilisateur_edit');
        }

        $user = new Utilisateur();
        $form = $this->createForm(NewUtilisateurType::class, $user, ['locale' => $this->get('session')->get('_locale') ?? 'fr']);

        // Some POST parameters ? Checks the form.
        if (count($request->request) !== 0) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Chaque inscription envoie un courriel à l'adresse saisie : en nombre limité par adresse IP
                if (!$signupIpLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                    $this->getSession()->getFlashBag()->add('error', $this->get('translator')->trans('security.tooManyRequests'));

                    return $this->render('@IrsteaBdohSecurity/Utilisateur/new.html.twig', ['form' => $form->createView()], new Response('', 429));
                }

                // Pas de mot de passe : on se connecte par un lien (la colonne n'accepte pas NULL)
                $user->setPassword('');
                $user->setSalt('');

                // Persists the new user
                $em = $this->getEm();
                $em->persist($user);
                $em->flush();

                // Le courriel de bienvenue porte le premier lien de connexion, qui confirme l'adresse (créé sans
                // la requête, qui ferait perdre au routeur l'observatoire courant). Les gestionnaires de
                // l'observatoire sont prévenus à la première connexion (BdohAuthenticationHandler).
                $messageUtilisateur = (new Email())
                    ->subject($this->get('translator')->trans('security.utilisateur.newMailUtilisateur.title'))
                    ->from('bdoh@inrae.fr')
                    ->to($user->getEmail())
                    ->html(
                        $this->renderView(
                            '@IrsteaBdohSecurity/Utilisateur/mailNewUtilisateur.txt.twig',
                            ['user' => $user, 'loginLink' => $loginLinkHandler->createLoginLink($user)->getUrl()]
                        )
                    );

                $this->send($messageUtilisateur);

                $this->container->get('irstea_bdoh_logger.logger')
                    ->createHistoriqueAdministrationUtilisateur(
                        $user,
                        new \DateTime(),
                        $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent(),
                        'historique.actions.UtilisateurCreate',
                        $user
                    );

                // Redirects to login form, with a success message
                $this->getSession()->getFlashBag()->add('success', $this->get('translator')->trans('security.utilisateur.create.isCompleted'));

                return $this->redirect($this->generateUrl('bdoh_security_login'));
            }
        }

        return $this->render('@IrsteaBdohSecurity/Utilisateur/new.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Edits an existing Utilisateur entity.
     *
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    #[Route('/edit', name: 'bdoh_security_utilisateur_edit', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_USER')"))]
    public function editAction(Request $request)
    {
        $user = $this->get('security.token_storage')->getToken()->getUser();
        $form = $this->createForm(EditUtilisateurType::class, $user, ['locale' => $this->get('session')->get('_locale') ?? 'fr']);

        // Some POST parameters ? Checks the form.
        if (count($request->request) !== 0) {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Persists the new user
                $em = $this->getEm();
                $em->flush();

                $this->container->get('irstea_bdoh_logger.logger')
                    ->createHistoriqueAdministrationUtilisateur(
                        $user,
                        new \DateTime(),
                        $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent(),
                        'historique.actions.UtilisateurUpdate',
                        $user
                    );

                // Redirects to home, with a success message
                $this->getSession()->getFlashBag()->add('success', $this->get('translator')->trans('security.utilisateur.edit.isCompleted'));

                return $this->redirect($this->generateUrl('bdoh_home'));
            }
        }

        return $this->render('@IrsteaBdohSecurity/Utilisateur/edit.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Edits an existing Utilisateur rights.
     *
     *
     * @param Request $request
     * @param mixed   $email
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    #[Route('/right/{email}', name: 'bdoh_security_utilisateur_right', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') and (user.isGestionnaireDesObservatoires() or user.isAManagerOfCurrentObservatory() or user.isASiteManagerOfCurrentObservatory())"))]
    public function rightAction(Request $request, $email)
    {
        $em = $this->getEm();
        $userRepo = $this->getSecurityRepo('Utilisateur');
        /** @var Utilisateur $user */
        $user = $userRepo->findOneByEmail($email);

        if ($user === null) {
            return $this->allAction();
        }

        $obs = $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent();
        $security = $this->get('security.authorization_checker');
        $actingAdmin = $this->get('security.token_storage')->getToken()->getUser();

        $sitesOnWhichUserIsGestionnaire = [];
        $sitesOnWhichUserIsContributeur = [];
        $chroniquesOnWhichUserIsUtilisateur = [];
        $sitesChoice = [];
        $chroniquesChoice = [];

        $besoinsATraiter = [];
        $besoinsTraite = [];
        $besoins = [];

        /* @var Besoin $besoin */
        if ($security->isGranted('RIGHT', [Role::UTILISATEUR, $obs])) {
            $besoins = $user->getBesoins();
            foreach ($besoins as $besoin) {
                if ($besoin !== null) {
                    if ($besoin->getObservatoire() === $obs) {
                        if ($besoin->getTraite()) {
                            $besoinsTraite[] = $besoin;
                        } else {
                            $besoinsATraiter[] = $besoin;
                        }
                    }
                }
            }
        }

        $sites = $this->getBdohRepo('SiteExperimental')->securedFindAll($actingAdmin);
        if ($sites) {
            foreach ($sites as $site) {
                $sitesChoice[$site->getId()] = $site->getNom();
                //retrieving of existing roles
                if ($user->hasRoleSite(Role::GESTIONNAIRE, $site)) {
                    $sitesOnWhichUserIsGestionnaire[] = $site->getId();
                }
                if ($user->hasRoleSite(Role::CONTRIBUTEUR, $site)) {
                    $sitesOnWhichUserIsContributeur[] = $site->getId();
                }
            }
            $site = $sites[0];
        } else {
            $site = null;
        }

        $chroniques = $this->getBdohRepo('Chronique')->findAll();
        if ($chroniques) {
            foreach ($chroniques as $chron) {
                $chroniqueTxt = $chron->__toString();
                if (!$chron->getEstVisible()) {
                    $chroniqueTxt .= ' (invisible)';
                }
                $chroniquesChoice[$chron->getId()] = $chroniqueTxt;

                if ($user->hasRoleChronique(Role::UTILISATEUR, $chron)) {
                    $chroniquesOnWhichUserIsUtilisateur[] = $chron->getId();
                }
            }
            $chronique = $chroniques[0];
        } else {
            $chronique = null;
        }

        //Retrieving of existing roles
        $isGestionnaireDesObservatoires = $user->getRoleAdmin() !== null;
        $isGestionnaireObservatoire = ($user->hasRoleObservatoire(Role::GESTIONNAIRE, $obs)) ? true : false;
        $isUtilisateur = ($user->hasRoleObservatoire(Role::UTILISATEUR, $obs)) ? true : false;
        $existingDateFin = $user->getFinAcces();

        $defaultData = ['dateFin' => $existingDateFin];
        if ($security->isGranted('RIGHT', [Role::UTILISATEUR, $obs])) {
            if ($besoins !== null) {
                foreach ($besoins as $besoin) {
                    if ($besoin->getObservatoire() === $obs) {
                        if ($besoin->getTraite()) {
                            $defaultData['besoinTraite' . $besoin->getId()] = true;
                        } else {
                            $defaultData['besoinATraiter' . $besoin->getId()] = false;
                        }
                    }
                }
            }
        }
        if ($security->isGranted('RIGHT', [Role::GESTIONNAIRE, $obs])) {
            $defaultData['gestionnaireDesObservatoires'] = $isGestionnaireDesObservatoires;
            $defaultData['gestionnaireObservatoire'] = $isGestionnaireObservatoire;
        }
        if (($site !== null) && $security->isGranted('RIGHT', [Role::GESTIONNAIRE, $site])) {
            $defaultData['gestionnaireSite'] = $sitesOnWhichUserIsGestionnaire;
        }
        if (($site !== null) && $security->isGranted('RIGHT', [Role::CONTRIBUTEUR, $site])) {
            $defaultData['contributeurSite'] = $sitesOnWhichUserIsContributeur;
        }
        if ($security->isGranted('RIGHT', [Role::UTILISATEUR, $obs])) {
            $defaultData['utilisateurObservatoire'] = $isUtilisateur;
        }
        if (($chronique !== null) && $security->isGranted('RIGHT', [Role::UTILISATEUR, $chronique])) {
            $defaultData['utilisateurChronique'] = $chroniquesOnWhichUserIsUtilisateur;
        }

        $formBuilder = $this->createFormBuilder($defaultData);
        if ($security->isGranted('RIGHT', [Role::UTILISATEUR, $obs])) { // gestionnaire de l'observatoire
            if ($besoins !== null) {
                foreach ($besoinsATraiter as $besoinATraiter) {
                    $formBuilder->add(
                        'besoinATraiter' . $besoinATraiter->getId(),
                        CheckboxType::class,
                        [
                            'required' => false,
                            'label'    => 'besoin.traite',
                        ]
                    );
                }
            }
        }
        if ($security->isGranted('RIGHT', [Role::GESTIONNAIRE, $obs]) ||  // gestionnaire des tous les observatoires
            $security->isGranted('RIGHT', [Role::GESTIONNAIRE, $site]) || // gestionnaire de l'observatoire
            $security->isGranted('RIGHT', [Role::CONTRIBUTEUR, $site])) { // gestionnaire de site
            $formBuilder->add(
                'dateFin',
                DateType::class,
                [
                    'required' => false,
                    'label'    => 'finAcces',
                    // Trois listes (jour, mois, année), comme l'ancien ; Symfony 7 en ferait un seul champ
                    'widget'      => 'choice',
                    'placeholder' => '',
                ]
            );
        }
        if ($security->isGranted('RIGHT', [Role::GESTIONNAIRE, $obs])) {
            $formBuilder->add(
                'gestionnaireDesObservatoires',
                CheckboxType::class,
                [
                    'required' => false,
                    'label'    => 'security.utilisateur.gestionnaireDesObservatoires',
                    'disabled' => $user->getRoleAdmin() === Utilisateur::ROLE_ADMIN_TECH,
                ]
            );
            $formBuilder->add(
                'gestionnaireObservatoire',
                CheckboxType::class,
                [
                    'required' => false,
                    'label'    => 'security.utilisateur.gestionnaireObservatoire',
                ]
            );
        }
        if (($site !== null) && $security->isGranted('RIGHT', [Role::GESTIONNAIRE, $site])) {
            $formBuilder->add(
                'gestionnaireSite',
                ChoiceType::class,
                [
                    'required' => false,
                    'label'    => 'security.utilisateur.gestionnaireSite',
                    'multiple'     => true,
                    // Les identifiants pour valeurs, les noms pour libellés, comme l'ancien les écrivait
                    'choices'      => array_keys($sitesChoice),
                    'choice_label' => fn ($id) => $sitesChoice[$id],
                ]
            );
        }
        if (($site !== null) && $security->isGranted('RIGHT', [Role::CONTRIBUTEUR, $site])) {
            $formBuilder->add(
                'contributeurSite',
                ChoiceType::class,
                [
                    'required' => false,
                    'label'    => 'security.utilisateur.contributeurSite',
                    'multiple'     => true,
                    // Les identifiants pour valeurs, les noms pour libellés, comme l'ancien les écrivait
                    'choices'      => array_keys($sitesChoice),
                    'choice_label' => fn ($id) => $sitesChoice[$id],
                ]
            );
        }
        if ($security->isGranted('RIGHT', [Role::UTILISATEUR, $obs])) {
            $formBuilder->add(
                'utilisateurObservatoire',
                CheckboxType::class,
                [
                    'required' => false,
                    'label'    => 'security.utilisateur.utilisateurObservatoire',
                ]
            );
        }
        if (($chronique !== null) && $security->isGranted('RIGHT', [Role::UTILISATEUR, $chronique])) {
            $formBuilder->add(
                'utilisateurChronique',
                ChoiceType::class,
                [
                    'required'   => false,
                    'label'      => 'security.utilisateur.utilisateurChronique',
                    'multiple'     => true,
                    'choices'      => array_keys($chroniquesChoice),
                    'choice_label' => fn ($id) => $chroniquesChoice[$id],
                    'block_name'   => 'choice_attr',
                    'group_by'   => function ($choiceValue, $key, $value) {
                        return $this->getBdohRepo('Chronique')->findOneById($choiceValue)->getStation()->getCode();
                    },
                ]
            );
        }

        $form = $formBuilder->getForm();

        if ($request->isMethod('POST')) { //count($request->request) !== 0) //Processing
            //A little boring :
            //rights should be granted according to what is on the form
            //AND removed if not in the form
            //BUT ONLY for rights on this observatoire...

            //so basically we're going to go through all sites and chroniques

            $form->handleRequest($request);
            $data = $form->getData();
            $besoinsATraiter = preg_grep('#besoinATraiter#', array_keys($data));
            if ($besoinsATraiter) {
                foreach ($besoinsATraiter as $besoinATraiter) {
                    $besoin = $this->getSecurityRepo('Besoin')->findOneById((int) \substr($besoinATraiter, \strlen('besoinATraiter')));
                    $besoin->setTraite($data[$besoinATraiter]);
                    $em->persist($besoin);
                }
            }
            $user->setFinAcces($data['dateFin']);

            if (array_key_exists('gestionnaireDesObservatoires', $data)) {
                $user->setGestionnaireDesObservatoires($data['gestionnaireDesObservatoires']);
            } else {
                $data['gestionnaireDesObservatoires'] = false; // utilisé dans le mail des droits
            }

            if (array_key_exists('gestionnaireObservatoire', $data)) {
                if ($data['gestionnaireObservatoire']) {
                    if (!$user->hasRoleObservatoire(Role::GESTIONNAIRE, $obs)) {
                        $role = new RoleObservatoire();
                        $role->setUtilisateur($user);
                        $role->setValeur(Role::GESTIONNAIRE);
                        $role->setObservatoire($obs);
                        $user->addRoleObservatoire($role);
                        $em->persist($role);
                    }
                } elseif ($user->hasRoleObservatoire(Role::GESTIONNAIRE, $obs)) {
                    $role = $this->getSecurityRepo('RoleObservatoire')->findOneBy(
                        ['utilisateur' => $user, 'observatoire' => $obs, 'valeur' => Role::GESTIONNAIRE]
                    );
                    $em->remove($role);
                    $user->removeRoleObservatoire($role);
                }
            }

            if (array_key_exists('utilisateurObservatoire', $data)) {
                if ($data['utilisateurObservatoire']) {
                    if (!$user->hasRoleObservatoire(Role::UTILISATEUR, $obs)) {
                        $role = new RoleObservatoire();
                        $role->setUtilisateur($user);
                        $role->setValeur(Role::UTILISATEUR);
                        $role->setObservatoire($obs);
                        $user->addRoleObservatoire($role);
                        $em->persist($role);
                    }
                } elseif ($user->hasRoleObservatoire(Role::UTILISATEUR, $obs)) {
                    $role = $this->getSecurityRepo('RoleObservatoire')->findOneBy(
                        ['utilisateur' => $user, 'observatoire' => $obs, 'valeur' => Role::UTILISATEUR]
                    );
                    $em->remove($role);
                    $user->removeRoleObservatoire($role);
                }
            }

            foreach ($sites as $site) {
                //GESTIONNAIRE
                if (array_key_exists('gestionnaireSite', $data)) {
                    if ($user->hasRoleSite(Role::GESTIONNAIRE, $site) && !in_array($site->getId(), $data['gestionnaireSite'])) {
                        //right should be removed
                        $role = $this->getSecurityRepo('RoleSite')->findOneBy(
                            ['utilisateur' => $user, 'site' => $site, 'valeur' => Role::GESTIONNAIRE]
                        );
                        $em->remove($role);
                        $user->removeRoleSite($role);
                    }
                    if (!$user->hasRoleSite(Role::GESTIONNAIRE, $site) && in_array($site->getId(), $data['gestionnaireSite'])) {
                        //right should be added
                        $role = new RoleSite();
                        $role->setUtilisateur($user);
                        $role->setValeur(Role::GESTIONNAIRE);
                        $role->setSite($site);
                        $user->addRoleSite($role);
                        $em->persist($role);
                    }
                }

                //CONTRIBUTEUR
                if (array_key_exists('contributeurSite', $data)) {
                    if ($user->hasRoleSite(Role::CONTRIBUTEUR, $site) && !in_array($site->getId(), $data['contributeurSite'])) {
                        //right should be removed
                        $role = $this->getSecurityRepo('RoleSite')->findOneBy(
                            ['utilisateur' => $user, 'site' => $site, 'valeur' => Role::CONTRIBUTEUR]
                        );
                        $em->remove($role);
                        $user->removeRoleSite($role);
                    }
                    if (!$user->hasRoleSite(Role::CONTRIBUTEUR, $site) && in_array($site->getId(), $data['contributeurSite'])) {
                        //right should be added
                        $role = new RoleSite();
                        $role->setUtilisateur($user);
                        $role->setValeur(Role::CONTRIBUTEUR);
                        $role->setSite($site);
                        $user->addRoleSite($role);
                        $em->persist($role);
                    }
                }
            }

            foreach ($chroniques as $chron) {
                if (array_key_exists('utilisateurChronique', $data)) {
                    if ($user->hasRoleChronique(Role::UTILISATEUR, $chron) && !in_array($chron->getId(), $data['utilisateurChronique'])) {
                        //right should be removed
                        $role = $this->getSecurityRepo('RoleChronique')->findOneBy(
                            ['utilisateur' => $user, 'chronique' => $chron, 'valeur' => Role::UTILISATEUR]
                        );
                        $em->remove($role);
                        $user->removeRoleChronique($role);
                    }
                    if (!$user->hasRoleChronique(Role::UTILISATEUR, $chron) && in_array($chron->getId(), $data['utilisateurChronique'])) {
                        //right should be added
                        $role = new RoleChronique();
                        $role->setUtilisateur($user);
                        $role->setValeur(Role::UTILISATEUR);
                        $role->setChronique($chron);
                        $user->addRoleChronique($role);
                        $em->persist($role);
                    }
                }
            }

            //flush if necessary
            $em->flush();

            // Send a mail to the user to inform her of right granting
            // and send a copy to the managers of the observatory
            $managerMails = $userRepo->getValidatorMails($obs);
            $message = (new Email())
                ->subject($this->get('translator')->trans('security.utilisateur.newRightMail.title'))
                ->from('bdoh@inrae.fr')
                ->to($email)
                ->cc(...$managerMails)
                ->text(
                    $this->renderView(
                        '@IrsteaBdohSecurity/Utilisateur/rightMail.txt.twig',
                        [
                            'user'                         => $user,
                            'obs'                          => $obs,
                            'gestionnaireDesObservatoires' => $data['gestionnaireDesObservatoires'],
                            'roleGestionnaire'             => Role::GESTIONNAIRE,
                            'roleContributeur'             => Role::CONTRIBUTEUR,
                            'roleUtilisateur'              => Role::UTILISATEUR,
                            'chroniques'                   => $chroniques,
                        ]
                    )
                );

            $this->send($message);

            //log the rights granting
            $this->get('irstea_bdoh_logger.logger')
                ->createHistoriqueAdministrationUtilisateur(
                    $this->getUser(),
                    new \DateTime(),
                    $obs,
                    'historique.actions.UtilisateurRights',
                    $user
                );

            // Redirects to home, with a success message
            $this->getSession()->getFlashBag()->add('success', $this->get('translator')->trans('security.utilisateur.right.isCompleted'));

            return $this->redirect($this->generateUrl('bdoh_security_utilisateur_all'));
        }   //Displaying

        return $this->render(
            '@IrsteaBdohSecurity/Utilisateur/right.html.twig',
            [
                'form'            => $form->createView(),
                'utilisateur'     => $user,
                'besoinsTraite'   => $besoinsTraite,
                'besoinsATraiter' => $besoinsATraiter,
            ]
        );
    }

    /**
     * Edits an existing Utilisateur entity.
     */
    #[Route('/all', name: 'bdoh_security_utilisateur_all', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') and (user.isGestionnaireDesObservatoires() or user.isAManagerOfCurrentObservatory() or user.isASiteManagerOfCurrentObservatory())"))]
    public function allAction()
    {
        $obs = $this->container->get('irstea_bdoh.manager.observatoire')->getCurrent();
        list($users, $autres) = $this->getSecurityRepo('Utilisateur')->findUsersRelatedToTheObservatory($obs);

        return $this->render(
            '@IrsteaBdohSecurity/Utilisateur/all.html.twig',
            [
                'obs'     => $obs,
                'users'   => $users,
                'autres'  => $autres,
                'filters' => json_encode($this->getRequest()->query->all()),
            ]
        );
    }
}
