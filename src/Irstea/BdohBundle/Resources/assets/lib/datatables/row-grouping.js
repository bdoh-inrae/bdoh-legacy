/*
 * © 2016-2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');

$(document).on('init.dt.rowGrouping', '[data-group-by]', (ev) => {
    if (ev.namespace !== 'dt') {
        return;
    }
    const table = $(ev.target);
    const groupingColumn = table.data('group-by');
    const api = table.DataTable();
    const column = api.column(groupingColumn);
    const orderingColumn = $(column.header()).data('group-order') || groupingColumn;

    column.visible(false);
    api.order.fixed({pre: [[orderingColumn, 'asc']]});

    table.on('draw.dt.rowGrouping', () => {
        if (ev.namespace !== 'dt' || ev.target !== table[0]) {
            return;
        }
        const rows = $(api.rows({page: 'current'}).nodes());
        const span = api
            .columns()
            .visible()
            .count();
        let last = null;

        api
            .column(groupingColumn, {page: 'current'})
            .data()
            .each((item, i) => {
                if (last === item) {
                    return;
                }
                rows.eq(i).before(`<tr class="group"><td colspan="${span}">${item}</td></tr>`);
                last = item;
            });
    });
});
