// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// Un chargement en cours (dev/plans/2026-11-11_interface.md, point 5 ; style dans entries/bdoh/busy.less) : le symbole
// de chargement, le même partout, qui n'apparaît que si le chargement dure. Une zone qui a déjà un contenu le garde,
// légèrement pâli, le symbole au milieu ; une zone vide (empty) n'a que le symbole, à la place du contenu à venir.
const $ = require('jquery');

const SPINNER = '<svg class="bdoh-spinner" viewBox="0 0 50 50" aria-hidden="true"><circle cx="25" cy="25" r="20"/></svg>';

module.exports = function busy(zone, on, empty = false) {
    $(zone).each((i, element) => {
        const $zone = $(element);
        $zone.removeClass('bdoh-busy bdoh-busy-empty').children('.bdoh-spinner').remove();
        if (on) {
            $zone.addClass(empty ? 'bdoh-busy-empty' : 'bdoh-busy').append(SPINNER);
        }
        $zone.attr('aria-busy', on ? 'true' : null);
    });
};
