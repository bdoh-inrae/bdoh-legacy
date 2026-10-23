const $ = require('jquery');
require('@IrsteaBdohBundle/lib/date-time/range-picker');

$(() => {
    $('[data-cut-modal]').each((_i, dom) => {
        const modal = $(dom);
        const submitButton = modal.find('input[type="submit"]');

        modal.one('show.bs.modal', () => {
            const picker = modal.find('.daterange');

            picker.dateTimeRangePicker({
                minDate: modal.data('debut'),
                maxDate: modal.data('fin')
            });

            picker.dateTimeRangePicker('observeStatus')
                .subscribe((status) => {
                    submitButton
                        .toggleClass('btn-danger', status.isValid)
                        .attr('disabled', !status.isValid)
                        .prop('disabled', !status.isValid);
                });

            // Displays, in a bootstrap-popover, the list of the descendant time series
            $('.childrenHelp').popover({trigger: 'hover', 'html': true});
        });
    });
});
