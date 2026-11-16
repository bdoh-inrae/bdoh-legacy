<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohBundle\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Irstea\BdohBundle\Util\HttpFileResponse;

/**
 * Class MainController.
 */
class MainController extends Controller
{
    #[Route('', name: 'bdoh_all_home', methods: ['GET'])]
    public function homeAction()
    {
        return $this->render('@IrsteaBdoh/Core/home.html.twig', [
            'mapStations' => $this->getBdohRepo('Station')->findForMap(),
        ]);
    }

    /**
     * @return HttpFileResponse
     */
    #[Route('/user_manual', name: 'bdoh_user_manual', methods: ['GET'])]
    public function userManualAction()
    {
        $userManualPath = $this->getParameter('irstea_bdoh.user_manual');

        return new HttpFileResponse($userManualPath, 'application/octet-stream');
    }

    /**
     * Les conditions d'utilisation des données, une page pour tout BDOH, sans observatoire dans son adresse (Lou,
     * 2026-11-15) ; l'ancienne adresse de chaque observatoire, que des fiches Theia citent, y renvoie
     * (ObservatoireController::cguAction).
     */
    #[Route('/terms_of_use', name: 'bdoh_terms_of_use', methods: ['GET'])]
    public function termsAction()
    {
        return $this->render('@IrsteaBdoh/Core/terms.html.twig');
    }

    /**
     * L'aide, une page pour tout BDOH, où mène le « ? » de toutes les pages (Lou, 2026-11-16) : la notice, le support
     * pour le site, les gestionnaires de chaque observatoire pour ses données.
     */
    #[Route('/help', name: 'bdoh_help', methods: ['GET'])]
    public function helpAction()
    {
        return $this->render('@IrsteaBdoh/Core/help.html.twig');
    }

    /**
     * Les mentions légales et la déclaration d'accessibilité, une page pour tout BDOH, liée au pied de chaque page
     * (Lou, 2026-11-16) : le paragraphe qui était sous chaque page, en sections.
     */
    #[Route('/legal', name: 'bdoh_legal', methods: ['GET'])]
    public function legalAction()
    {
        return $this->render('@IrsteaBdoh/Core/legal.html.twig');
    }
}
