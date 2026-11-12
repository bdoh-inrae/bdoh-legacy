// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
define([
    'jquery',
    './repositories/measure',
    './services/ajax-queue',
    './services/x-axis-config',
    '@IrsteaBdohBundle/lib/moment',
    'lodash',
    'translator',
    'rx',
    '@IrsteaBdohBundle/lib/busy',
    // --
    '@IrsteaBdohBundle/lib/date-time/range-picker'
], ($, MeasureRepository, AjaxQueue, createXAxisConfig, moment, _, Translator, Rx, busy) => {
    'use strict';

    return $.widget(
        'viewer.baseViewer',
        {
            options: {
                minDate: null,
                maxDate: null,
                measureURL: null,
                allowRangeOutOfBoundaries: false
            },

            _create() {
                this._setupDateRangePicker();
                this._setupDateRangeSubjects();
                this._setupRepositories();
                this._setupBusy();
                this._setupXAxisConfig();
            },

            _setupDateRangePicker() {
                const picker = this._dateRangePicker = this.element.find('.date-range-picker');
                picker.dateTimeRangePicker({
                    minDate: this.options.minDate,
                    maxDate: this.options.maxDate,
                    minInterval: moment.duration(1, 'day'),
                    allowRangeOutOfBoundaries: this.options.allowRangeOutOfBoundaries
                });

                const statusObs = picker.dateTimeRangePicker('observeStatus');

                statusObs
                    .subscribe((status) => {
                        const msg = status.showError ? status.format(Translator) : null;
                        picker
                            .find('.feedback')
                            .toggle(!!msg)
                            .find('.msg')
                            .text(msg);

                        picker
                            .find('.btn.validated')
                            .toggleClass('disabled', !status.isValid)
                            .attr('disabled', !status.isValid);
                    });

                this._selectedDateRange =
                    statusObs
                        .filter(_.property('isValid'))
                        .pluck('value');

                this._submitTrigger = new Rx.Subject();

                picker
                    .find('.btn.validated.submit')
                    .clickAsObservable()
                    .subscribe(this._submitTrigger);

                this._submittedDateRange = this._submitTrigger
                    .flatMap(() => this._selectedDateRange.first());
            },

            _setupRepositories() {
                const queue = this._queue = new AjaxQueue();
                const url = this.options.measureURL;
                const holder = new Rx.SerialDisposable(Rx.Disposable.empty);

                this._measureRepository = this._inputDateRange
                    .filter(({start, end}) => start && end)
                    .map((range) => new MeasureRepository(queue, url, range.start, range.end))
                    .do((repo) => holder.setDisposable(repo))
                    .shareReplay(1);
            },

            _setupDateRangeSubjects() {
                const lastInputRange = this._inputDateRange = new Rx.ReplaySubject(1);
                const graphDateRange = this._graphDateRange = new Rx.ReplaySubject(1);

                this._submittedDateRange.subscribe(lastInputRange);

                Rx.Observable
                    .merge([
                        this._submittedDateRange,
                        this.element.onAsObservable('plotzoomreset')
                            .flatMap(() => lastInputRange.first()),
                        this.element.onAsObservable('plotzoom')
                            .map((ev) => ({
                                start: moment.utc(ev.originalEvent.data.start),
                                end: moment.utc(ev.originalEvent.data.end)
                            }))
                    ])
                    .subscribe(graphDateRange);
            },

            _setupXAxisConfig() {
                this._xAxisConfig = this._graphDateRange
                    .map(createXAxisConfig)
                    .shareReplay(1);
            },

            // Le visualiseur grisé et flouté pendant qu'il charge, au lieu d'un voile sur toute la page (lib/busy.js) ;
            // la file ne le montre qu'après une demi-seconde de chargement (services/ajax-queue.js). Elle dit la fin des
            // requêtes, mais le tracé d'une longue chronique suit quelques centaines de millisecondes plus tard : le
            // voile reste jusqu'à lui (widgets/graph.js, « graphdrawn »), ou une seconde et demie au plus.
            _setupBusy() {
                const element = this.element;
                const drawn = Rx.Observable.fromEvent(element, 'graphdrawn');
                this._queue.observeState()
                    .pluck('show')
                    .distinctUntilChanged()
                    .flatMapLatest((show) => (show
                        ? Rx.Observable.just(true)
                        : Rx.Observable.merge(drawn, Rx.Observable.timer(1500)).first().map(() => false)))
                    .distinctUntilChanged()
                    .subscribe((on) => busy(element, on));
            },

            setDateRange(start, end, submit = false) {
                this._dateRangePicker.dateTimeRangePicker('setDateTimeRange', {start, end});
                if (submit) {
                    this._submitTrigger.onNext(submit);
                }
            },

            getSubmittedDateRange() {
                return this._submittedDateRange;
            },

            // La période que montre le graphique : les dates choisies, puis chaque zoom dans le graphique
            observeGraphDateRange() {
                return this._graphDateRange;
            },

            getSelectedDateRange() {
                return this._selectedDateRange;
            }
        }
    );

});
