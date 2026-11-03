<?php

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

}
