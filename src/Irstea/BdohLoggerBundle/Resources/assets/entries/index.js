/**
 * Javascript definitions for 'IrsteaBdohLoggerBundle:Main:index.html.twig' template.
 */
const $ = require('jquery');
require('@IrsteaBdohBundle/lib/select2');
require('@IrsteaBdohBundle/lib/datatables');

$(() => {
    $('.log-tab').each((_, tab) => {
        const $tab = $(tab);
        const selector = `#${tab.id}`;
        const init = () => {
            // dernières connexions -> tri par date desc sur la colonne 1
            $tab.find('.table.last-logins').DataTable({'order': [[ 1, 'desc' ]]});
            // logs normaux -> tri par date desc sur la colonne 2
            $tab.find('.table:not(.last-logins)').DataTable({'order': [[ 2, 'desc' ]]})
        };

        if ($tab.hasClass('active')) {
            init();
        } else {
            $(`a[data-toggle="tab"][href="${selector}"]`).one('shown.bs.tab', init);
        }
    });
    const $historiqueYear = $('#historiqueYear');
    if($historiqueYear) {
        $historiqueYear.select2({
            minimumResultsForSearch: Infinity
        });
        $historiqueYear.on('change', () => {
            window.location.href = `${$historiqueYear.data('url')}?year=${$historiqueYear.val()}`;
        });
    }
});
