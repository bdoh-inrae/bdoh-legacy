/*
 * © 2016-2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const dt = require('datatables.net');

require('datatables.net-bs/js/dataTables.bootstrap');
require('datatables.net-bs/css/dataTables.bootstrap.css');

dt.defaults.dom = "<'row'<'col-sm-12'l><'col-sm-12'f>><'row'<'col-sm-24'tr>><'row'<'col-sm-10'i><'col-sm-14'p>>";
