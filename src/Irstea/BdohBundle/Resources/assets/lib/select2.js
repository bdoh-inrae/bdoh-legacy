/*
 * © 2016-2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
const select2 = require('select2');
require('select2/dist/css/select2.css');
require('select2-bootstrap-theme/dist/select2-bootstrap.css');

$.fn.select2.defaults.set('theme', 'bootstrap');

module.exports = select2;
