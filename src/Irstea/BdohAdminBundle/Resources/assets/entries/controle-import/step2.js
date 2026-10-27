/**
 * Step 2 : validation of import
 */
define([
    'jquery',
    './main',
    '@IrsteaBdohBundle/lib/switch-default-list-widget',
    '@IrsteaBdohBundle/lib/finite-list-widget'
], ($) => {

    $.measureImport.step2 = function () {
        const measureImport = $.measureImport.init();
        const form = measureImport.form;
        const importTypes = measureImport.form.find('input[type = "radio"]');
        const stateOfSubmit = measureImport.stateOfSubmit(() => {
            return importTypes.filter(':checked').length !== 0;
        });

        // Enables or not the "submit button"
        importTypes.on('click', stateOfSubmit);

        // Displays, in a bootstrap-popover, a help for the dates overlaps
        $('.overlapHelp').popover({trigger: 'hover'});

        // Send 'importType' to the server
        form.ajaxForm({
            dataType: 'json',
            success(data) {
                measureImport.nextStep(data, () => {
                });
            }
        });
    };

});
