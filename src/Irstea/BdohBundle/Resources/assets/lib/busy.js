// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// Un chargement en cours (dev/plans/2026-11-11_interface.md, point 5 ; style dans entries/bdoh/busy.less) : le symbole
// de chargement, le même partout, qui n'apparaît que si le chargement dure. Une zone qui a déjà un contenu le garde,
// légèrement pâli, le symbole au milieu ; une zone vide (empty) n'a que le symbole, à la place du contenu à venir.
const $ = require('jquery');

// Le même que la macro spinner de templates/macros.html.twig
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

// Un bouton qui envoie : il garde son libellé, se désactive, et le symbole vient à côté si l'envoi dure, au lieu du
// « Chargement… » de Bootstrap (data-loading-text). Rétabli (on = false), il ne se réactive que si c'est d'ici qu'il a
// été désactivé : un bouton qu'un autre script désactive le reste.
module.exports.button = function busyButton(button, on = true) {
    $(button).each((i, element) => {
        const $button = $(element);
        $button.next('.bdoh-spinner').remove();
        if (on) {
            $button.data('bdohBusy', true);
            $(SPINNER).addClass('bdoh-spinner-inline').insertAfter($button);
            // Au tour suivant, comme Bootstrap : désactivé pendant l'envoi d'un formulaire, il en manquerait la valeur
            setTimeout(() => $button.data('bdohBusy') && $button.prop('disabled', true));
        } else if ($button.data('bdohBusy')) {
            $button.removeData('bdohBusy').prop('disabled', false);
        }
    });
};
