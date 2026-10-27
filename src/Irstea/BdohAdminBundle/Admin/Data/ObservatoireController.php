<?php

namespace Irstea\BdohAdminBundle\Admin\Data;

use Irstea\BdohDataBundle\Entity\Observatoire;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ObservatoireController extends CRUDController
{
    /**
     * Un observatoire se modifie depuis sa propre page : les choix de son formulaire (responsable et
     * gestionnaires de données, DOI) sont ceux de l'observatoire de la page, et l'enregistrement perdait
     * ceux qui n'y étaient pas (dev/differences.md, « Gestionnaires de données d'un observatoire perdus »).
     */
    protected function preEdit(Request $request, object $object): ?Response
    {
        \assert($object instanceof Observatoire);
        if ($object->getSlug() === $request->attributes->get('_observatoire')) {
            return null;
        }

        return $this->redirect($this->admin->generateUrl(
            'edit',
            ['id' => $object->getId(), '_observatoire' => $object->getSlug()]
        ));
    }
}
