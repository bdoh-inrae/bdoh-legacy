// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
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

    // Plusieurs chroniques sur un graphique : les bandes de chacune dans sa couleur, pour qu'on sache de laquelle
    MultiGraphSerieConfig.prototype._bandFill = function (serie) {
        return this._formatColor(serie.measures.color[0], serie.measures.color[1], serie.measures.color[2], 0.12);
    };

    MultiGraphSerieConfig.prototype._applySerieType = function (options, serie) {
        _super._applySerieType.call(this, options, serie);

        // couleurs des lignes (définies avec jqplot.ColorGenerator() dans panel.js)
        options.color = this._getColor(serie, serie.measures.color[0], serie.measures.color[1], serie.measures.color[2]);
    };

    return MultiGraphSerieConfig;

});
