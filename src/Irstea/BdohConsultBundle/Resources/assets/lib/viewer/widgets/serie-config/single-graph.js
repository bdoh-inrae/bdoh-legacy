/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
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

        // couleur des lignes (en fonction du style)
        options.color = this._getColor(serie, serie.style.color[0], serie.style.color[1], serie.style.color[2]);

        // épaisseur des lignes (en fonction du style)
        options.lineWidth = serie.style.thickness;

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
