// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Les lacunes et les mesures invalides d'une série, en bandes très pâles sur toute la hauteur du graphique, au lieu d'un
// trait épais posé au bas des valeurs (2026-11-11, dev/differences.md, « Graphique d'une chronique »). Une bande va du
// premier au dernier point de chaque morceau de la série, que le serveur sépare par des points vides. La hauteur du
// graphique est celle du canevas de la série, qui couvre la grille : l'axe inversé des pluies n'y change rien.
define(['jquery', '@IrsteaBdohBundle/lib/jqplot'], ($) => {
    'use strict';

    const _super = $.jqplot.LineRenderer;

    function GapBandRenderer() {
        _super.apply(this, arguments);
    }

    GapBandRenderer.prototype = Object.create(_super.prototype);
    GapBandRenderer.prototype.constructor = GapBandRenderer;

    GapBandRenderer.prototype.draw = function (ctx, gridData) {
        const height = ctx.canvas.height;
        let start = null;
        let last = null;
        ctx.save();
        ctx.fillStyle = this.rendererOptions.fill;
        const flush = () => {
            if (start !== null) {
                // Une lacune d'un seul point reste visible : deux pixels au moins
                ctx.fillRect(Math.min(start, last), 0, Math.max(Math.abs(last - start), 2), height);
            }
            start = null;
        };
        // Seule la date compte : sur une période sans valeur valide, le serveur n'a pas de hauteur à donner aux lacunes
        gridData.forEach(([x]) => {
            if (x === null || Number.isNaN(x)) {
                flush();
                return;
            }
            if (start === null) {
                start = x;
            }
            last = x;
        });
        flush();
        ctx.restore();
    };

    return GapBandRenderer;
});
