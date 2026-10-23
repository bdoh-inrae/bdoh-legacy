/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
const _ = require('lodash');

/* Ce script permet de calculer l'order dans lequel importer les fichiers jqplot en
 * tenant compte des dépendances.
 *
 * Usage: node src/Irstea/BdohBundle/Resources/scripts/jqplot-deps.js
 */

// Jqplot ...
const deps = {
    'jqplot/src/jqplot.core': [],
    'jqplot/src/jqplot.axisLabelRenderer': ['jqplot/src/jqplot.core'],
    'jqplot/src/jqplot.axisTickRenderer': ['jqplot/src/jqplot.core', 'jqplot/src/jqplot.sprintf'],
    'jqplot/src/jqplot.canvasGridRenderer': ['jqplot/src/jqplot.shadowRenderer'],
    'jqplot/src/jqplot.divTitleRenderer': ['jqplot/src/jqplot.core'],
    'jqplot/src/jqplot.linePattern': ['jqplot/src/jqplot.core'],
    'jqplot/src/jqplot.lineRenderer': ['jqplot/src/jqplot.shadowRenderer', 'jqplot/src/jqplot.shapeRenderer'],
    'jqplot/src/jqplot.linearAxisRenderer': ['jqplot/src/jqplot.axisLabelRenderer', 'jqplot/src/jqplot.linearTickGenerator', 'jqplot/src/plugins/jqplot.barRenderer', 'jqplot/src/plugins/jqplot.canvasAxisTickRenderer'],
    'jqplot/src/jqplot.linearTickGenerator': ['jqplot/src/jqplot.sprintf'],
    'jqplot/src/jqplot.markerRenderer': ['jqplot/src/jqplot.shadowRenderer', 'jqplot/src/jqplot.shapeRenderer'],
    'jqplot/src/jqplot.shadowRenderer': ['jqplot/src/jqplot.linePattern'],
    'jqplot/src/jqplot.shapeRenderer': ['jqplot/src/jqplot.linePattern'],
    'jqplot/src/jqplot.sprintf': ['jqplot/src/jqplot.core'],
    'jqplot/src/jqplot.tableLegendRenderer': ['jqplot/src/plugins/jqplot.BezierCurveRenderer'],
    'jqplot/src/jqplot.themeEngine': ['jqplot/src/jqplot.core', 'jqplot/src/plugins/jqplot.donutRenderer', 'jqplot/src/plugins/jqplot.funnelRenderer', 'jqplot/src/plugins/jqplot.meterGaugeRenderer', 'jqplot/src/plugins/jqplot.pieRenderer'],
    'jqplot/src/jqplot.toImage': ['jqplot/src/jqplot.core'],
    'jqplot/src/jqplot.effects.core': ['jqplot/src/jqplot.core'],
    'jqplot/src/jqplot.effects.blind': ['jqplot/src/jqplot.effects.core'],

    // ... et ses plugins. Cette a été obtenue avec la commande: dev/jsDeps.pl web/vendor/jqplot/ '$.jqplot' 'jqplot/'
    'jqplot/src/plugins/jqplot.BezierCurveRenderer': ['jqplot/src/jqplot.linearAxisRenderer'],
    'jqplot/src/plugins/jqplot.barRenderer': ['jqplot/src/jqplot.lineRenderer'],
    'jqplot/src/plugins/jqplot.blockRenderer': ['jqplot/src/jqplot.lineRenderer'],
    'jqplot/src/plugins/jqplot.bubbleRenderer': ['jqplot/src/jqplot.linearAxisRenderer'],
    'jqplot/src/plugins/jqplot.canvasAxisLabelRenderer': ['jqplot/src/jqplot.core', 'jqplot/src/plugins/jqplot.canvasTextRenderer'],
    'jqplot/src/plugins/jqplot.canvasAxisTickRenderer': ['jqplot/src/jqplot.axisTickRenderer', 'jqplot/src/plugins/jqplot.canvasTextRenderer'],
    'jqplot/src/plugins/jqplot.canvasOverlay': ['jqplot/src/jqplot.markerRenderer', 'jqplot/src/jqplot.sprintf'],
    'jqplot/src/plugins/jqplot.canvasTextRenderer': ['jqplot/src/jqplot.core'],
    'jqplot/src/plugins/jqplot.categoryAxisRenderer': ['jqplot/src/jqplot.linearAxisRenderer'],
    'jqplot/src/plugins/jqplot.ciParser': ['jqplot/src/plugins/jqplot.json2'],
    'jqplot/src/plugins/jqplot.cursor': ['jqplot/src/jqplot.tableLegendRenderer'],
    'jqplot/src/plugins/jqplot.dateAxisRenderer': ['jqplot/src/jqplot.linearAxisRenderer'],
    'jqplot/src/plugins/jqplot.donutRenderer': ['jqplot/src/jqplot.tableLegendRenderer'],
    'jqplot/src/plugins/jqplot.dragable': ['jqplot/src/jqplot.markerRenderer'],
    'jqplot/src/plugins/jqplot.enhancedLegendRenderer': ['jqplot/src/jqplot.tableLegendRenderer'],
    'jqplot/src/plugins/jqplot.enhancedPieLegendRenderer': ['jqplot/src/jqplot.tableLegendRenderer'],
    'jqplot/src/plugins/jqplot.funnelRenderer': ['jqplot/src/jqplot.tableLegendRenderer'],
    'jqplot/src/plugins/jqplot.highlighter': ['jqplot/src/jqplot.markerRenderer', 'jqplot/src/jqplot.sprintf', 'jqplot/src/plugins/jqplot.barRenderer'],
    'jqplot/src/plugins/jqplot.highlightingCursor': ['jqplot/src/plugins/jqplot.highlighter'],
    'jqplot/src/plugins/jqplot.json2': [],
    'jqplot/src/plugins/jqplot.logAxisRenderer': ['jqplot/src/jqplot.linearAxisRenderer'],
    'jqplot/src/plugins/jqplot.mekkoAxisRenderer': ['jqplot/src/jqplot.axisLabelRenderer', 'jqplot/src/plugins/jqplot.canvasAxisTickRenderer'],
    'jqplot/src/plugins/jqplot.mekkoRenderer': ['jqplot/src/jqplot.shapeRenderer', 'jqplot/src/plugins/jqplot.mekkoAxisRenderer'],
    'jqplot/src/plugins/jqplot.meterGaugeRenderer': ['jqplot/src/jqplot.tableLegendRenderer'],
    'jqplot/src/plugins/jqplot.mobile': ['jqplot/src/jqplot.core'],
    'jqplot/src/plugins/jqplot.ohlcRenderer': ['jqplot/src/jqplot.lineRenderer'],
    'jqplot/src/plugins/jqplot.pieRenderer': ['jqplot/src/jqplot.tableLegendRenderer'],
    'jqplot/src/plugins/jqplot.pointLabels': ['jqplot/src/jqplot.axisTickRenderer', 'jqplot/src/plugins/jqplot.barRenderer'],
    'jqplot/src/plugins/jqplot.pyramidAxisRenderer': ['jqplot/src/jqplot.linearAxisRenderer', 'jqplot/src/plugins/jqplot.pyramidRenderer'],
    'jqplot/src/plugins/jqplot.pyramidGridRenderer': ['jqplot/src/jqplot.canvasGridRenderer'],
    'jqplot/src/plugins/jqplot.pyramidRenderer': ['jqplot/src/jqplot.lineRenderer', 'jqplot/src/plugins/jqplot.pyramidGridRenderer'],
    'jqplot/src/plugins/jqplot.trendline': ['jqplot/src/jqplot.lineRenderer']
};

const queue = [
    'jqplot/src/jqplot.core',
    'jqplot/src/jqplot.axisLabelRenderer',
    'jqplot/src/jqplot.axisTickRenderer',
    'jqplot/src/jqplot.canvasGridRenderer',
    'jqplot/src/jqplot.divTitleRenderer',
    'jqplot/src/jqplot.lineRenderer',
    'jqplot/src/jqplot.linearAxisRenderer',
    'jqplot/src/jqplot.markerRenderer',
    'jqplot/src/jqplot.tableLegendRenderer',
    'jqplot/src/jqplot.themeEngine',
    'jqplot/src/plugins/jqplot.canvasAxisLabelRenderer',
    'jqplot/src/plugins/jqplot.canvasAxisTickRenderer',
    'jqplot/src/plugins/jqplot.barRenderer',
    'jqplot/src/plugins/jqplot.highlightingCursor',
    'jqplot/src/plugins/jqplot.cursor'
];

const orders = {};
for (const mod of queue) {
    orders[mod] = 1;
}

while (queue.length > 0) {
    const mod = queue.shift();
    const depMinOrder = orders[mod] + 1;
    for (const dep of deps[mod]) {
        if (!orders[dep] || depMinOrder > orders[dep]) {
            orders[dep] = depMinOrder;
            queue.push(dep);
        }
    }
}

const all = _.keys(orders);
all.sort((a, b) => orders[b] - orders[a]);

for (const dep of all) {
    // eslint-disable-next-line no-console
    console.log(`require('${dep}');`);
}
