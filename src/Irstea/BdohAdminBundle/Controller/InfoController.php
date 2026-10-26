<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohAdminBundle\Controller;

use Irstea\BdohBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Class InfoController.
 */
class InfoController extends Controller
{
    /**
     * @return Response
     */
    #[IsGranted('ROLE_ADMIN')]
    public function generateChroniqueCodeAction()
    {
        $request = $this->getRequest()->query;
        $stationId = $request->get('station');
        $parametreId = $request->get('parametre');

        if (null === $station = $this->getBdohRepo('Station')->find($stationId)) {
            return new Response('');
        }

        if (null === $parametre = $this->getBdohRepo('TypeParametre')->find($parametreId)) {
            return new Response('');
        }

        $count = $this->getBdohRepo('Chronique')->countByStationAndParametre($station, $parametre) + 1;

        return new Response($parametre->getCode() . ($count === 1 ? '' : ("-$count")));
    }

    /**
     * @return Response
     */
    #[IsGranted('ROLE_ADMIN')]
    public function getFilteredChroniquesAction()
    {
        $request = $this->getRequest()->query;
        $bassinId = $request->get('bassinId');
        $coursEauId = $request->get('coursEauId');

        $chronRepo = $this->getBdohRepo('Chronique');

        // cette methode n'existe pas ...
        // TODO à virer ou à corriger, cette action ne semble pas être utilisée non plus
        $chroniques = $chronRepo->richFindByGeography($bassinId, $coursEauId);

        return new Response($chroniques);
    }
}
