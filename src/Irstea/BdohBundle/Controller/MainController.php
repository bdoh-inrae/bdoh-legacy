<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

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
        return $this->render('@IrsteaBdoh/Core/home.html.twig');
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
}
