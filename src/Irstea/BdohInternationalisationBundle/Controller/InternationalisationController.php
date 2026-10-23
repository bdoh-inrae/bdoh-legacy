<?php
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohInternationalisationBundle\Controller;

use Irstea\BdohBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class InternationalisationController extends Controller
{
    /**
     * La langue choisie est déjà notée en session par le LocaleListener ; l'action renvoie à la page
     * d'où l'on vient. L'ancien demandait le service « request », retiré, et finissait sur une erreur
     * interne, après avoir noté la langue (dev/differences.md).
     */
    public function selectLangAction(Request $request, $langue = null)
    {
        if ($langue !== null) {
            $this->get('session')->set('_locale', $langue);
        }

        $url = $request->headers->get('referer');
        if (empty($url)) {
            // Hors d'un observatoire (/select_lang/… est à la racine), l'accueil de tous les observatoires
            $router = $this->get('router');
            $url = $router->getContext()->hasParameter('_observatoire')
                ? $router->generate('bdoh_home') : $router->generate('bdoh_all_home');
        }

        return new RedirectResponse($url);
    }
}
