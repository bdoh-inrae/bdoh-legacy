/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');

/* jqplot n'étant pas "module-compatible", il faut inclure les fichiers dans le bon ordre
 * pour respecter les dépendances.
 * cf. src/Irstea/BdohBundle/Resources/scripts/jqplot-deps.js
 */
require('jqplot/src/jqplot.core');
require('jqplot/src/jqplot.linePattern');
require('jqplot/src/jqplot.shadowRenderer');
require('jqplot/src/jqplot.shapeRenderer');
require('jqplot/src/jqplot.sprintf');
require('jqplot/src/jqplot.axisTickRenderer');
require('jqplot/src/jqplot.lineRenderer');
require('jqplot/src/plugins/jqplot.canvasTextRenderer');
require('jqplot/src/plugins/jqplot.barRenderer');
require('jqplot/src/plugins/jqplot.canvasAxisTickRenderer');
require('jqplot/src/jqplot.axisLabelRenderer');
require('jqplot/src/jqplot.linearTickGenerator');
require('jqplot/src/jqplot.linearAxisRenderer');
require('jqplot/src/plugins/jqplot.BezierCurveRenderer');
require('jqplot/src/jqplot.markerRenderer');
require('jqplot/src/jqplot.tableLegendRenderer');
require('jqplot/src/plugins/jqplot.enhancedLegendRenderer');
require('jqplot/src/plugins/jqplot.donutRenderer');
require('jqplot/src/plugins/jqplot.funnelRenderer');
require('jqplot/src/plugins/jqplot.meterGaugeRenderer');
require('jqplot/src/plugins/jqplot.pieRenderer');
require('jqplot/src/plugins/jqplot.highlighter');
require('jqplot/src/plugins/jqplot.highlightingCursor');
require('jqplot/src/jqplot.divTitleRenderer');
require('jqplot/src/jqplot.themeEngine');
require('jqplot/src/jqplot.canvasGridRenderer');
require('jqplot/src/plugins/jqplot.canvasAxisLabelRenderer');
require('jqplot/src/plugins/jqplot.cursor');
require('jqplot/src/jquery.jqplot.css');

$.jqplot.preInitHooks.push(function () {
     this.drawIfHidden = true;
});

module.exports = $.jqplot;
