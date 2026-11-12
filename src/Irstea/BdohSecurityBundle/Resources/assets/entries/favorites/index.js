// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// La page des favoris (Favorite/list.html.twig ; dev/plans/2026-11-12_favorites.md) : une étiquette coche son groupe,
// « Tout cocher » coche un observatoire, et « Exporter la sélection » ouvre la fenêtre d'export de cet observatoire pour
// ses favoris cochés, que le serveur rend (FavoriteController::exportAction) : celle de la page d'une chronique.
const $ = require('jquery');

require('@IrsteaBdohConsultBundle/entries/chronique/export-modal');

const boxes = () => $('#favorites-form input[name="favorites[]"]');

// Une étiquette coche exactement ses favoris, dans tous les observatoires
$(document).on('click', '[data-favorite-label]', function () {
    const label = this.dataset.favoriteLabel;
    boxes().each((i, box) => {
        box.checked = JSON.parse(box.dataset.labels || '[]').indexOf(label) >= 0;
    });
});

// « Tout cocher » coche les favoris d'un observatoire, ou les décoche s'ils l'étaient tous
$(document).on('click', '[data-favorite-check-all]', function () {
    const group = $(this).closest('.favorites-group').find('input[name="favorites[]"]');
    const all = group.length === group.filter(':checked').length;
    group.prop('checked', !all);
});

$(document).on('click', '[data-favorites-export]', function () {
    const button = this;
    const ids = $(button).closest('.favorites-group').find('input[name="favorites[]"]:checked')
        .map((i, box) => box.dataset.chronique).get();
    const target = $('#favorites-export');
    if (!ids.length) {
        target.empty().append($('<div class="alert alert-warning"></div>').text(button.dataset.nothingChecked));
        return;
    }
    const query = $.param({chroniques: ids});
    button.disabled = true;
    $.get(`${button.dataset.favoritesExport}?${query}`)
        .done((html) => {
            // Sans session (déconnecté dans un autre onglet), la réponse est la page de connexion, où mène le
            // rechargement
            if (html.indexOf('data-favorites-export-report') < 0) {
                window.location.reload();
                return;
            }
            // Une fenêtre précédente part avec son fond, pour qu'une seule soit dans la page
            target.find('.modal').modal('hide');
            target.html(html);
            const modal = target.find('[data-export-modal]');
            if (modal.length) {
                modal.exportModal({dateFirst: modal.data('min-date'), dateLast: modal.data('max-date')});
                modal.modal('show');
            } else {
                target[0].scrollIntoView({block: 'nearest'});
            }
        })
        .fail(() => window.location.reload())
        .always(() => {
            button.disabled = false;
        });
});
