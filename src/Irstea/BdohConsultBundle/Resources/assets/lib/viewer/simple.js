/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'translator',
    './base',
    'rx',
    './widgets/serie-config/single-graph',
    './widgets/graph',
], ($, Translator, baseViewer, Rx, SingleGraphSerieConfig) => {

    return $.widget(
        'viewer.simpleViewer',
        baseViewer,
        {
            options: {
                id: null,
                allowRangeOutOfBoundaries: true
            },

            _create() {
                this._super();

                const showControlPoints = new Rx.BehaviorSubject(true);

                this.element.find('.controlPoints').on('click', (ev) => {
                    showControlPoints.onNext(ev.target.checked);
                });

                const config = new SingleGraphSerieConfig();

                this._config  = showControlPoints.map(
                    checked => {
                        config.showControlPoints = checked;
                        return config;
                  });

                this._setupMeasures();
            },

            _setupMeasures() {
                const holder = new Rx.SerialDisposable(Rx.Disposable.empty);
                const id = this.options.id;

                this._measures = this._measureRepository
                    .flatMap((repo) => repo.getMeasures(id))
                    .do((measures) => holder.setDisposable(measures.acquire()))
                    .shareReplay(1);

                this._measures.subscribe((measures) => this._updateInfos(measures));

                this._measures.first().subscribeOnNext(() => this._setupGraph());
            },

            _updateInfos(measures) {
                let controlePoints = false;
                for(const serie of measures.series){
                    if(serie.type === 'controlPoints'){
                        controlePoints = true;
                        break;
                    }
                }
                let dataType = 'discontinuous';
                if(measures.samplingType === 'instantaneous'){
                    dataType = 'instantaneous';
                }
                if(measures.samplingType === 'mean'){
                    dataType = 'meanPast';
                    if(measures.ahead){
                        dataType = 'meanNext';
                    }
                }
                if(measures.samplingType === 'cumulative'){
                    dataType = 'cumulativePast';
                    if(measures.ahead){
                        dataType = 'cumulativeNext';
                    }
                }
                this.element
                    .find('.divTitleDataType')
                    .text(Translator.trans(`titleDataType.${dataType}`, {}, 'viewer'));
                this.element
                    .find('.divControlPoints')
                    .toggle(controlePoints);
                this.element
                    .find('.divCheckpointsOnCumulative')
                    .toggle(measures.checkpointsOnCumulative);
                this.element
                    .find('.divNoticeTimeStep')
                    .toggle(measures.samplingType === 'cumulative');
                this.element
                    .find('.divDummyFillerTitle')
                    .toggle(measures.samplingType !== 'cumulative' &&
                        (controlePoints || measures.checkpointsOnCumulative));
                this.element
                    .find('.divDummyFillerCheckpoints')
                    .toggle(measures.samplingType === 'cumulative' &&
                        (!controlePoints && !measures.checkpointsOnCumulative));
                this.element
                    .find('.divNoticeSubsampledText')
                    .html(Translator.trans('subsampledInfoHelp(%num%)', {'num':measures.validCount}, 'viewer'));
                this.element
                    .find('span.subsampledHelp')
                    .attr('title', Translator.trans('subsampledHelp', {}, 'viewer'))
                    .tooltip('fixTitle');
                this.element
                    .find('.divNoticeSubsampled')
                    .toggle(measures.downSampled);
            },

            _setupGraph() {
                const left = this._measures.map((measures) => [measures]);

                this.element
                    .find('.graph-infos')
                    .show();

                this.element
                    .find('.graph')
                    .show()
                    .graph({
                        xaxis: this._xAxisConfig,
                        left,
                        right: Rx.Observable.just([]),
                        serie_config: this._config,
                        reverseAxis: false,
                        showAdvanbcedLabel: true,
                        allowPadding: true,
                        plot: {
                            width: this.element.width(),
                            height: 520,
                            default: {
                                gridPadding: { left: 70, bottom: 170 },
                                axes: {
                                    xaxis: {
                                        tickOptions: {
                                            showGridline: true,
                                            showMark: true,
                                            showLabel: true
                                        }
                                    }
                                },
                                cursor: {
                                    constrainZoomTo: 'none'
                                },
                                legend: {
                                    show: true,
                                    location: 's',
                                    placement: 'outsideGrid',
                                    renderer:$.jqplot.EnhancedLegendRenderer,
                                    rendererOptions: {
                                        numberRows: 1,
                                        seriesToggle: 1
                                    }
                                }
                            }
                        }
                    });
            }
        }
    );
});
