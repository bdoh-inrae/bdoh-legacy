/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define(['jquery', '@IrsteaBdohBundle/lib/jqplot'], ($) => {
    'use strict';

    const formatColor = function (r, g, b, a) {
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    };

    const _super = $.jqplot.BarRenderer;

    function CumulativeSerieRenderer() {
        _super.apply(this, arguments);
    }

    CumulativeSerieRenderer.prototype = Object.create(_super.prototype);
    CumulativeSerieRenderer.prototype.constructor = CumulativeSerieRenderer;

    CumulativeSerieRenderer.prototype.draw = function (ctx, gridData, options) {
        const opts = $.extend({}, options);
        const xaxis = this._xaxis;
        const cumulativeSeries = xaxis._series.filter((serie) => {
                return serie.show && serie.renderer instanceof CumulativeSerieRenderer;
            });
        const chroniqueIds = [];
        cumulativeSeries.forEach((serie, i) => {
            if(!chroniqueIds.includes(serie.chroniqueId)){
                chroniqueIds.push(serie.chroniqueId);
            }
        });
        const barIndex = chroniqueIds.findIndex(id => id === this.rendererOptions.chroniqueId);
        const ahead = this.rendererOptions.ahead;
        const stepWidth = xaxis.series_u2p(xaxis.min + 1000 * this.rendererOptions.timeStep);
        const numBars = chroniqueIds.length;
        const barWidth = stepWidth / numBars;
        const barOffset = barIndex * barWidth;
        const shapeRenderer = this.renderer.shapeRenderer;
        const points = [[null, null], [null, null], [null, null], [null, null]];
        const yaxis = this._yaxis;
        // yOffset permet de corriger la correction (!) faite en amont pour les graphes inversés.
        const yOffset = yaxis.u2p(yaxis.max);
        const zero = yaxis.u2p(0);

        gridData.forEach((data) => {
            const x = data[0];
            const y = data[1];

            if (x === null || y === null) {
                return;
            }

            const left = x + barOffset - (ahead ? 0.0 : stepWidth);
            const right = left + barWidth;
            const top = Math.min(zero, y + yOffset);
            const bottom = Math.max(zero, y + yOffset);

            // Ces points doivent être ordonnées dans le sens des aiguilles d'une montre.
            points[0] = [left, bottom];
            points[1] = [left, top];
            points[2] = [right, top];
            points[3] = [right, bottom];

            shapeRenderer.draw(ctx, points, opts);
        });
    };

    return CumulativeSerieRenderer;
});
