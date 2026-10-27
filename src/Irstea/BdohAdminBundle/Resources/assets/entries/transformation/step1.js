/**
 * Step 1 : file and file format
 */
define([
    'jquery',
    './import'
], ($) => {
    'use strict';

    $.baremeImport.step1 = function () {
        let baremeImport     = $.baremeImport.init(),
            form              = baremeImport.form,
            file              = baremeImport.field('file'),

            stateOfSubmit = baremeImport.stateOfSubmit(() => {
                return (file.val() !== '');
            });

            // Enables or not the "submit button"
            file.on('change', stateOfSubmit);

        // Sends file and file format to the server
        form.ajaxForm({
            dataType : 'json',
            success (data) {
                const nextStep = baremeImport.step2;
                baremeImport.nextStep(data, nextStep);
            }
        });
    };

});
