<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Irstea\BdohAdminBundle\Form\Type\WysiwygType;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * Class SiteExperimental.
 */
class SiteExperimental extends Admin
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
     * List of stations in the site.
     */
    private $stations = null;

    /**
     * @return array|null
     */
    public function getStations()
    {
        if ($this->stations === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->stations = $this->getSubject()->getStations();
        }

        return $this->stations;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-site.html.twig');
    }

    /**
     * Sets 'observatoire' attribute to the current 'observatoire'.
     *
     * {@inheritdoc}
     */
    protected function prePersist(object $site): void
    {
        $site->setObservatoire(
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('nom', null, ['route' => ['name' => 'edit']]);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $formMapper
            ->add('nom')
            ->add('description', WysiwygType::class, ['required' => false])
            ->add('descriptionEn', WysiwygType::class, ['required' => false]);
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationSiteExperimental(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.SiteExperimentalCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $this->logger->createHistoriqueAdministrationSiteExperimental(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.SiteExperimentalUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationSiteExperimental(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.SiteExperimentalRemove',
            $object
        );
    }
}
