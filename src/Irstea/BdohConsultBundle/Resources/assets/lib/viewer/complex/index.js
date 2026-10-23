/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    '../base',
    '../repositories/metadata',
    '../widgets/selector',
    '../services/state',
    'rx',
    '../widgets/panel',
    '../widgets/x-axis',
    './style.css'
], ($, baseViewer, MetadataRepository, Selector, State, Rx) => {

    let panelTemplate;
    (function () {
        const tpl = $('#cv-panel-template');
        panelTemplate = $(tpl.html());
        tpl.remove();
    })();

    return $.widget(
        'viewer.complexViewer',
        baseViewer,
        {
            _create() {
                this._super();

                this._selectionsDisposable = new Rx.SerialDisposable();
                this._selections = new Rx.BehaviorSubject([]);

                this._setupXAxis();
                this._setupPanels();
                //this._setupState(); // marche trop mal
            },

            _setupRepositories() {
                this._super();

                const queue = this._queue;
                const url = this.options.metadataURL;
                const holder = new Rx.SerialDisposable(Rx.Disposable.empty);

                const meta = this._metadataRepository = this._measureRepository
                    .map((measureRepo) => {
                        return new MetadataRepository(queue, url, measureRepo.start, measureRepo.end, measureRepo);
                    })
                    .do((repo) => {
                        holder.setDisposable(repo);
                    })
                    .shareReplay(1);

                this._observatoire = meta.flatMap((repo) => repo.observatoire);
                this._stations = meta.flatMap((repo) => repo.stations);
                this._chroniques = meta.flatMap((repo) => repo.chroniques);
            },

            _setupXAxis() {
                const xAxis = this.element
                    .find('.x-axis')
                    .xAxis({config: this._xAxisConfig});

                this._selections
                    .map((sels) => sels.length > 0)
                    .subscribeOnNext(xAxis.toggle, xAxis);
            },

            _setupPanels() {
                const panelButtons = this.element.find('.panel-buttons');
                this._panelContainer = this.element.find('.panel-container');

                this._inputDateRange
                    .map((range) => range && range.start !== null && range.end !== null)
                    .subscribeOnNext(panelButtons.toggle, panelButtons);

                this._inputDateRange
                    .first()
                    .subscribeOnNext(() => {
                        if (this.getPanels().length === 0) {
                            this.createPanel();
                            this._updateSelections();
                        }
                    });

                this._on(this.element, {
                    'click .add-pane'() {
                        this.createPanel();
                        this._updateSelections();
                    },
                    'panelselect'(ev, data) {
                        Selector.open($.extend({}, data, {
                            index: 1 + $.inArray(ev.target, this.getPanels()),
                            observatoire: this._observatoire,
                            stations: this._stations
                        }));
                    },
                    'panelremove'(ev) {
                        this.removePanel(ev.target);
                        this._updateSelections();
                    },
                });
            },

            createPanel() {
                panelTemplate
                    .clone()
                    .appendTo(this._panelContainer)
                    .panel({
                        xaxis: this._xAxisConfig,
                        repository: this._metadataRepository
                    });
            },

            removePanel(panel) {
                $(panel).remove();
            },

            getPanels() {
                return this._panelContainer.children();
            },

            _setupState() {
                const state = this._state = new State(window);

                Rx.Observable
                    .combineLatest([this._inputDateRange, this._selections], this._serializeState)
                    .subscribe(state.input);

                state.output.subscribeOnNext(this._restoreState, this);

                state.connect();
            },

            _serializeState(dates, selections) {
                return {
                    dates,
                    graphs: selections.map((selection) =>
                        selection.map((side) =>
                            side.map((chronique) => chronique.id)
                        )
                    )
                };
            },

            _restoreState(state) {
                this._dateRangePicker.dateTimeRangePicker('setDateTimeRange', state.dates);

                const graphs = state.graphs;
                let panels = this.getPanels();

                panels.slice(graphs.length).remove();
                for (let i = panels.length; i < graphs.length; i++) {
                    this.createPanel();
                }

                panels = this.getPanels();
                this._chroniques
                    .subscribeOnNext(function (chroniques) {
                        graphs.forEach((sides, idx) => {
                            const selections = sides.map((ids) =>
                                ids
                                    .map((id) => chroniques[id])
                                    .filter(Rx.helpers.identity)
                            );
                            $(panels[idx]).panel('setSelections', selections[0], selections[1]);
                        });

                        this._updateSelections();
                    }, this);
            },

            _updateSelections() {
                const selections = this
                        .getPanels()
                        .toArray()
                        .map((panel) => $(panel).panel('observeSelections'));
                let subscription;
                if (selections.length > 0) {
                    subscription = Rx.Observable
                        .combineLatest(selections)
                        .subscribe(this._selections);
                } else {
                    subscription = Rx.emptyDisposable;
                    this._selections.onNext([]);
                }
                this._selectionsDisposable.setDisposable(subscription);
            }
        }
    );
});
