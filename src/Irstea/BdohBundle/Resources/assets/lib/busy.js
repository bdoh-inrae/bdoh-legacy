// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// Une zone qui charge : grisée et floutée sous un voile, sans rien déplacer ni rien ajouter d'autre, la même partout
// (dev/plans/2026-11-11_interface.md, point 5 ; style dans entries/bdoh/busy.less). Le voile est un élément à part :
// les ::before et ::after des blocs de Bootstrap servent déjà (clearfix).
const $ = require('jquery');

module.exports = function busy(zone, on) {
    $(zone).each((i, element) => {
        const $zone = $(element);
        $zone.toggleClass('bdoh-busy', on).attr('aria-busy', on ? 'true' : null);
        const veil = $zone.children('.bdoh-busy-veil');
        if (on && !veil.length) {
            $zone.append('<div class="bdoh-busy-veil"></div>');
        } else if (!on) {
            veil.remove();
        }
    });
};
