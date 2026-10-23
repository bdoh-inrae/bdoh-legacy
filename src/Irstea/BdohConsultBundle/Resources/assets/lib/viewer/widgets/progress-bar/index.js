/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx',
    'translator',
    // --
    'jquery-ui/ui/widget',
    './style.css'
], ($, Rx, Translator) => {

    return $.widget(
        'viewer.progressBar',
        {
            _create() {
                this.element.html(
                    `<div class="viewer-progress-bar big-spinner">\
                            <div class="background"></div>\
                            <div class="container">\
                                <h2>${Translator.trans('chart-loading', {}, 'viewer')}</h2 >\
                                <div class="progress">\
                                    <div class="progress-bar progress-bar-success"></div>\
                                </div>\
                            </div>\
                        </div>`);

                this.completedBar = this.element.find('.progress-bar-success');
                this._disposables = new Rx.CompositeDisposable(this.options.state.subscribe(this._update.bind(this)));
            },

            _destroy() {
                this._disposables.dispose();
            },

            _update(data) {
                const el = this.element;

                this.completedBar.width(`${data.progress * 100}%`);

                if (data.show) {
                    if (!el.is(':visible')) {
                        el.fadeIn();
                    }
                } else if (el.is(':visible')) {
                    el.fadeOut();
                }
            }
        }
    );
});
