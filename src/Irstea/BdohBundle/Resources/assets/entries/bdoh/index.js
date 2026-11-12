// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
const $ = require('jquery');

require('babel-polyfill');
require('bootstrap');
require('datatables.net');
require('translator');

require('datatables.net-bs/css/dataTables.bootstrap.css');
require('toastr/build/toastr.css');

require('./toastr');
require('./template-polyfill');
require('./row-links');
require('./style.less');

$(() => {
    $('[data-toggle="tooltip"]').tooltip();
});
