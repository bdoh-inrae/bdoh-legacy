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
    const links = $(row).find('a.iconText').filter((i, a) => $(a).find('.fam-magnifier').length > 0
        && (a.offsetParent !== null || a.classList.contains('row-link-view')));
    return links.length === 1 ? links[0] : null;
}

// Une ligne reconnue le reste, et sa loupe, qu'elle remplace, se cache (appearance.less, .row-link-view). Une ligne non
// reconnue se réexamine : avant que DataTables ne les retire, les colonnes masquées d'un tableau portent une seconde loupe.
function mark(row) {
    if (row.dataset.rowLink !== 'yes') {
        const link = viewLink(row);
        if (!link) {
            return false;
        }
        row.dataset.rowLink = 'yes';
        link.classList.add('row-link-view');
    }
    return true;
}

// Dès le chargement, puis à chaque dessin d'un tableau (DataTables crée les en-têtes de station en dessinant) ; au
// survol, pour ce qui serait apparu entre-temps. Les lignes intérieures d'abord : une ligne qui contient une ligne
// reconnue ne l'est pas elle-même.
const markAll = (root) => $(root).find(ROWS).get().reverse().forEach((row) => {
    if (!$(row).find('[data-row-link="yes"]').length) {
        mark(row);
    }
});
$(() => markAll(document));
$(document).on('draw.dt', (event) => markAll(event.target));

$(document).on('mouseover', ROWS, function (event) {
    if (mark(this)) {
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
