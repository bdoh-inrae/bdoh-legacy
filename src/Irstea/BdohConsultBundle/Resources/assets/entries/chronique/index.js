const $ = require('jquery');
const initFillingRates = require('./filling-rates');

require('./cut-modal');
require('./export-modal');
//require('./jeux-baremes');
require('./viewer-graph');
require('./style.css');

$(() => {

    // contournement d'un bug de jqplot sur l'utilisation de window.getSelection
    // potentiellement dangereux pour d'autres fonctionnalité (penser à virer au besoin)
    window.getSelection = null;

    initFillingRates('#fillingRates');
    $('sup.checkpointsInfo').tooltip();
    $('a.viewChroniqueMere').tooltip();
});
