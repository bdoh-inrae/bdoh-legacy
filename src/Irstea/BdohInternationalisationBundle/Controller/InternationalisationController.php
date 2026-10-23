<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohInternationalisationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class InternationalisationController extends Controller
{
    public function selectLangAction($langue = null)
    {
        if ($langue !== null) {
            $this->get('session')->set('_locale', $langue);
        }

        $url = $this->container->get('request')->headers->get('referer');
        if (empty($url)) {
            $url = $this->container->get('router')->generate('bdoh_home');
        }

        return new RedirectResponse($url);
    }
}
