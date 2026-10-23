/*
 * © 2016-2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
const _ = require('lodash');
const Translator = require('translator');

require('@IrsteaBdohBundle/lib/date-time/range-picker');
require('@IrsteaBdohBundle/lib/datatables');
require('@IrsteaBdohBundle/lib/select2');

$(() => {

    $('#filter-date-range').dateTimeRangePicker();

    let startDate = NaN;
    let endDate = NaN;

    const $table = $('#main-table').DataTable({
        initComplete: () => {
            $('#filter-date-range')
                .dateTimeRangePicker('observeStatus')
                .filter(_.property('isValid'))
                .debounce(100)
                .pluck('value')
                .subscribe(({ start, end }) => {
                    startDate = start.unix();
                    endDate = end.unix();
                    $table.draw();
                });

            $('.async').addClass('done');

            // modification du label de la recherche par mots-clés pour insérer une bulle d'aide
            const searchLabel = document.querySelector('#main-table_filter > label').firstChild;
            searchLabel.textContent = searchLabel.textContent
                .replace('\u00A0:', ' ')
                .replace(':', ' ');
            const $sup = $(`<sup title="${Translator.trans('searchInfo', {}, 'messages')}">(?)</sup>`);
            $sup.insertBefore('#main-table_filter > label > input');
            $sup.tooltip({container: $('#main-table')})
                .tooltip('fixTitle');
            $(`<span>${Translator.trans('deux_points', {}, 'messages')}</span>`)
                .insertBefore('#main-table_filter > label > input');
        }
    });

    $table.on('draw', () => {
        $('tr.group *[data-original-title]').each((i, e) => {
            $(e).tooltip({container:$('#main-table')})
                .tooltip('fixTitle');
        });
    });

    $.fn.DataTable.ext.search.push((_, fields) => {
        // Test en négatif parce que startDate et endDate valent NaN si elles ne sont pas définis.
        // Or une comparaison avec NaN retourne toujours faux.
        return !(fields[10] < startDate || fields[9] > endDate);
    });
});
