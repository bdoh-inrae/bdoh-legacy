// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
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
