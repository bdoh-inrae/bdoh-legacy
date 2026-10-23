/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
const moment = require('@IrsteaBdohBundle/lib/moment');
require('../../lib/viewer/simple');

$(() => {
    const viewer = $('#viewer');

    viewer.simpleViewer({
        id: viewer.data('chronique-id'),
        minDate: viewer.data('min-date'),
        maxDate: viewer.data('max-date'),
        measureURL: viewer.data('measure-url')
    });

    const modal = $('[data-export-modal]');

    viewer.find('.graph-export-button')
        .onAsObservable('click')
        .flatMap(() => viewer.simpleViewer('getSelectedDateRange').first())
        .subscribe((range) => {
            const boundedStart = moment(modal.data('min-date'));
            const boundedEnd = moment(modal.data('max-date'));
            range.start=moment.max(range.start, boundedStart);
            range.end=moment.min(range.end, boundedEnd);
            modal.one('shown.bs.modal', () => modal.exportModal('setDateRange', range));
            modal.modal('show');
        });

    $(document).on('click', '[href="#measures-display"][data-begin][data-end]', (ev) => {
        const el = $(ev.currentTarget);
        viewer.simpleViewer('setDateRange', el.data('begin'), el.data('end'), true);
    });
});
