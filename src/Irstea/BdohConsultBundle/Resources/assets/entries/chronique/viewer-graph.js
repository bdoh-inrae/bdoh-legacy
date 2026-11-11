// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
const $ = require('jquery');
const moment = require('@IrsteaBdohBundle/lib/moment');
const setupOverview = require('../../lib/viewer/widgets/overview');
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

    // La bande de toute la chronique, sous le graphique, puis le graphique dès l'ouverture : sa dernière année, ou
    // toute la chronique si elle est plus courte (2026-11-11, dev/differences.md, « Graphique d'une chronique »)
    setupOverview(viewer.find('.graph-overview'), viewer);
    const first = moment.utc(viewer.data('min-date'));
    const last = moment.utc(viewer.data('max-date'));
    if (viewer.data('min-date') && viewer.data('max-date') && first.isValid() && last.isValid()) {
        const start = moment.max(first, last.clone().subtract(1, 'year'));
        viewer.simpleViewer('setDateRange', start.format('YYYY-MM-DDTHH:mm:ss[Z]'), last.format('YYYY-MM-DDTHH:mm:ss[Z]'), true);
    }
});
