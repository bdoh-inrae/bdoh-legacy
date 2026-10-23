define([
    'jquery',
    './style.css'
], ($) => {

    $(() => {
        $(document).on('mouseover.data-hover-tab', '[data-hover-tab]', (ev) => {
           $(ev.currentTarget).tab('show');
        });
    });

});
