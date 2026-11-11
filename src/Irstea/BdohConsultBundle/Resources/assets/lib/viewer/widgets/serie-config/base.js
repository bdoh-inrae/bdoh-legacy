// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
define([
    'jquery',
    '../cumulative-serie-renderer',
    '../gap-band-renderer'
], ($, CumulativeSerieRenderer, GapBandRenderer) => {
    'use strict';

    // Les lacunes et les invalides, en bandes très pâles sur toute la hauteur (gap-band-renderer.js) : gris pour une
    // lacune, ambre pour une mesure invalide. La légende montre la même teinte, un peu plus soutenue.
    const BANDS = {
        gap: {fill: 'rgba(110, 120, 130, 0.07)', legend: 'rgba(110, 120, 130, 0.3)'},
        invalid: {fill: 'rgba(230, 140, 20, 0.09)', legend: 'rgba(230, 140, 20, 0.4)'}
    };

    // Les couleurs des qualités, réglées dans l'administration, sont souvent des couleurs pures (bleu, vert ou jaune
    // vifs). Elles se dessinent dans la couleur la plus proche, par la teinte, d'une palette lisible sur blanc
    // (Tableau 10) : chaque qualité garde sa famille de couleur, sans rien changer aux réglages. Un gris reste tel quel.
    const PALETTE = [[0, [214, 39, 40]], [30, [255, 127, 14]], [58, [188, 189, 34]], [120, [44, 160, 44]],
        [185, [23, 190, 207]], [210, [31, 119, 180]], [275, [148, 103, 189]], [330, [227, 119, 194]]];

    function soften(r, g, b) {
        const max = Math.max(r, g, b) / 255;
        const min = Math.min(r, g, b) / 255;
        const light = (max + min) / 2;
        const sat = max === min ? 0 : (max - min) / (1 - Math.abs(2 * light - 1));
        if (sat < 0.25 || light < 0.12 || light > 0.92) {
            return [r, g, b];
        }
        const [R, G, B] = [r / 255, g / 255, b / 255];
        let hue;
        if (max === R) {
            hue = 60 * (((G - B) / (max - min)) % 6);
        } else if (max === G) {
            hue = 60 * ((B - R) / (max - min) + 2);
        } else {
            hue = 60 * ((R - G) / (max - min) + 4);
        }
        hue = (hue + 360) % 360;
        const distance = (h) => Math.min(Math.abs(h - hue), 360 - Math.abs(h - hue));
        return PALETTE.reduce((best, entry) => (distance(entry[0]) < distance(best[0]) ? entry : best))[1];
    }

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
            options.color = BANDS[serie.type].legend;
        }

        // épaisseur des lignes (par défaut)
        options.lineWidth = 1;

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
        const [sr, sg, sb] = soften(r, g, b);
        return this._formatColor(sr, sg, sb, 1.0);
    };

    BaseSerieConfig.prototype._formatColor = function (r, g, b, a) {
        return (
            (typeof a === 'number')
                ? `rgba(${r}, ${g}, ${b}, ${a})`
                : `rgb(${r}, ${g}, ${b})`
        );
    };

    BaseSerieConfig.prototype._bandFill = function (serie) {
        return BANDS[serie.type].fill;
    };

    BaseSerieConfig.prototype._applyRendererType = function (options, serie) {
        if (serie.type === 'invalid' || serie.type === 'gap') {
            $.extend(options, {
                renderer: GapBandRenderer,
                rendererOptions: {fill: this._bandFill(serie)},
                showMarker: false,
                shadow: false
            });
            return;
        }
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
