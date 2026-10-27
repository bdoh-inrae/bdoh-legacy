/**
 * Step 3 : validation of import
 */
define([
    'jquery',
    './main'
], ($) => {

    $.measureImport.step3 = function () {
        const measureImport = $.measureImport.init();
        const data = measureImport.data;
        const form = measureImport.form;
        const importTypes = measureImport.form.find('input[type = "radio"]');
        const stateOfSubmit = measureImport.stateOfSubmit(() => {
            return importTypes.filter(':checked').length === data.numChroniquesToValidate;
        });

        // Enables or not the "submit button"
        importTypes.on('click', stateOfSubmit);

        // Displays, in a bootstrap-popover, a help for the dates overlaps
        $('.overlapHelp').popover({trigger: 'hover'});

        // Displays, in a bootstrap-popover, the list of the descendant time series
        $('.childrenHelp').popover({trigger: 'hover', 'html': true});

        // Send 'importType' to the server
        form.ajaxForm({
            dataType: 'json',
            beforeSend() {
                $('#modalStep3')
                    .modal({backdrop: 'static'})
                    .modal('show');
            },
            success(data) {
                $('#modalStep3')
                    .modal('hide')
                    .one('hidden.bs.modal', () => {
                        measureImport.nextStep(data, () => {
                        });
                    });
            }
        });
    };

});
