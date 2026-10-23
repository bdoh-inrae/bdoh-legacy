
/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([], () => {
    'use strict';

    function Serie(type, measures, values, style, label, showLabel) {
        this.type = type;
        this.measures = measures;
        this.values = values;
        this.style = style;
        this.label = label;
        this.showLabel = showLabel;
    }

    Serie.prototype.dispose = function() {
        this.measures = this.values = this.type = null;
    };

    return Serie;
});
