// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
const $ = require('jquery');
const select2 = require('select2');
require('select2/dist/css/select2.css');
require('select2-bootstrap-theme/dist/select2-bootstrap.css');

$.fn.select2.defaults.set('theme', 'bootstrap');

module.exports = select2;
