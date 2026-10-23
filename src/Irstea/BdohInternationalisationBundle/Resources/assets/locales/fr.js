const _ = require('lodash');

// BazingaJsTranslation
const Translator = require('translator');
Translator.fromJSON(
    _.merge(
        require('../translations/config.json'),
        require('../translations/fr.json')
    )
);

// Moment
require('moment/locale/fr');

// Datatables
const dt = require('datatables.net');
//dt.defaults.language = require('drmonty-datatables-plugins/i18n/French.json');
dt.defaults.language = require('../DataTables/French.json');

// Globals
module.exports = 'fr';
global.locale = 'fr';
window.locale = 'fr';
