<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohDataBundle\Controller;

use Irstea\BdohBundle\Controller\Controller;
use Irstea\BdohBundle\Util\HttpFileResponse;

/**
 * Realizes the following downloads :
 *      => Download of user conditions.
 */
class DownloadController extends Controller
{
    /**
     * Download of user conditions.
     */
    public function conditionsAction()
    {
        $observatoire = $this->get('irstea_bdoh.manager.observatoire')->getCurrent();
        $termsOfUses = $observatoire->getConditionsUtilisation();
        $cguPath = $termsOfUses ?: $this->getParameter('irstea_bdoh_data.measure_export.default_termofuses_file');

        return new HttpFileResponse($cguPath, 'application/octet-stream');
    }
}
