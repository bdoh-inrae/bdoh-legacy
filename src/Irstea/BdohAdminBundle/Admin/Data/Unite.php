<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * Class Unite.
 */
class Unite extends Admin
{
    /**
     * Configure the ordered field.
     */
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        parent::configureDefaultSortValues($sortValues);
        $sortValues['_sort_by'] = 'libelle';  // name of the ordered field
        // (default = the model's id field, if any)
    }

    /**
     * Les chroniques qui utilisent cette unité.
     */
    private $timeSeries = null;

    /**
     * @return array|null
     */
    public function getTimeSeries()
    {
        if ($this->timeSeries === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->timeSeries = $this->getBdohRepo('Chronique')->findByUnite($this->getSubject());
        }

        return $this->timeSeries;
    }

    /**
     * Les bnarèmes qui utilisent cette unité.
     */
    private $scales = null;

    /**
     * @return array|null
     */
    public function getScales()
    {
        if ($this->scales === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->scales = $this->getBdohRepo('Bareme')->findByUnite($this->getSubject());
        }

        return $this->scales;
    }

    /**
     * Les types de paramètre qui utilisent cette unité.
     */
    private $parameterTypes = null;

    /**
     * @return array|null
     */
    public function getParameterTypes()
    {
        if ($this->parameterTypes === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->parameterTypes = $this->getBdohRepo('TypeParametre')->findByUnite($this->getSubject());
        }

        return $this->parameterTypes;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-unite.html.twig');
        $this->setTemplate('delete', '@IrsteaBdohAdmin/CRUD/delete-unite.html.twig');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('libelle', null, ['route' => ['name' => 'edit']]);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('libelle')
            ->add('libelleEn');
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationUnite(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.UniteCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationUnite(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.UniteUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationUnite(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.UniteRemove',
            $object
        );
    }
}
