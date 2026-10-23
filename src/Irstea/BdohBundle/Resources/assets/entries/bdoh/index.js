/*
 * © 2016-2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');

require('babel-polyfill');
require('bootstrap');
require('datatables.net');
require('translator');

require('datatables.net-bs/css/dataTables.bootstrap.css');
require('toastr/build/toastr.css');

require('./toastr');
require('./template-polyfill');
require('./style.less');

$(() => {
    $('[data-toggle="tooltip"]').tooltip();
});
