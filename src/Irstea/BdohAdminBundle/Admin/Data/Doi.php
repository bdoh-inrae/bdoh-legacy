<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * Class Doi.
 */
class Doi extends Admin
{
    /**
     * Configure the ordered field.
     */
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        parent::configureDefaultSortValues($sortValues);
        $sortValues['_sort_by'] = 'identifiant';  // name of the ordered field
        // (default = the model's id field, if any)
    }

    /**
     * {@inheritdoc}
     */
    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper
            ->add('identifiant')
            ->add('description');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('identifiant', null, ['route' => ['name' => 'edit']])
            ->add('description');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('identifiant', TextType::class, [
                'attr'      => ['maxlength' => '255'],
                'help'      => 'admin.help.doi.identifiant',
                'help_html' => true,
            ])
            ->add('description', TextType::class, [
                'attr'      => ['maxlength' => '750'],
                'help'      => 'admin.help.doi.description',
                'help_html' => true,
            ])
            ->add('descriptionEn', TextType::class, ['attr' => ['maxlength' => '750']]);
    }

    /**
     * {@inheritdoc}
     */
    protected function prePersist(object $doi): void
    {
        /* @var \Irstea\BdohDataBundle\Entity\Doi $doi */
        $doi->setObservatoire(
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationDoi(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.DoiCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationDoi(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.DoiUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationDoi(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.DoiRemove',
            $object
        );
    }
}
