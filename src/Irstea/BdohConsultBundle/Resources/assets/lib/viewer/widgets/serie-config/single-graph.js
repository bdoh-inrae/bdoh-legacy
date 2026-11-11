// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
define(['./base'], (BaseSerieConfig) => {
    'use strict';

    function SingleGraphSerieConfig() {
        BaseSerieConfig.call(this);
        this.showControlPoints = true;
    }

    const _super = BaseSerieConfig.prototype;
    SingleGraphSerieConfig.prototype = Object.create(_super);

    SingleGraphSerieConfig.prototype.shouldDraw = function (serie) {
        return this.showControlPoints || serie.type !== 'controlPoints';
    };

    SingleGraphSerieConfig.prototype._applySerieType = function (options, serie) {
        _super._applySerieType.call(this, options, serie);

        // couleur et épaisseur des lignes (en fonction du style), sauf pour les lacunes et les invalides, en bandes
        // de leur teinte à elles (base.js)
        if (serie.type !== 'invalid' && serie.type !== 'gap') {
            options.color = this._getColor(serie, serie.style.color[0], serie.style.color[1], serie.style.color[2]);
            options.lineWidth = serie.style.thickness;
        }

        // marqueurs des bornes des plages des discontinues (en fonction du style)
        if (serie.type !== 'invalid' && serie.type !== 'gap' &&
            serie.measures.samplingType === 'discontinuous') {
            options.markerOptions.style =  serie.style.shape;
            options.markerOptions.lineWidth = serie.style.stroke;
            options.markerOptions.size = serie.style.size;
        }

        // points de contrôle (en fonction du style)
        if (serie.type === 'controlPoints') {
            options.showLine = false;
            options.showMarker = true;
            options.markerOptions.show = true;
            options.markerOptions.shadow = false;
            options.markerOptions.style = serie.style.shape;
            options.markerOptions.lineWidth = serie.style.stroke;
            options.markerOptions.size = serie.style.size;
        }
    };

    return SingleGraphSerieConfig;
});
