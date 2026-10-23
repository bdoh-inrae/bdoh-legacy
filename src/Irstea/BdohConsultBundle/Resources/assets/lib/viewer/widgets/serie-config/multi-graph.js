/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define(['./base'], (BaseSerieConfig) => {
    'use strict';

    function MultiGraphSerieConfig() {
        BaseSerieConfig.call(this);
    }

    const _super = BaseSerieConfig.prototype;
    MultiGraphSerieConfig.prototype = Object.create(_super);

    MultiGraphSerieConfig.prototype._getColor = function (serie, r, g, b) {
        if (serie.type === 'invalid' || serie.type === 'gap') {
            return this._formatColor(r, g, b, 0.6);
        }
        return _super._getColor.call(this, serie, r, g, b);
    };

    MultiGraphSerieConfig.prototype._applySerieType = function (options, serie) {
        _super._applySerieType.call(this, options, serie);

        // couleurs des lignes (définies avec jqplot.ColorGenerator() dans panel.js)
        options.color = this._getColor(serie, serie.measures.color[0], serie.measures.color[1], serie.measures.color[2]);
    };

    return MultiGraphSerieConfig;

});
