/**
 * Step 1 : file and file format
 */
define([
    'jquery',
    './main',
    './step2'
], ($) => {

    $.measureImport.step1 = function () {
        const measureImport = $.measureImport.init();
        const form = measureImport.form;
        const file = measureImport.field('file');
        const formatDescription = $('#format-description');
        const stateOfSubmit = measureImport.stateOfSubmit(() => {
            return (file.val() !== '');
        });

        // Enables or not the "submit button"
        file.on('change', stateOfSubmit);

        formatDescription.children('ul[name  = "controle"], h4').show(0);

        // Sends file and file format to the server
        form.ajaxForm({
            dataType: 'json',
            success(data) {
                const nextStep = measureImport.step2;
                measureImport.nextStep(data, nextStep);
            }
        });
    };

    $.measureExport.step1 = function () {
        const measureExport = $.measureExport.init();
        const chronique = measureExport.field('chronique');
        const timezone = measureExport.field('timezone');
        const stateOfSubmit = measureExport.stateOfSubmit(() => {
            return (chronique.val() !== '');
        });

        // Enables or not the "submit button"
        chronique.add(timezone).on('change', stateOfSubmit);
    };

});
