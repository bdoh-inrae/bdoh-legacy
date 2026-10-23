/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    // --
    'jquery-ui/ui/widget',
    '../plot',
    './style.css'
], ($) => {

    //noinspection JSDuplicatedDeclaration
    return $.widget(
        'viewer.xAxis', {
            options: {
                default: {
                    axes: {
                        xaxis: {
                            rendererOptions: {
                                baselineColor: '#666666'
                            },
                            tickOptions: {
                                showGridline: false,
                                showMark: true,
                                showLabel: true,
                            }
                        },
                        yaxis: {
                            show: false,
                            tickOptions: {
                                show: false
                            }
                        }
                    },
                    title: {
                        show: false
                    },
                    legend: {
                        show: false
                    },
                    grid: {
                        drawBorder: false,
                        background: 'transparent',
                        shadow: false
                    },
                    gridPadding: {top: 0, bottom: 120, left: 50, right: 50}
                }
            },

            _create() {
                const plot = this.element.plot({ height: 120, default: this.options.default });

                this._disposables = this.options.config
                    .subscribe((config) => {
                        config.showLabel = false;
                        plot.plot(
                            'update',
                            'ok',
                            {axes: {xaxis: config}},
                            [[[config.min, null], [config.max, null]]]
                        )
                    });
            },

            _destroy() {
                this._disposables.dispose();
            },
        });
})
;
