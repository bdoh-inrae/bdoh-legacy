<?php

namespace Irstea\BdohAdminBundle\Admin\Extension;

use Sonata\AdminBundle\Admin\AbstractAdminExtension;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Route\RouteCollectionInterface;

/**
 * Pas d'export dans l'administration, comme dans l'ancien : Admin::getExportFormats() rend une liste
 * vide, que Sonata 3 lisait comme « aucun format », et Sonata 4 comme « tous les formats ». Sans la
 * route, ni bouton ni adresse ; le nom bdoh_admin_bareme_export reste à l'export des barèmes
 * (routing.yml).
 */
final class NoExportExtension extends AbstractAdminExtension
{
    public function configureRoutes(AdminInterface $admin, RouteCollectionInterface $collection): void
    {
        $collection->remove('export');
    }
}
