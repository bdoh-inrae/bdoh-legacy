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

/**
 * Class FamilleParametres.
 */
class FamilleParametres extends Admin
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
     * Les familles qui utilisent cette famille comme famille mère.
     */
    private $childrenFamilies = null;

    /**
     * @return array|null
     */
    public function getChildrenFamilies()
    {
        if ($this->childrenFamilies === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->childrenFamilies = $this->getBdohRepo('FamilleParametres')->findByFamilleParente($this->getSubject());
        }

        return $this->childrenFamilies;
    }

    /**
     * Les types de paramètres qui sont dans cette famille de paramètres.
     */
    private $parameterTypes = null;

    /**
     * @return array|null
     */
    public function getParameterTypes()
    {
        if ($this->parameterTypes === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->parameterTypes = $this->getBdohRepo('TypeParametre')->findByFamilleParametres($this->getSubject());
        }

        return $this->parameterTypes;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-famille-parametres.html.twig');
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
            ->addIdentifier('prefix')
            ->add('familleParente', null, ['associated_property' => $label]);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $translator = $this->getContainer()->get('translator');
        $isLocaleEn = \strpos($translator->getLocale(), 'en') === 0;
        /** @var FamilleParametresRepository $familleRepo */
        $familleRepo = $this->getBdohRepo('FamilleParametres');
        $choices = $familleRepo->findAllHierachy($this->hasSubject() ? $this->getSubject() : null, $isLocaleEn);
        $label = $isLocaleEn ? 'nomEn' : 'nom';
        $formMapper
            ->add('nom')
            ->add('nomEn')
            ->add('prefix', null, [
                'help'      => $translator->trans('admin.help.familleParametres.help-prefix'),
                'help_html' => true,
            ])
            ->add(
                'familleParente',
                ChoiceType::class,
                [
                    'choices'           => $choices,
                    'choice_label'      => $label,
                    'choice_attr'       => function ($family) {
                        /* @var \Irstea\BdohDataBundle\Entity\FamilleParametres $family */
                        return ['class' => 'hierarchy-level-' . $family->depth];
                    },
                    'required'          => false,
                    'help'              => $translator->trans('admin.help.familleParametres.help-familleParente'),
                    'help_html'         => true,
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('familleParente');
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationFamilleParametres(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.FamilleParametresCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationFamilleParametres(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.FamilleParametresUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationFamilleParametres(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.FamilleParametresRemove',
            $object
        );
    }
}
