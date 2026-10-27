/**
 * Step 1 : file and file format
 */
define([
    'jquery',
    './main',
    './step2'
], ($) => {
    'use strict';

    $.measureImport.step1 = function () {
        let formatDescription = $('#format-description'),
            measureImport     = $.measureImport.init(),
            form              = measureImport.form,
            file              = measureImport.field('file'),
            fileFormat        = measureImport.field('file-format'),
            progressBar       = measureImport.main.find('.progress'),
            progression       = progressBar.children('.bar'),

            stateOfSubmit = measureImport.stateOfSubmit(() => {
                return (file.val() !== '' && fileFormat.val() !== '');
            });

        // When a format is selected, displays its description
        fileFormat.on('change', function () {
            let id    = $(':selected', this).val(),
                value = $(':selected', this).text();

            if (id === '') {
                // Hides description
                formatDescription.children('h4, ul').hide(0);
            } else {
                // Shows the good description
                formatDescription.find('h4 > i').html(value);
                formatDescription.children(`ul[name != "${id}"]`).hide(0);
                formatDescription.children(`ul[name  = "${id}"], h4`).show(0);
            }
        });

        // Enables or not the "submit button"
        file.add(fileFormat).on('change', stateOfSubmit);

        // Sends file and file format to the server
        form.ajaxForm({
            dataType : 'json',
            beforeSend () {
                // Displays progress bar if file > 1 Mio
                if (file[0].files[0] !== undefined && file[0].files[0].size > 1000000) {
                    progressBar.show(0);
                }
            },
            uploadProgress (event, position, total, percentComplete) {
                progression.width(`${percentComplete}%`);
            },
            complete (xhr) {
                progressBar.hide(0);
                progression.width('0%');
            },
            success (data) {
                const nextStep = (data.action === 'step2.askStation') ?
                               measureImport.step2.askStation :
                               measureImport.step2;
                measureImport.nextStep(data, nextStep);
            }
        });
    };

});
