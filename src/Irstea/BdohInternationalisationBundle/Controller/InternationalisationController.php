<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohInternationalisationBundle\Controller;

use Irstea\BdohBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class InternationalisationController extends Controller
{
    /**
     * Note la langue choisie en session, qui s'ouvre au besoin, et renvoie à la page d'où l'on vient. Le
     * LocaleListener ne la note que s'il y a déjà une session : l'ancien en ouvrait une sur toutes les pages,
     * pas le nouveau pour un visiteur sans compte. L'ancien demandait le service « request », retiré, et
     * finissait sur une erreur interne, après avoir noté la langue (dev/differences.md).
     */
    public function selectLangAction(Request $request)
    {
        $request->getSession()->set('_locale', $request->attributes->get('_locale'));

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
