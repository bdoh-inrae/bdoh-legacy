<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Irstea\BdohDataBundle\Entity\Repository\FamilleParametresRepository;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Sonata\AdminBundle\Form\Type\ModelType;

/**
 * Class TypeParametre.
 */
class TypeParametre extends Admin
{
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
     * Les chroniques qui utilisent ce type de paramètre.
     */
    private $timeSeries = null;

    /**
     * @return array|null
     */
    public function getTimeSeries()
    {
        if ($this->timeSeries === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->timeSeries = $this->getBdohRepo('Chronique')->findByParametre($this->getSubject());
        }

        return $this->timeSeries;
    }

    /**
     * @var null
     */
    private $mustDefineParameterFamily = null;

    /**
     * @return bool
     */
    public function getMustDefineParameterFamily()
    {
        $subject = $this->hasSubject() ? $this->getSubject() : null;
        if ($this->mustDefineParameterFamily === null) {
            $this->mustDefineParameterFamily = $subject && $subject->getId() && $subject->getFamilleParametres() === null;
        }

        return $this->mustDefineParameterFamily;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-type-parametre.html.twig');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $isLocaleEn = \strpos($this->getContainer()->get('translator')->getLocale(), 'en') === 0;
        $label = $isLocaleEn ? 'nomEn' : 'nom';
        $listMapper
            ->addIdentifier('nom', null, ['route' => ['name' => 'edit']])
            ->addIdentifier('nomEn', null, ['route' => ['name' => 'edit']])
            ->add('code')
            ->add('familleParametres', null, ['associated_property' => $label]);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $isLocaleEn = \strpos($this->getContainer()->get('translator')->getLocale(), 'en') === 0;
        /** @var FamilleParametresRepository $familleRepo */
        $familleRepo = $this->getBdohRepo('FamilleParametres');
        $choices = $familleRepo->findAllHierachy(null, $isLocaleEn);
        $label = $isLocaleEn ? 'nomEn' : 'nom';
        $formMapper
            ->add('nom')
            ->add('nomEn')
            ->add('code')
            ->add(
                'familleParametres',
                ChoiceType::class,
                [
                    'choices'           => $choices,
                    'choice_label'      => $label,
                    'choice_attr'       => function ($family) {
                        /* @var \Irstea\BdohDataBundle\Entity\FamilleParametres $family */
                        return ['class' => 'hierarchy-level-' . $family->depth];
                    },
                    'required'          => true,
                ]
            )
            ->add(
                'unites',
                ModelType::class,
                [
                    'expanded' => true,
                    'multiple' => true,
                    'required' => false,
                    'query'    => $this->getBdohRepo('Unite')->createOrderedQueryBuilder(),
                    'attr'     => ['class' => 'inputs-list-inline'],
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('familleParametres');
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationTypeParametre(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.TypeParametreCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationTypeParametre(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.TypeParametreUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationTypeParametre(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.TypeParametreRemove',
            $object
        );
    }
}
