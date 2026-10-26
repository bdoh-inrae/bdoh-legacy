<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Irstea\BdohAdminBundle\Form\Type\FileType;
use Irstea\BdohAdminBundle\Form\Type\LinkType;
use Irstea\BdohAdminBundle\Form\Type\WysiwygType;
use Irstea\BdohDataBundle\Entity\JeuQualite;
use Irstea\BdohDataBundle\Entity\Repository\JeuQualiteRepository;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;

/**
 * Class Observatoire.
 */
class Observatoire extends Admin
{
    /**
     * @var \Irstea\BdohDataBundle\Entity\Observatoire|null
     */
    protected $previousObs = null;

    /**
     * @var bool|null
     */
    protected $isCurrentObs = null;

    /**
     * @return bool|null
     */
    public function isCurrentObs()
    {
        if ($this->isCurrentObs === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->isCurrentObs =
                $this->getSubject() === $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent();
        }

        return $this->isCurrentObs;
    }

    /**
     * Configure the ordered field.
     */
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        parent::configureDefaultSortValues($sortValues);
        $sortValues['_sort_by'] = 'nom';  // name of the ordered field
        // (default = the model's id field, if any)
    }

    /**
     * List of sites in the observatory.
     */
    private $sites = null;

    /**
     * @return array|null
     */
    public function getSites()
    {
        if ($this->sites === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->sites = $this->getSubject()->getSites();
        }

        return $this->sites;
    }

    /**
     * Number of scales linked to the observatory.
     */
    private $scales = null;

    /**
     * @return array|null
     */
    public function getScales()
    {
        if ($this->scales === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->scales = $this->getBdohRepo('Bareme')->findByObservatoire($this->getSubject());
        }

        return $this->scales;
    }

    /**
     * Number of basins linked to the observatory.
     */
    private $basins = null;

    /**
     * @return array|null
     */
    public function getBasins()
    {
        if ($this->basins === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->basins = $this->getBdohRepo('Bassin')->findByObservatoire($this->getSubject());
        }

        return $this->basins;
    }

    /**
     * Number of river linked to the observatory.
     */
    private $rivers = null;

    /**
     * @return array|null
     */
    public function getRivers()
    {
        if ($this->rivers === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->rivers = $this->getBdohRepo('CoursEau')->findByObservatoire($this->getSubject());
        }

        return $this->rivers;
    }

    /**
     * Number of DOIs linked to the observatory.
     */
    private $dois = null;

    /**
     * @return array|null
     */
    public function getDois()
    {
        if ($this->dois === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->dois = $this->getBdohRepo('Doi')->findByObservatoire($this->getSubject());
        }

        return $this->dois;
    }

    private $partenaires = null;

    /**
     * @return array|null
     */
    public function getPartenaires()
    {
        if ($this->partenaires === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->partenaires = $this->getBdohRepo('Partenaires')->findByObservatoire($this->getSubject());
        }

        return $this->partenaires;
    }

    /**
     * @var null
     */
    private $mustDefineUpdate = null;

    /**
     * @return bool
     */
    public function getMustDefineUpdate()
    {
        $subject = $this->hasSubject() ? $this->getSubject() : null;
        if ($this->mustDefineUpdate === null) {
            $this->mustDefineUpdate = $subject && $subject->getId() && $subject->getTheiaCode() === null;
        }

        return $this->mustDefineUpdate;
    }

    /**
     * Pre-update :
     *  => Moves uploaded image files to webdir.
     *
     * {@inheritdoc}
     */
    protected function preUpdate(object $object): void
    {
        /*
         * Before update, Doctrine has already persited in cache the modification
         * That's why if we want to find the slug before pre update it doesn't exist
         * Now we know there is a modification of slug if the result of the query returns null
         */

        $oldObs = $this->getBdohRepo('Observatoire')->findOneBySlug($object->getSlug());
        $this->previousObs = $oldObs;
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationObservatoire(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.ObservatoireCreate',
            $object
        );
    }

    /**
     * Post-update :
     *  => Removes 'observatoire' css file. It will be recreated on the next request.
     *  => Redirect to home.
     *
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationObservatoire(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.ObservatoireUpdate',
            $object
        );

        /* on ne redirifge sur le nouveau slug que si c'est l'observatoire courant qui a été modifié */
        if (!$this->previousObs && $this->isCurrentObs()) {
            $redirect = new RedirectResponse(
                $this->getContainer()->get('router')->generate(
                    'bdoh_admin_observatoire_edit',
                    ['id' => $object->getId(), '_observatoire' => $object->getSlug()]
                )
            );
            $redirect->send();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        /* construction / enregistrement de l'information isCurrentObs avant la suppression */
        $this->isCurrentObs();

        $this->logger->createHistoriqueAdministrationObservatoire(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.ObservatoireRemove',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postRemove(object $object): void
    {
        /* redirection sur la page d'accueil de tous les observatoires si on supprime l'observatoire courant */
        if ($this->isCurrentObs()) {
            $redirect = new RedirectResponse(
                $this->getContainer()->get('router')->generate('bdoh_all_home')
            );
            $redirect->send();
        }
    }

    /**
     * Since it uses "colopicker", "image" and "wysiwyg html editor" widgets,
     * the 'Observatoire' admin uses its own editing template.
     *
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-observatoire.html.twig');
        $this->setTemplate('delete', '@IrsteaBdohAdmin/CRUD/delete-observatoire.html.twig');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('nom')
            ->add('theiaCode')
            ->add('projectLeader')
            ->add('dataManagers')
            ->add('titre')
            ->add('partenaires')
            ->add('dataSets');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('nom', null, ['route' => ['name' => 'edit']])
            ->add('theiaCode')
            ->add('projectLeader')
            ->add('dataManagers')
            ->add('titre')
            ->add('partenaires')
            ->add('dataSets');
    }

    public function validate(ErrorElement $errorElement, $object)
    {
        $errorElement
            ->with('theiaCode')
            ->assertLength([
                'min' => 4,
                'max' => 4, ])
            ->assertRegex([
                'pattern' => '/[a-z0-9]/',
                'match'   => false,
                'message' => 'Un Identifiant Theia doit faire 4 caractères et être en MAJUSCULE !!!',
            ])
            ->end();
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $translator = $this->getContainer()->get('translator');
        $repoPersonneTheia = $this->getBdohRepo('PersonneTheia');

        $formMapper
            ->add(
                'nom',
                TextType::class,
                [
                    'required' => true,
                ]
            )
            ->add(
                'titre',
                TextType::class,
                [
                    'required'  => true,
                    'help'      => $translator->trans('admin.help.observatoire.titre-info'),
                    'help_html' => true,
                ]
            )
            ->add(
                'titreEn',
                TextType::class,
                [
                    'required' => true,
                ]
            )
            ->add('description', WysiwygType::class)
            ->add('descriptionEn', WysiwygType::class)
            ->add('email', EmailType::class, [
                'required'  => false,
                'help'      => $translator->trans('admin.help.observatoire.email-info'),
                'help_html' => true,
            ])
            // Sans protocole par défaut, le champ reste de type url, comme sous Symfony 2
            ->add('lien', LinkType::class, ['required' => false])
            ->add(
                'jeu',
                EntityType::class,
                [
                    'required'      => true,
                    'expanded'      => true,
                    'multiple'      => false,
                    'class'         => JeuQualite::class,
                    'query_builder' => function (JeuQualiteRepository $repo) {
                        return $repo->createQueryBuilder('jq')->where('jq.estAffectable = TRUE');
                    },
                    'help'          => $translator->trans('admin.help.observatoire.jeu'),
                    'help_html'     => true,
                ]
            )
            ->add(
                'doiPrincipal',
                ModelListType::class,
                [
                    'btn_add'    => false,
                    'btn_delete' => $this->listDeleteButton('irstea_bdoh_admin.admin.doi'),
                    'required'   => false,
                    'help'       => $translator->trans('admin.help.observatoire.doi-principal-message'),
                    'help_html'  => true,
                ],
                ['placeholder' => 'Pas de DOI',
                ]
            )
            ->add(
                'theiaCode',
                TextType::class,
                [
                    'required'  => false,
                    'help'      => $translator->trans('admin.help.observatoire.theiaCode-message'),
                    'help_html' => true,
                ]
            )
            ->add(
                'theiaPassword',
                PasswordType::class,
                [
                    'required'  => false,
                    'help'      => $translator->trans('admin.help.observatoire.theiaPassword-message'),
                    'help_html' => true,
                ]
            )
            ->end()
            ->with('Contact', ['class' => 'col-md-12'])
            ->add('projectLeader', ModelType::class, [
                'query'    => $repoPersonneTheia->createQueryBuilder('PersonneTheia'),
                'required' => true,
                'multiple' => false,
                'btn_add'  => $translator->trans('button.add'),
            ])
            ->add('dataManagers', ModelType::class, [
                'query'     => $repoPersonneTheia->createQueryBuilder('PersonneTheia'),
                'required'  => false,
                'multiple'  => true,
                'btn_add'   => $translator->trans('button.add'),
                'help'      => $translator->trans('admin.help.astuce'),
                'help_html' => true,
            ])
            ->end()
            ->with('fieldset.graphic', ['collapsed' => true])
            // colorpicker 1st widget
            ->add('couleurPrimaire', TextType::class, ['required' => true], ['block_name' => 'couleur'])
            // colorpicker 2nd widget
            ->add('couleurSecondaire', TextType::class, ['required' => true], ['block_name' => 'couleur'])
            // image widget
            ->add(
                'pathLogo',
                FileType::class,
                ['required' => false, 'accept' => ['image/*', '.png', '.jpg', '.gif'], 'label' => 'fileLogo']
            )
            // image widget
            ->add(
                'pathPhotoPrincipale',
                FileType::class,
                ['required' => false, 'accept' => ['image/*', '.png', '.jpg', '.gif'], 'label' => 'filePhotoPrincipale']
            )
            // image widget
            ->add(
                'pathPhotoSecondaire',
                FileType::class,
                ['required' => false, 'accept' => ['image/*', '.png', '.jpg', '.gif'], 'label' => 'filePhotoSecondaire']
            )
            ->end();

        // Met en place une gestion spéciale pour le mot de passe
        $formMapper->get('theiaPassword')
            ->addEventSubscriber($this->getContainer()->get('irstea_bdoh_theia_ozcar.password_event_subscriber'));
    }
}
