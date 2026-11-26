// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
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
                // L'allègement en peu de mots ; son explication dans la bulle du « i », avec le seuil que donne la page
                // (GRAPH_MAX_POINTS), moitié moins en moyennes, qui demandent deux points par mesure
                // (ChroniqueContinueRepository::seriesInstantaneousOrMeanSampling)
                const number = (n) => Number(n).toLocaleString(Translator.locale);
                const count = number(measures.validCount);
                const max = Number(this.element.data('max-points'));
                this.element
                    .find('.divNoticeSubsampledText')
                    .text(Translator.trans('subsampledShort(%num%)', {'num': count}, 'viewer'));
                this.element
                    .find('.divNoticeSubsampled .graph-info')
                    .attr('title', `${Translator.trans('subsampledInfo(%num%)', {'num': count}, 'viewer')} ${
                        Translator.trans('subsampledHelp(%max%,%half%)', {'max': number(max), 'half': number(Math.floor(max / 2))}, 'viewer')}`);
                this.element
                    .find('.divNoticeSubsampled')
                    .toggle(measures.downSampled);
            },

            _setupGraph() {
                const left = this._measures.map((measures) => [measures]);

                this.element
                    .find('.graph-status')
                    .show();

                // La légende, que jqPlot dessine sous le graphique à chaque tracé, passe dans la ligne d'état, à droite,
                // toujours à la même place ; ses clics, qui masquent une série, la suivent.
                const legendBox = this.element.find('.graph-legend')[0];
                const graphBox = this.element.find('.graph')[0];
                new MutationObserver(() => {
                    const legend = graphBox.querySelector('table.jqplot-table-legend');
                    if (legend) {
                        legendBox.replaceChildren(legend);
                    }
                }).observe(graphBox, {childList: true, subtree: true});

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
                                // Sous la grille, la place que prennent les dates et le titre de l'axe, mesurée par
                                // jqPlot (null) : écrites à la verticale, une date avec l'heure est plus longue, et une
                                // place fixe la laissait déborder sur la bande, dont elle couvrait les boutons. La
                                // hauteur du graphique ne change pas : la bande reste en place. À droite, les 10 pixels
                                // de la bande (overview, GRID_RIGHT)
                                gridPadding: { left: 70, right: 10, bottom: null },
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
                                // Dessinée dans la grille, que jqPlot ne réduit pas pour elle : elle passe aussitôt dans
                                // la ligne d'état (plus haut)
                                legend: {
                                    show: true,
                                    location: 's',
                                    placement: 'insideGrid',
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
