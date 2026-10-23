/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define(['./chronique'], (Chronique) => {
    'use strict';

    function Station(id, name, code, hasMeasures, chroniques) {
        this.id = id;
        this.name = name;
        this.code = code;
        this.hasMeasures = hasMeasures;
        this.chroniques = chroniques;
    }

    Station.prototype.dispose = function() {
        $.each(this.chroniques, (id, chronique) => {
            chronique.dispose();
        });
        this.chroniques = null;
    };

    Station.prototype.toString = function() {
        return this.code;
    };

    return Station;
});
