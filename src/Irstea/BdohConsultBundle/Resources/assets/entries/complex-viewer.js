/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
require('../lib/viewer/complex');

$(() => {

    // contournement d'un bug de jqplot sur l'utilisation de window.getSelection
    // potentiellement dangereux pour d'autres fonctionnalité (penser à virer au besoin)
    window.getSelection = null;

    const viewer = $('#complex-viewer');
    viewer.complexViewer({
        metadataURL: viewer.data('metadata-url'),
        measureURL: viewer.data('measure-url'),
        minDate: viewer.data('min-date'),
        maxDate: viewer.data('max-date')
    });
});
