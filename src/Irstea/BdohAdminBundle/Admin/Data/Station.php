<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohAdminBundle\Admin\Admin;
use Irstea\BdohAdminBundle\Form\Type\PreciseNumberType;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Class Station.
 */
class Station extends Admin
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
     * List of time series in the station.
     */
    private $timeSeries = null;

    /**
     * @return array|null
     */
    public function getTimeSeries()
    {
        if ($this->timeSeries === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->timeSeries = $this->getSubject()->getChroniques();
        }

        return $this->timeSeries;
    }

    /**
     * Les bassins qui utilisent cette station en tant que station exutoire.
     */
    private $basins = null;

    /**
     * @return array|null
     */
    public function getBasins()
    {
        if ($this->basins === null && $this->hasSubject() && $this->getSubject()->getId()) {
            $this->basins = $this->getBdohRepo('Bassin')->findByStationExutoire($this->getSubject());
        }

        return $this->basins;
    }

    /**
     * In order to delete the "add and delete" tools for Station.commune field,
     * the 'Station' admin uses its own editing template.
     *
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setTemplate('edit', '@IrsteaBdohAdmin/CRUD/edit-station.html.twig');
        $this->setTemplate('delete', '@IrsteaBdohAdmin/CRUD/delete-station.html.twig');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $listMapper): void
    {
        $listMapper
            ->addIdentifier('nom', null, ['route' => ['name' => 'edit']])
            ->add('code')
            ->add('codeAlternatif')
            ->add('commune')
            ->add('altitude')
            ->add('estActive');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $formMapper): void
    {
        $translator = $this->getContainer()->get('translator');
        $repoSite = $this->getBdohRepo('SiteExperimental');
        $repoCommune = $this->getBdohRepo('Commune');

        $formMapper
            ->add('nom')
            ->add('code', null, ['help' => $translator->trans('admin.help.station.code'), 'help_html' => true])
            ->add(
                'commune',
                ModelAutocompleteType::class,
                [
                    'property'  => ['nom', 'codePostal'],
                    'required'  => false,
                    'btn_add'   => false, // Sonata 3 n'avait pas ce bouton
                    'help'      => $translator->trans('admin.help.station.town-name-is-case-sensitive'),
                    'help_html' => true,
                ]
            )
            ->add('sites', null, ['required' => true, 'query_builder' => $repoSite->createSecuredQueryBuilder($this->getUser())])
            ->add('codeAlternatif', null, ['help' => $translator->trans('admin.help.station.codeAlternatif'), 'help_html' => true])
            // Sans arrondi, que l'ancien faisait à 3 décimales (dev/differences.md)
            ->add('altitude', PreciseNumberType::class, ['required' => false])
            ->add('latitude', PreciseNumberType::class, [
                'required'  => false,
                'help'      => $translator->trans('admin.help.station.latlong'),
                'help_html' => true,
            ])
            ->add('longitude', PreciseNumberType::class, [
                'required'  => false,
                'help'      => $translator->trans('admin.help.station.latlong'),
                'help_html' => true,
            ])
            ->add('commentaire', TextareaType::class, ['required' => false])
            ->add('commentaireEn', TextareaType::class, ['required' => false])
            ->add('estActive', CheckboxType::class, ['required' => false]);

        // Le formulaire ne propose que les sites que le compte gère, et retirerait les autres de la
        // station : ils lui sont rendus (dev/differences.md, « Sites perdus d'une station »).
        $managed = $repoSite->createSecuredQueryBuilder($this->getUser())->getQuery()->getResult();
        $kept = $this->hasSubject() ? array_filter(
            $this->getSubject()->getSites()->toArray(),
            fn ($site) => !in_array($site, $managed, true)
        ) : [];
        $formMapper->getFormBuilder()->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($kept) {
            $sites = $event->getData()->getSites();
            foreach ($kept as $site) {
                if (!$sites->contains($site)) {
                    $sites->add($site);
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function postPersist(object $object): void
    {
        $this->logger->createHistoriqueAdministrationStation(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.StationCreate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function postUpdate(object $object): void
    {
        $stationRepo = $this->getBdohRepo('Station');
        $stationRepo->updatePosition($object);

        $this->logger->createHistoriqueAdministrationStation(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.StationUpdate',
            $object
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function preRemove(object $object): void
    {
        $this->logger->createHistoriqueAdministrationStation(
            $this->getUser(),
            new \DateTime(),
            $this->getContainer()->get('irstea_bdoh.manager.observatoire')->getCurrent(),
            'historique.actions.StationRemove',
            $object
        );
    }
}
