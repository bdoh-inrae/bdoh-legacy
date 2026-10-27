<?php

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\Form\Validator\ErrorElement;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Sonata\AdminBundle\Form\Type\ModelListType;
use Sonata\AdminBundle\Form\Type\ModelType;

class DataSet extends Admin
{
    /**
     * Configure the ordered field.
     */
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        parent::configureDefaultSortValues($sortValues);
        $sortValues['_sort_by'] = 'titre';  // name of the ordered field
        // (default = the model's id field, if any)
        //
    }

    /**
     * the 'Chronique' admin uses its own editing template.
     *
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-dataset.html.twig');
    }

    /**
     * Sets 'observatoire' attribute to the current 'observatoire'.
     *
     * {@inheritdoc}
     */
    protected function prePersist(object $dataset): void
    {
        $dataset->setObservatoire(
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('titre')
            ->add('principalInvestigators')
            ->add('portailSearchCriteriaClimat')
            ->add('portailSearchCriteriaGeology');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('titre', null, ['route' => ['name' => 'edit']])
            ->add('principalInvestigators')
            ->add('observatoire')
            ->add('portailSearchCriteriaClimat')
            ->add('portailSearchCriteriaGeology');
    }

    public function validate(ErrorElement $errorElement, $object)
    {
        /**
         * Les contraintes s'active uniquement si le code Theia a été renseigné.
         */
        $observatoireCourant = $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent();
        $theiaCode = $observatoireCourant->getTheiaCode();
        if ($theiaCode !== null) {
            $errorElement
                ->with('principalInvestigators')
                ->assertCount(
                    ['min'           => 1,
                        'minMessage' => 'Il faut au moins un Principal Investigator (Contraintes Theia/Ozcar)',
                    ]
                )
                ->end()
                ->with('portailSearchCriteriaGeology')
                ->assertCount(
                    ['min'           => 1,
                        'minMessage' => 'Il faut au moins un critère (Contraintes Theia/Ozcar)',
                    ]
                )
                ->end()
                ->with('portailSearchCriteriaClimat')
                ->assertCount(
                    ['min'           => 1,
                        'minMessage' => 'Il faut au moins un critère (Contraintes Theia/Ozcar)',
                    ]
                )
                ->end()
                ->with('topicCategories')
                ->assertCount(
                    ['min'           => 1,
                        'minMessage' => 'Il faut au moins une catégorie (Contraintes Theia/Ozcar)',
                    ]
                )
                ->end();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $translator = $this->getContainer()->get('translator');
        $repoChronique = $this->getBdohRepo('Chronique');
        $repoPersonneTheia = $this->getBdohRepo('PersonneTheia');

        $dataset = $this->getSubject();

        $formMapper
            ->add('titre', TextType::class, [
                'required' => true,
            ])
            ->add('titreEn', TextType::class, [
                'required' => true,
            ])
            ->add('uuid', HiddenType::class)
            ->add('description', TextareaType::class, [
                'required'  => true,
                'help'      => $translator->trans('admin.help.dataset.description'),
                'help_html' => true,
            ])
            ->add('descriptionEn', TextareaType::class, [
                'required' => true,
            ])
            ->add('genealogie', TextareaType::class, [
                'help'      => $translator->trans('admin.help.dataset.genealogy'),
                'help_html' => true,
            ], [
                'required' => true,
            ])
            ->add('genealogieEn', TextareaType::class, [], [
                'required' => true,
            ])
            ->add('dataConstraint', null, [
                'required' => true,
            ])
            ->add(
                'doi',
                ModelListType::class,
                [
                    'btn_add'    => false,
                    // Sonata 3 n'avait pas ce bouton
                    'btn_edit'   => false,
                    'btn_delete' => $this->listDeleteButton('irstea_bdoh_admin.admin.doi'),
                    'required'   => false,
                ],
                [
                ]
            )
            ->end()
            ->with('Contact', ['class' => 'col-md-6'])
            ->add('principalInvestigators', ModelType::class, [
                'query'     => $repoPersonneTheia->createQueryBuilder('PersonneTheia'),
                'required'  => true,
                'multiple'  => true,
                'btn_add'   => $translator->trans('button.add'),
                'help'      => $translator->trans('admin.help.astuce'),
                'help_html' => true,
            ])
            ->add('dataManagers', ModelType::class, [
                'query'     => $repoPersonneTheia->createQueryBuilder('PersonneTheia'),
                'required'  => false,
                'multiple'  => true,
                'btn_add'   => $translator->trans('button.add'),
                'help'      => $translator->trans('admin.help.astuce'),
                'help_html' => true,
            ])
            ->add('dataCollectors', ModelType::class, [
                'query'     => $repoPersonneTheia->createQueryBuilder('PersonneTheia'),
                'required'  => false,
                'multiple'  => true,
                'btn_add'   => $translator->trans('button.add'),
                'help'      => $translator->trans('admin.help.astuce'),
                'help_html' => true,
            ])
            ->add('projectMembers', ModelType::class, [
                'query'     => $repoPersonneTheia->createQueryBuilder('PersonneTheia'),
                'required'  => false,
                'multiple'  => true,
                'btn_add'   => $translator->trans('button.add'),
                'help'      => $translator->trans('admin.help.astuce'),
                'help_html' => true,
            ])
            ->end()
            ->with('Mots clés', ['class' => 'col-md-6'])
            ->add('topicCategories', null, [
                'required'  => true,
                'help'      => $translator->trans('admin.help.astuce'),
                'help_html' => true,
            ])
            ->add('inspireTheme', null, [
                'required'  => true,
                'help'      => $translator->trans('admin.help.astuce'),
                'help_html' => true,
            ])
            ->add('portailSearchCriteriaClimat', null, [
                'required' => true,
                'expanded' => true,
            ])
            ->add('portailSearchCriteriaGeology', null, [
                'required' => true,
                'expanded' => true,
            ])
            ->end()
            ->with('Chroniques', ['class' => 'col-md-12'])
            ->add('chroniques', ModelType::class, [
                'query'        => $repoChronique->createQueryBuilderFilter('Chronique', null, $dataset),
                'required'     => false,
                'btn_add'      => false,
                'multiple'     => true,
                'by_reference' => false,
                'property'     => 'nomLong',
                'help'         => $translator->trans('admin.help.astuce'),
                'help_html'    => true,
            ])
            ->end();
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationDataSet(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.DatasetCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationDataSet(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.DatasetUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationDataSet(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.DatasetRemove',
            $object
        );
    }
}
