/*
 * © 2016-2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
const toastr = require('toastr');
require('toastr/build/toastr.css');

toastr.options = {
    containerId: 'notices',
    closeButton: true,
    toastClass: 'alert',
    iconClasses: {
        error: 'alert-danger',
        info: 'alert-info',
        success: 'alert-success',
        warning: 'alert-warning'
    },
    showMethod: 'slideDown',
    showEasing: 'swing',
    hideMethod: 'slideUp',
    hideEasing: 'swing',
    timeOut: 5000,
    extendedTimeOut: 1000
};

function runtimeError(msg, title) {
    try {
        toastr.error(msg, title, {timeOut: 300000, extendedTimeOut: 300000});
    } catch(more) {
        // eslint-disable-next-line no-console
        console.log(more);
    }
}

$(() => {
    window.onerror = (msg) => runtimeError(msg, 'Javascript error');
    $.Deferred.exceptionHook = () => null;

    $(document).ajaxError((_1, xhr, {type, url}) => {
        const title = `Request error: ${xhr.status} ${xhr.statusText}`;
        const debugUrl = xhr.getResponseHeader('X-Debug-Token-Link');
        let msg = `<b>Request:</b> ${type} ${url}`;
        if (debugUrl) {
            msg += `<br/><b>Debug:</b> <a href="${debugUrl}">${debugUrl}</a>`;
        } else {
            const rid = xhr.getResponseHeader('X-Request-Id');
            if (rid) {
                msg += `<br/><b>Request ID:</b> <code>${rid}</code>`;
            }
        }
        runtimeError(msg, title);
    });

    const notices = $('#notices').data('notices');
    $.each(notices, (level, notices) =>
        $.each(notices, (_, msg) => {
            if(toastr[level]) {
               toastr[level](msg, '', {showDuration: 0});
            }
        })
    );
});

module.exports = toastr;
