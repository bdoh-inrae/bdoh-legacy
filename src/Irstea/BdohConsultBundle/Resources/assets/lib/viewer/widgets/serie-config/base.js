/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    '../cumulative-serie-renderer'
], ($, CumulativeSerieRenderer) => {
    'use strict';

    function BaseSerieConfig() {
    }

    BaseSerieConfig.prototype.shouldDraw = function (serie) {
        return serie.type !== 'controlPoints';
    };

    BaseSerieConfig.prototype.configure = function (options, serie) {
        this._applySerieType(options, serie);
        this._applyRendererType(options, serie);
    };

    BaseSerieConfig.prototype._applySerieType = function (options, serie) {
        // sauvegarde du type de la série dans les options du graphe
        // pour la gestion du zoom en Y sur les invalides et les gaps
        options.bdohSerieType = serie.type;

        // enregistrement du label pour la legende du graphe simple
        options.label = serie.label;
        options.showLabel = serie.showLabel;

        // desactivation du tooltip sur les invalides et les gaps
        if (serie.type === 'invalid' || serie.type === 'gap') {
            options.showHighlight = false;
        }

        // couleur des lignes (par défaut)
        options.color = this._formatColor(0, 0, 255); // bleu #0000FF
        if (serie.type === 'invalid' || serie.type === 'gap') {
            options.color = this._formatColor(255, 0, 0); // rouge #FF0000
        }

        // épaisseur des lignes (par défaut)
        options.lineWidth = 1;
        if (serie.type === 'invalid' || serie.type === 'gap') {
            options.lineWidth = 5;
        }

        // marqueurs des bornes des plages des discontinues (par défaut)
        if (serie.type !== 'invalid' && serie.type !== 'gap' &&
            serie.measures.samplingType === 'discontinuous') {
            options.showMarker = true;
            options.markerOptions.show = true;
            options.markerOptions.shadow = false;
            options.markerOptions.style = 'filledCircle';
            options.markerOptions.lineWidth = 2;
            options.markerOptions.size = 5;
        }
    };

    BaseSerieConfig.prototype._getColor = function (serie, r, g, b) {
        return this._formatColor(r, g, b, 1.0);
    };

    BaseSerieConfig.prototype._formatColor = function (r, g, b, a) {
        return (
            (typeof a === 'number')
                ? `rgba(${r}, ${g}, ${b}, ${a})`
                : `rgb(${r}, ${g}, ${b})`
        );
    };

    BaseSerieConfig.prototype._applyRendererType = function (options, serie) {
        if (serie.type !== 'invalid' &&
            serie.type !== 'gap' &&
            serie.type !== 'controlPoints' &&
            serie.measures.samplingType === 'cumulative') {
            $.extend(options, {
                renderer: CumulativeSerieRenderer,
                rendererOptions: {
                    barPadding: 0,
                    barMargin: 0,
                    fillToZero: true,
                    timeStep: serie.measures.timeStep,
                    ahead: serie.measures.ahead,
                    chroniqueId: serie.measures.id,
                }
            });
        }
    };

    return BaseSerieConfig;
});
