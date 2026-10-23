/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
require('@IrsteaBdohBundle/lib/datatables');

module.exports = function initFillingRates(selector) {
    $(selector).DataTable({
        autoWidth: false,
        searching: false,
        order: [[0, 'desc']],
        pageLength: 5,
        lengthMenu: [5, 10, 25, 50, 100],
        columnDefs: [
            {orderable: false, targets: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]},
        ],
    });
};
