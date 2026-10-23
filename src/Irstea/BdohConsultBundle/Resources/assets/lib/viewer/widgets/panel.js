/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx',
    './serie-config/multi-graph',
    // --
    'jquery-ui/ui/widget',
    './y-axis',
    './graph'
], ($, Rx, MultiGraphSerieConfig) => {
    'use strict';

    const SIDES = {left: 0, right: 1};
    const filterSide = function (side, items) {
            return items.filter((item) => {
                return item.side === side;
            });
        };

    return $.widget(
        'viewer.panel', {
            _create() {
                const element = this.element;

                const subjects = this._subjects = [
                    new Rx.BehaviorSubject([]),
                    new Rx.BehaviorSubject([])
                ];

                const colorGen = new $.jqplot.ColorGenerator([
                    '#1f77b4',
                    '#ff7f0e',
                    '#2ca02c',
                    '#d62728',
                    '#9467bd',
                    '#8c564b',
                    '#e377c2',
                    '#7f7f7f',
                    '#bcbd22',
                    '#17becf'
                ]);

                const serialDisp = new Rx.SerialDisposable(Rx.Disposable.empty);

                this._chroMemory = [];

                const selections = this._selections = Rx.Observable
                        .combineLatest([
                          subjects[0],
                          subjects[1],
                          this.options.repository.flatMap(repo => repo.chroniques)
                        ])
                        .debounce(10)
                        .map(([left, right, chroniques]) => ([
                            left.map(({ id }) => chroniques[id]),
                            right.map(({ id }) => chroniques[id])
                          ]))
                        .publish();

                const chroniques = selections
                        .flatMap(this._prepareChroniques.bind(this, colorGen, serialDisp))
                        .map((items) => {
                            const bySide = {left: [], right: []};
                            items.forEach((item) => {
                                bySide[item.side].push(item);
                            });
                            return bySide;
                        })
                        .shareReplay(1);

                const yAxes = {
                        left: this._createYAxis('left', chroniques.pluck('left')),
                        right: this._createYAxis('right', chroniques.pluck('right'))
                    };

                element.find('.graph').graph({
                    xaxis: this.options.xaxis,
                    left: yAxes.left.yAxis('observeSelection'),
                    right: yAxes.right.yAxis('observeSelection'),
                    serie_config: Rx.Observable.of(new MultiGraphSerieConfig()),
                    plot: { default: { gridPadding: { top: 0, bottom: 0, left: 50, right: 50 } } }
                });

                element.find('.remove-pane').tooltip({container: element});

                this._on(element, {
                    'click .remove-pane'(ev) {
                        this._trigger('remove', ev, {panel: this});
                    },
                    'yaxisselect'(ev, data) {
                        const subject = subjects[SIDES[data.side]];
                        this._trigger('select', ev, $.extend({}, data, {
                            selection: subject.getValue(),
                            callback: subject.onNext.bind(subject)
                        }));
                    },
                });

                this._disposables = new Rx.CompositeDisposable(
                    serialDisp,
                    subjects[0],
                    subjects[1],
                    selections.connect()
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

            _prepareChroniques(colorGen, serialDisp, sides) {
                const proms = [];
                const refs = [];
                const chro = [];

                sides.forEach((chroniques, i) => {
                    const side = i === 0 ? 'left' : 'right';
                    const colorOffset = i === 0 ? 0 : sides[0].length;
                    chroniques.forEach((chronique, j) => {
                        chro.push(chronique);
                        proms.push(
                            chronique
                                .getMeasures()
                                .then((measures) => {
                                    refs.push(measures.acquire());
                                    measures.side = side;
                                    measures.index = j;
                                    measures.color = $.jqplot.getColorComponents(colorGen.get(colorOffset + j));
                                    return measures;
                                })
                        );
                    });
                });

                const chroniquesWithMeasures = proms.length > 0
                  ? Rx.Observable.forkJoin(proms)
                  : Rx.Observable.of([]);

                for(const c of this._chroMemory){
                    if(!chro.includes(c)){
                        c.measuresDisposed = true;
                    }
                }
                this._chroMemory=chro;

                return chroniquesWithMeasures
                    .do((x) => {
                        serialDisp.setDisposable(new Rx.NAryDisposable(refs));
                    });

            },

            _createYAxis(side, selection) {
                return this.element
                    .find(`.${side}-axis`)
                    .yAxis({
                        side,
                        selection
                    });
            },

            setSelections(left, right) {
                this._subjects[SIDES.left].onNext(left);
                this._subjects[SIDES.right].onNext(right);
            },

            observeSelections() {
                return this._selections;
            }
        })
        ;
})
;
