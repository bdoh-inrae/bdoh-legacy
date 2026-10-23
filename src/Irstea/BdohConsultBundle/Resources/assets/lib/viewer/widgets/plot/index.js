/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'translator',
    '@IrsteaBdohBundle/lib/moment',
    'lodash',
    '@IrsteaBdohBundle/lib/jqplot',
    './style.css',
], ($, Translator, moment, _) => {

    let serial = 0;

    function formatMesure(m) {
        m=parseFloat(m);
        if(m == 0){ // might be 0.0
            return 0;
        }else if(m < 0.1 || m > 10000){
            return m.toExponential(3);
        }else if(m < 1){
            return _.round(m, 4);
        }else if(m < 10){
            return _.round(m, 3);
        }else if(m < 100){
            return _.round(m, 2);
        }else if(m < 1000){
            return _.round(m, 1);
        }else if(m < 10000){
            return _.round(m, 0);
        }
    }

    function extractUnitFromLabel(label){
        const unit=label.match(/^.*?\[(.*?)\]/);
        if(unit){
            return unit.pop().replace('unite constructeur', 'u.c.');
        }
        return '';
    }

    const defaultPlotOptions = {
        axes: {
            xaxis: {
                tickOptions: {
                    showMarker: false,
                    showLabel: false,
                    showGridline: true
                },
            },
            yaxis: {
                labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
                pad: 1,
                tickOptions: {
                    showGridline: false
                }
            },
            y2axis: {
                labelRenderer: $.jqplot.CanvasAxisLabelRenderer,
                pad: 1,
                tickOptions: {
                    showGridline: false
                }
            }
        },
        title: {
            show: false
        },
        legend: {
            show: false
        },
        gridPadding: {top: 0, bottom: 0, left: 0, right: 0},
        seriesDefaults: {
            renderer: $.jqplot.LineRenderer,
            shadow: false,
            showLine: true,
            showLabel: true,
            breakOnNull: true,
            markerOptions: {
                show: false
            }
        },
        highlighter: {
            show: true,
            showToolTip: true,
            sizeAdjust: 8,
            showMarker: true,
            tooltipOffset: 5,
            useAxesFormatters: true,
            tooltipContentEditor:(str, seriesIndex, pointIndex, plot)=>{
                return `${moment(plot.data[seriesIndex][pointIndex][0]).utc()
                    .format(Translator.trans('formatDateTimeJS', {}, 'messages'))}, ${formatMesure(plot
                    .data[seriesIndex][pointIndex][1])}<span class="tooltip_unite"> ${extractUnitFromLabel(plot
                    .series[seriesIndex]._yaxis.label)}</span>`;
            }
        },
        cursor: {
            show: true,
            showTooltip: false,
            zoom: true,
            constrainZoomTo: 'x',
            dblClickReset: true
        },
        grid:{
            shadow: false
        },
        series: []
    };

    let initialValues = null;

    return $.widget(
        'viewer.plot',
        {
            options: {
                width: 750,
                height: 275,
                default: {},

                status: {
                    icons: {
                        'default': 'glyphicon glyphicon-alert',
                        'processing': 'glyphicon glyphicon-hourglass spinning',
                        'empty': 'glyphicon glyphicon',
                        'all-hidden': 'glyphicon glyphicon-eye-close',
                        'error': 'glyphicon glyphicon-fire'
                    },
                    classes: {
                        'default': 'alert alert-info',
                        'error': 'alert alert-danger'
                    }
                }
            },

            _create() {
                this._chartId = `chart${++serial}`;
                this._chartEl = $(`<div id="${this._chartId}"></div>`)
                    .width(this.options.width)
                    .height(this.options.height)
                    .appendTo(this.element);
                this._zoomed = false;
                this._zoomData = {};
                this._on(this._chartEl, {
                    jqplotZoom(ev, gridpos, datapos, chart, cursor) {
                        if(cursor.constrainZoomTo === 'none'){
                            this._zoomed = true;
                            const y1 = cursor._zoom.axes.start.yaxis;
                            const y2 = datapos.yaxis;
                            this._zoomData = {min: Math.min(y1, y2), max: Math.max(y1, y2)};
                        }
                        const start = cursor._zoom.axes.start.xaxis;
                        const end = datapos.xaxis;
                        ev.data = {start: Math.min(start, end), end: Math.max(start, end)};
                        this._trigger('zoom', ev, ev.data);
                    },
                    jqplotResetZoom(ev) {
                        this._zoomed = false;
                        this._zoomData = {};
                        this._trigger('zoomreset', ev);
                    }
                });
            },

            update(status, configOrMessage, values) {
                // On conserve la valeur d'origine pour le dezoom des gaps.
                if(initialValues===null && values !== undefined){
                    initialValues = JSON.parse(JSON.stringify(values));
                }
                this._chartEl.empty();
                if (status === 'ok') {
                    try {
                        if (configOrMessage.axes.yaxis) {
                            if (configOrMessage.axes.yaxis.newAxis) {
                                configOrMessage.axes.yaxis.newAxis = false;
                                this._zoomed = false;
                                this._zoomData = {};
                            }
                            if (this._zoomed) {
                                for(let idx=0; idx < configOrMessage.series.length; ++idx){
                                    if(configOrMessage.series[idx].bdohSerieType === 'invalid' ||
                                        configOrMessage.series[idx].bdohSerieType === 'gap'){
                                        for(let idx2=0; idx2 < values[idx].length; ++idx2){
                                            if(values[idx][idx2][0] !== null){
                                                if(values[idx][idx2][1] < this._zoomData.min){
                                                    values[idx][idx2][1] = this._zoomData.min;
                                                }
                                            }
                                        }
                                    }
                                }
                                configOrMessage.axes.yaxis.min = this._zoomData.min;
                                configOrMessage.axes.yaxis.max = this._zoomData.max;
                            } else {
                                for(let idx=0; idx < configOrMessage.series.length; ++idx){
                                    if(configOrMessage.series[idx].bdohSerieType === 'invalid' ||
                                        configOrMessage.series[idx].bdohSerieType === 'gap'){
                                        // On reset la valeur des gat et invalid lors du dezoom.
                                        if(initialValues !== null){
                                            for(let idx2=0; idx2 < values[idx].length; ++idx2){
                                                if(typeof initialValues[idx] !== 'undefined' && typeof initialValues[idx][idx2] !== 'undefined'){
                                                     values[idx][idx2][1]=initialValues[idx][idx2][1];
                                                }
                                            }
                                        }
                                    }
                                }

                                delete configOrMessage.axes.yaxis.min;
                                delete configOrMessage.axes.yaxis.max;
                            }
                        }
                        this._doPlot(values, configOrMessage);
                        return;
                    } catch(ex) {
                        if (ex instanceof Error && ex.message === 'No data specified') {
                            status = 'no-measures';
                        } else {
                            throw ex;
                        }
                    }
                }
                this._showStatus(status, configOrMessage);
            },

            _doPlot(values, config) {
                const finalOptions = $.extend(true, {}, defaultPlotOptions, this.options.default, config);
                this._chartEl.jqplot(values, finalOptions);
            },

            _showStatus(status, message) {
                const icons = this.options.status.icons;
                const classes = this.options.status.classes;
                this._chartEl.append(
                    $(
                        `${'<div class="chart-state">'
                        + '<div class="chart-state-inner '}${classes[status in classes ? status : 'default']}">`
                        + `<i class="${icons[status in icons ? status : 'default']}"></i> ${
                            Translator.trans(`graph.state.${status}`, {message}, 'viewer')
                            }</div></div>`
                    )
                        .width(this.options.width)
                        .height(this.options.height)
                );
            }
        });
});
