/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx',
    'translator',
    './serie-config/base',
    './reversed-axis-renderer',
    // --
    'jquery-ui/ui/widget',
    './plot'
], ($, Rx, Translator, BaseSerieConfig, ReversedAxisRenderer) => {

    return $.widget(
        'viewer.graph', {
            options: {
                xaxis: null,
                left: null,
                right: null,
                serie_config: new BaseSerieConfig(),
                reverseAxis: true,
                showAdvanbcedLabel: false,
                allowPadding: false,
                plot: {}
            },

            _create() {
                const disposables = this._disposables = new Rx.CompositeDisposable();

                const processing = new Rx.BehaviorSubject(false);
                const startProcess = function () {
                        processing.onNext(true);
                    };
                const endProcess = function () {
                        processing.onNext(false);
                    };

                const xaxis = this.options.xaxis
                        .do(startProcess);
                const yaxis = this.options.left
                        .do(startProcess)
                        .map(this._prepareYAxis, this);
                const y2axis = this.options.right
                        .do(startProcess)
                        .map(this._prepareYAxis, this);
                const series = Rx.Observable
                        .combineLatest(
                            [this.options.left, this.options.right, this.options.serie_config],
                            (left, right, config) => {
                                return {yaxis: left, y2axis: right, config};
                            }
                        )
                        .do(startProcess)
                        .map(this._prepareSeries, this);
                const plot = this._plot = this.element.plot(this.options.plot);

                disposables.add(
                    Rx.Observable
                        .combineLatest(
                            [xaxis, yaxis, y2axis, series],
                            (xaxis, yaxis, y2axis, series) => {
                                if (xaxis.error || yaxis.error || y2axis.error || series.error) {
                                    return ['error', xaxis.error || yaxis.error || y2axis.error || series.error];
                                }
                                return [
                                    series.state,
                                    {
                                        axes: {xaxis, yaxis, y2axis},
                                        series: series.options,
                                    },
                                    series.values
                                ];
                            }
                        )
                        .debounce(100)
                        .do(endProcess)
                        .catch((reason) => {
                            return Rx.Observable.of(['error', reason, null]);
                        })
                        .subscribe((data) => {
                            plot.plot('update', data[0], data[1], data[2]);
                        }),

                    processing
                        .distinctUntilChanged()
                        .filter(Rx.helpers.identity)
                        .subscribe(() => {
                            plot.plot('update', 'processing');
                        })
                );
            },

            _destroy() {
                try {
                    this._disposables.dispose();
                } catch (e) {
                    // eslint-disable-next-line no-console
                    console.error(e);
                }
            },

            _getYAxisLabel(measures){
                let label = measures.dataType;
                let unit = measures.unit;
                if(unit){
                    if(measures.samplingType === 'cumulative') {
                        unit += `/${this._unitSymbolForTimeStep(measures.timeStep)}`;
                    }
                    unit = `[${unit}]`;
                }
                if(label && unit){
                    label += ' ';
                }
                label += unit;

                return label;
            },

            _unitSymbolForTimeStep(timeStep){
                const standardTimeSteps = [
                    [1, 's'],
                    [60, 'min'],
                    [3600, 'h'],
                    [86400, Translator.trans('dateDayShort', {}, 'messages')]
                ];
                let i = 0;
                let iMin = i;
                let dev = Math.abs(timeStep - standardTimeSteps[i][0]);
                let minDev = dev;
                for(i = 1; i < standardTimeSteps.length; ++i){
                    dev = Math.abs(timeStep - standardTimeSteps[i][0]);
                    if(dev < minDev){
                        minDev = dev;
                        iMin = i;
                    }
                }
                return standardTimeSteps[iMin][1];
            },

            _prepareYAxis(items) {
                if (items.length === 0) {
                    return {show: false};
                }
                const measures = items[0];
                const options = { label: `[${measures.unit}]` };
                if(this.options.showAdvanbcedLabel){
                    options.label=this._getYAxisLabel(measures);
                }
                if (this.options.reverseAxis && measures.reverseAxis) {
                    options.renderer = ReversedAxisRenderer;
                }
                options.newAxis = true;
                if(this.options.allowPadding
                    && (measures.samplingType === 'discontinuous'
                        || measures.chroniqueClass === 'ChroniqueConvertie')) {
                    options.pad = 1.1;
                }
                return options;
            },

            _prepareSeries(series) {
                const options = [];
                const values = [];
                const prepareSerie = this._prepareSerie.bind(this, values, options, series.config);
                let total = 0;
                let visible = 0;
                let withMeasures = 0;

                ['yaxis', 'y2axis'].forEach((yaxis) => {
                    const items = series[yaxis];
                    if (!(items instanceof Array)) {
                        return;
                    }
                    items.forEach((measures) => {
                        total++;
                        if (!measures.shown) {
                            return;
                        }
                        visible++;
                        if (measures.isEmpty() || !measures.series) {
                            return;
                        }
                        withMeasures++;

                        measures.series.forEach((serie) => {
                            prepareSerie(yaxis, serie);
                        });
                    });
                });

                let state = 'ok';
                if (total === 0) {
                    state = 'empty';
                } else if (visible === 0) {
                    state = 'all-hidden';
                } else if (withMeasures === 0) {
                    state = 'no-measures';
                }

                return {options, values, state};
            },

            _prepareSerie(values, options, serieConfig, yaxis, serie) {
                if (!serieConfig.shouldDraw(serie)) {
                    return;
                }

                const serieOptions = {
                    index: values.length,
                    yaxis,
                    markerOptions: { style: 'x', shadow: false, lineWidth: 1, size: 7.5 }
                };
                serieConfig.configure(serieOptions, serie);

                values.push(serie.values);
                options.push(serieOptions);
            }
        });
});
