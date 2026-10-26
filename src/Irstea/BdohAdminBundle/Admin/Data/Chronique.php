<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Parameter;
use Irstea\BdohAdminBundle\Admin\Admin;
use Irstea\BdohAdminBundle\Form\Type\PreciseNumberType;
use Irstea\BdohDataBundle\Entity\ChroniqueContinue;
use Irstea\BdohDataBundle\Entity\Qualite;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\Form\Validator\ErrorElement;
use Irstea\BdohAdminBundle\Filter\DateFilter;
use Sonata\AdminBundle\Form\Type\ModelType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class Chronique.
 */
class Chronique extends Admin
{
    /**
     * Configure the ordered field.
     */
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        parent::configureDefaultSortValues($sortValues);
        $sortValues['_sort_order'] = 'DESC';
        $sortValues['_sort_by'] = 'dataset.titre';  // name of the ordered field
        // (default = the model's id field, if any)
    }

    /**
     * Les points de contrôle de la chronique.
     */
    private $checkpoints = null;

    /**
     * {@inheritdoc}
     */
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection
            ->add('list')
            ->add('clone', $this->getRouterIdParameter().'/clone')
        ;
    }

    /**
     * @return array|null
     */
    public function getCheckpoints()
    {
        if ($this->checkpoints === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->checkpoints = $this->getBdohRepo('PointControle')->findByChronique($this->getSubject());
        }

        return $this->checkpoints;
    }

    /**
     * Les chroniques filles.
     */
    private $childrenTimeSeries = null;

    /**
     * @return array|null
     */
    public function getChildrenTimeSeries()
    {
        if ($this->childrenTimeSeries === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->childrenTimeSeries = $this->getBdohRepo('Chronique')->findChildren($this->getSubject());
        }

        return $this->childrenTimeSeries;
    }

    /**
     * @var null
     */
    private $mustDefineMilieu = null;

    /**
     * @return bool
     */
    public function getMustDefineMilieu()
    {
        $subject = $this->hasSubject() ? $this->getSubject() : null;
        if ($this->mustDefineMilieu === null) {
            $this->mustDefineMilieu = $subject && $subject->getId() && $subject->getMilieu() === null;
        }

        return $this->mustDefineMilieu;
    }

    /**
     * In order to automaticaly generate a Chronique.code from TypeParametre.code,
     * the 'Chronique' admin uses its own editing template.
     *
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-chronique.html.twig');
        $this->setTemplate('delete', '@IrsteaBdohAdmin/CRUD/delete-chronique.html.twig');
    }

    /**
     * L'aide du champ de l'unité : ChroniqueContinue en donne une autre (setHelps de Sonata 3, appelé
     * après celui-ci).
     */
    protected function getUniteHelp(TranslatorInterface $translator): string
    {
        return $translator->trans('admin.help.theia.theia-message');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
          ->add('code')
          ->add('unite')
          ->add('station')
          // Sonata 4 : les options du champ de filtre passent dans celles du filtre (field_options)
          ->add('station.sites', null, ['field_options' => ['expanded' => false, 'multiple' => true]])
            ->add('producteur', null, ['field_options' => ['expanded' => false, 'multiple' => true]])
            ->add('parametre', null, ['field_options' => ['expanded' => false, 'multiple' => true]])
            ->add('parametre.familleParametres', null, ['field_options' => ['expanded' => false, 'multiple' => true]])
            ->add('dateDebutMesures', DateFilter::class, ['field_options' => ['widget' => 'single_text']])
            ->add('dateFinMesures', DateFilter::class, ['field_options' => ['widget' => 'single_text']])
            ->add('dataset', null, ['field_options' => ['expanded' => false, 'multiple' => true]]);
    }

    /**
     * @param ErrorElement $errorElement
     * @param mixed        $object
     */
    public function validate(ErrorElement $errorElement, $object)
    {
        /**
         * Les contraintes s'active uniquement si le code Theia a été renseigné et si la chronique est reliée à un jeu de donnée.
         */
        $observatoireCourant = $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent();
        $theiaCode = $observatoireCourant->getTheiaCode();
        $chronique = $this->getSubject();
        $dataset = $chronique->getDataSet();

        if ($theiaCode !== null && $dataset !== null) {
            $errorElement
                ->with('libelleEn')
                ->assertNotNull(
                    [
                        'message' => 'Il faut au moins un libellé en anglais (Contraintes Theia/Ozcar)',
                    ]
                )
                ->end();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $isLocaleEn = \strpos($this->getContainer()->get('translator')->getLocale(), 'en') === 0;
        $label = $isLocaleEn ? 'nomEn' : 'nom';
        $listMapper
            ->addIdentifier('code', null, ['route' => ['name' => 'edit']])
            ->add('estVisible')
            ->add('unite')
            ->add('parametre', null, ['associated_property' => $label])
            ->add('parametre.familleParametres')
            ->add('producteur')
            ->add('dataSet')
            ->add('station')
            ->add('station.sites')
            ->add('dateDebutMesures', FieldDescriptionInterface::TYPE_STRING, [])
            ->add('dateFinMesures', FieldDescriptionInterface::TYPE_STRING, [])
        ->add('_action', 'actions', [
        'actions' => [
            'edit'   => [],
            'clone' => [
                'template' => '@IrsteaBdohAdmin/CRUD/list__action_clone.html.twig',
            ],
            'delete' => [],
        ], ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $repoPartenaire = $this->getBdohRepo('Partenaire');
        $repoStation = $this->getBdohRepo('Station');
        $repoParam = $this->getBdohRepo('TypeParametre');
        $translator = $this->getContainer()->get('translator');
        $dateDebutMesure = $this->getSubject()->getDateDebutMesures();
        $dateFinMesure = $this->getSubject()->getDateFinMesures();

        $propertyParameter = 'nom';
        if (($isLocaleEn = \strpos($translator->getLocale(), 'en') === 0)) {
            $propertyParameter .= 'En';
        }

        $formMapper
            ->add('station', null, ['required' => true, 'query_builder' => $repoStation->createSecuredQueryBuilderForMesure($this->getUser())])
            ->add(
                'parametre',
                null,
                [
                    'required'      => true,
                    'choice_label'  => $propertyParameter,
                    'query_builder' => $repoParam->createQueryBuilder('p')->orderBy('p.' . $propertyParameter, 'ASC'),
                ]
            )
            ->add('code', null, ['help' => $translator->trans('admin.help.chronique.code'), 'help_html' => true])
            ->add('libelle', null, ['help' => $translator->trans('admin.help.chronique.libelle'), 'help_html' => true])
            ->add(
                'libelleEn',
                null,
                [
                    'required'  => true,
                    'help'      => $translator->trans('admin.help.theia.theia-message'),
                    'help_html' => true,
                ]
            )
            ->add('genealogie', TextareaType::class, ['required' => true])
            ->add('genealogieEn', TextareaType::class, ['required' => true]);

        if ($dateDebutMesure !== null && $dateFinMesure !== null) {
            $formMapper->add('dataset');
        } else {
            $formMapper->add('dataset', HiddenType::class);
        }

        $formMapper
            ->add('milieu', null, ['required' => true])
            ->add('estVisible', CheckboxType::class, [
                'required'  => false,
                'help'      => $translator->trans('admin.help.chronique.estVisible'),
                'help_html' => true,
            ])
            // Les seuils gardent la valeur saisie, que l'ancien arrondissait à 3 décimales
            ->add('minimumValide', PreciseNumberType::class, ['required' => false])
            ->add('maximumValide', PreciseNumberType::class, ['required' => false])
            ->add('unite', null, ['required' => true, 'help' => $this->getUniteHelp($translator), 'help_html' => true])
            ->add('producteur', ModelType::class, ['btn_add' => $translator->trans('button.add'),
                'required'                                      => false, 'query' => $repoPartenaire->createQueryBuilder(), ]);

        // Check whether the current "chronique"'s measures contain at least one
        // "value limit" (quality order = 600 or 700)
        $chronique = $this->getSubject();
        $hasLimiteValeur = $chronique->getId() !== null;
        if ($hasLimiteValeur) {
            $repoName = ($chronique instanceof ChroniqueContinue) ? 'Mesure' : 'Plage';
            /* @var $mesureOuPlageRepo EntityRepository */
            $mesureOuPlageRepo = $this->getBdohRepo($repoName);
            $hasLimiteValeur = (bool)
                $mesureOuPlageRepo->createQueryBuilder('m')->select('m')
                    ->where('m.chronique = :chronique')
                    ->leftJoin('m.qualite', 'q')->andWhere('q.ordre IN (:ordres)')
                    // Remplace les paramètres, dont celui de l'observatoire que createQueryBuilder pose
                    // pour une condition que where() vient d'effacer (setParameters d'ORM 2)
                    ->setParameters(new ArrayCollection([
                        new Parameter('chronique', $chronique),
                        new Parameter('ordres', Qualite::$ordresLimites),
                    ]))
                    ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        }

        $isConvertie = $chronique->isConvertie();
        $valueLimits = (bool) $chronique->getAllowValueLimits();

        if ($hasLimiteValeur) {
            $formMapper->add(
                $allowValueLimitsField = 'allowValueLimits_dummy',
                CheckboxType::class,
                [
                    'required'  => true,
                    'disabled'  => true,
                    'data'      => true,
                    'mapped'    => false,
                    'label'     => 'allowValueLimits',
                    'help'      => $isConvertie ? 'chronique.lq_ld.legacy_and_already_present' : 'chronique.lq_ld.forced_allow_already_present',
                    'help_html' => true,
                ]
            );
            // s'il s'agit d'une chronique convertie, le champs est purrement informatif, il ne peut pas être modifié
            if (!$isConvertie) {
                $formMapper->add(
                    'allowValueLimits',
                    HiddenType::class,
                    [
                        'required' => true,
                        'data'     => true,
                    ]
                );
            }
        } else {
            if ($this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent()->getJeu()->hasValueLimits()) {
                if ($isConvertie) {
                    $formMapper
                        // s'il s'agit d'une chronique convertie, le champs est purrement informatif, il ne peut pas être modifié
                        ->add(
                            $allowValueLimitsField = 'allowValueLimits_dummy',
                            CheckboxType::class,
                            [
                                'required'  => false,
                                'disabled'  => true,
                                'data'      => $valueLimits,
                                'mapped'    => false,
                                'label'     => 'allowValueLimits',
                                'help'      => 'chronique.lq_ld.legacy_parent_time_series',
                                'help_html' => true,
                            ]
                        );
                } else {
                    $formMapper->add('allowValueLimits', CheckboxType::class, ['required' => false]);
                }
            }
            // je ne sais pas ce qui est le mieux entre le laisser à NULL ou le forcer à FALSE quand ce n'est pas autorisé
            /*else {
                $formMapper->add(
                    'allowValueLimits',
                    'hidden',
                    [
                        'required' => false,
                        'data'     => false,
                    ]
                );
            }*/
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $em = $this->getModelManager()->getEntityManager($object);
        $object->setEchantillonageSet(true);
        $em->persist($object);
        $em->flush();

        $this->logger->createHistoriqueAdministrationChronique(
            $this->getUser(),
            new DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.ChroniqueCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $em = $this->getModelManager()->getEntityManager($object);
        $object->setEchantillonageSet(true);
        $em->persist($object);
        $em->flush();

        $this->logger->createHistoriqueAdministrationChronique(
            $this->getUser(),
            new DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.ChroniqueUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationChronique(
            $this->getUser(),
            new DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.ChroniqueRemove',
            $object
        );
    }
}
