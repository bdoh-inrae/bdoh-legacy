/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    // --
    '@IrsteaBdohBundle/lib/jqplot'
], ($) => {
    'use strict';

    const formatColor = function (r, g, b, a) {
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    };

    function ReversedAxisRenderer() {
        $.jqplot.LinearAxisRenderer.apply(this, arguments);
    }

    const _super = $.jqplot.LinearAxisRenderer.prototype;
    ReversedAxisRenderer.prototype = Object.create(_super);
    ReversedAxisRenderer.prototype.constructor = ReversedAxisRenderer;

    ReversedAxisRenderer.prototype.pack = function (pos, offsets) {
        pos = pos || {};
        offsets = offsets || this._offsets;
        return _super.pack.call(
            this,
            pos,
            {
                max: Math.max(offsets.max, offsets.min),
                min: Math.min(offsets.max, offsets.min)
            }
        );
    };

    return ReversedAxisRenderer;
});
