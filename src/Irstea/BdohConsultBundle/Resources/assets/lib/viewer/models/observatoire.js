/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([], () => {
    'use strict';

    function Observatoire(name) {
        this.name = name;
    }

    Observatoire.prototype.toString = function () {
        return this.name;
    };

    return Observatoire;
});
