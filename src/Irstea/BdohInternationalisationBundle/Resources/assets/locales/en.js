const _ = require('lodash');

// BazingaJsTranslation
const Translator = require('translator');
Translator.fromJSON(
    _.merge(
        require('../translations/config.json'),
        require('../translations/en.json')
    )
);

// Datatables
const dt = require('datatables.net');
//dt.defaults.language = require('drmonty-datatables-plugins/i18n/English.json');
dt.defaults.language = require('../DataTables/English.json');

// Globals
module.exports = 'en';
global.locale = 'en';
window.locale = 'en';
