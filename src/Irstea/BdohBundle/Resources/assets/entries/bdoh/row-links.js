// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// Les lignes d'une liste mènent à leur fiche : une ligne de tableau, ou une boîte de chronique (page d'une station), qui
// a un seul lien « Voir la fiche » visible (la loupe, macros.html.twig) suit ce lien quand on clique n'importe où sur
// elle. La loupe reste le lien qu'on atteint au clavier, et les autres liens et boutons de la ligne gardent leur rôle ;
// un texte qu'on sélectionne ne mène nulle part. Les lignes se reconnaissent au survol : un tableau que DataTables
// redessine (pages, filtres) n'a rien à refaire. Le style : appearance.less, [data-row-link].
const $ = require('jquery');

const ROWS = 'tbody > tr, div.even, div.odd';
const INTERACTIVE = 'a, button, input, select, textarea, label, summary, [role="button"], [contenteditable]';

function viewLink(row) {
    const links = $(row).find('a.iconText').filter((i, a) => $(a).find('.fam-magnifier').length > 0 && a.offsetParent !== null);
    return links.length === 1 ? links[0] : null;
}

$(document).on('mouseover', ROWS, function (event) {
    if (!this.dataset.rowLink) {
        this.dataset.rowLink = viewLink(this) ? 'yes' : 'no';
    }
    if (this.dataset.rowLink === 'yes') {
        event.stopPropagation();  // une seule ligne à la fois, la plus proche
    }
});

$(document).on('click', ROWS, function (event) {
    if ($(event.target).closest(INTERACTIVE).length || String(window.getSelection())) {
        return;
    }
    const link = viewLink(this);
    if (!link) {
        return;
    }
    event.stopPropagation();
    if (event.ctrlKey || event.metaKey || event.shiftKey) {
        window.open(link.href, '_blank', 'noopener');
    } else {
        window.location.assign(link.href);
    }
});
