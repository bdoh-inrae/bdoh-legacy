<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohSecurityBundle\Security;

/**
 * Les pages du compte ont l'adresse d'un observatoire (« /DRAIX/login »), et, sur le portail, une adresse à elles
 * (« /login »), que garde un pare-feu à lui, du même contexte (config/packages/security.yaml) : on s'y connecte et
 * on s'y inscrit sans choisir d'observatoire, et l'on revient au portail (Lou, 2026-11-12). Le nom de la route selon
 * qu'il y a un observatoire courant ou non ; le portail n'a pas de page de modification du compte, qui le ramène à
 * son accueil.
 */
final class AccountRoutes
{
    private const ROUTES = [
        'login'       => ['bdoh_security_login', 'bdoh_portal_login'],
        'login_send'  => ['bdoh_security_login_send', 'bdoh_portal_login_send'],
        'login_check' => ['bdoh_security_login_check', 'bdoh_portal_login_check'],
        'new'         => ['bdoh_security_utilisateur_new', 'bdoh_portal_user_new'],
        'edit'        => ['bdoh_security_utilisateur_edit', 'bdoh_all_home'],
        'logout'      => ['bdoh_security_logout', 'bdoh_portal_logout'],
        'home'        => ['bdoh_home', 'bdoh_all_home'],
    ];

    public static function name(string $page, ?object $observatoire): string
    {
        return self::ROUTES[$page][null === $observatoire ? 1 : 0];
    }
}
