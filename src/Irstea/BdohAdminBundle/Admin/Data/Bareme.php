<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;

/**
 * Class Bareme.
 */
class Bareme extends Admin
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
     * {@inheritdoc}
     */
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection
            ->remove('create')
            ->remove('update')
        ;
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('delete', '@IrsteaBdohAdmin/CRUD/delete-bareme.html.twig');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('nom', null, ['route' => ['name' => 'edit']])
            ->add('uniteEntree')
            ->add('uniteSortie')
            ->add('dateCreation', 'date', ['format' => $this->getContainer()->get('translator')->trans('formatDateTime')])
            ->add('isOrphan', 'boolean')
            ->add('_action', 'actions', [
                'actions' => [
                    'delete' => [
                        'template' => '@IrsteaBdohAdmin/CRUD/list__action_delete_barem.html.twig',
                    ],
                ],
            ]);

    }
}
