/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
const moment = require('../moment');
const Rx = require('rx');
const rxWidget = require('./rx-widget');
const _ = require('lodash');

const picker = require('./picker');
const defaults = picker.defaults;
const momentUtcOrNull = picker.momentUtcOrNull;
const toISOString = picker.toISOString;
const Status = picker.Status;

class RangeStatus extends Status {
    constructor(start, end) {
        super(
            { start: start.value, end: end.value },
            start.isPristine && end.isPristine
        );

        this.start = start;
        this.end = end;
        this.isValid = start.isValid && end.isValid;
        this.showError = start.showError || end.showError;

        this.key = start.status === 'ok' ? `end.status.${end.status}` : `start.status.${start.status}`;
        const src = start.status === 'ok' ? end : start;
        this.status = src.status;
        this.data = src.data;
    }
}

function momentDuration(value) {
    return value ? moment.duration(value) : moment.duration.invalid();
}

const widget = $.widget('bdoh.dateTimeRangePicker', rxWidget, {
    options: _.assign({}, defaults, {
        minDate: null,
        maxDate: null,

        minInterval: NaN,
        maxInterval: NaN,

        startInput: '.start',
        startAltInput: null,

        endInput: '.end',
        endAltInput: null,

        allowRangeOutOfBoundaries: false
    }),

    _create() {
        this._super();

        const minDate = this._observeOption('minDate', momentUtcOrNull, toISOString);
        const maxDate = this._observeOption('maxDate', momentUtcOrNull, toISOString);
        const minInterval = this._observeOption('minInterval', momentDuration, toISOString);
        const maxInterval = this._observeOption('maxInterval', momentDuration, toISOString);
        const format = this._observeOption('format');
        const altFormat = this._observeOption('altFormat');
        const shortFormat = this._observeOption('shortFormat');
        const placeholder = this._observeOption('placeholder');

        const status = this._status = new Rx.BehaviorSubject({});
        this._disposables.add(status);

        const startDate = status.pluck('start', 'value');
        const endDate = status.pluck('end', 'value');

        const start = this._start = this.element.find(this.options.startInput);
        if (!start.length) {
            throw new Error(`Start input not found: ${this.options.startInput}`);
        }

        const end = this._end = this.element.find(this.options.endInput);
        if (!end.length) {
            throw new Error(`End input not found: ${this.options.endInput}`);
        }

        start.dateTimePicker({
            format,
            shortFormat,
            placeholder,
            altFormat,
            altInput: this._observeOption('startAltInput'),

            minDate: Rx.Observable
                .combineLatest(minDate, endDate, maxInterval, _.bindKey(this, '_calculateStartMinDate'))
                .distinctUntilChanged(toISOString),

            maxDate: Rx.Observable
                .combineLatest(maxDate, endDate, minInterval, _.bindKey(this, '_calculateStartMaxDate'))
                .distinctUntilChanged(toISOString),

            minDateBounded: Rx.Observable
                .combineLatest(minDate, endDate, maxInterval, _.bindKey(this, '_calculateStartMinDateBounded'))
                .distinctUntilChanged(toISOString),

            maxDateBounded: Rx.Observable
                .combineLatest(maxDate, endDate, minInterval, _.bindKey(this, '_calculateStartMaxDateBounded'))
                .distinctUntilChanged(toISOString)
        });

        end.dateTimePicker({
            format,
            shortFormat,
            placeholder,
            altFormat,
            altInput: this._observeOption('endAltInput'),

            minDate: Rx.Observable
                .combineLatest(minDate, startDate, minInterval, _.bindKey(this, '_calculateEndMinDate'))
                .distinctUntilChanged(toISOString),

            maxDate: Rx.Observable
                .combineLatest(maxDate, startDate, maxInterval, _.bindKey(this, '_calculateEndMaxDate'))
                .distinctUntilChanged(toISOString),

            minDateBounded: Rx.Observable
                .combineLatest(minDate, startDate, minInterval, _.bindKey(this, '_calculateEndMinDateBounded'))
                .distinctUntilChanged(toISOString),

            maxDateBounded: Rx.Observable
                .combineLatest(maxDate, startDate, maxInterval, _.bindKey(this, '_calculateEndMaxDateBounded'))
                .distinctUntilChanged(toISOString)
        });

        this._disposables.add(
            Rx.Observable
                .combineLatest(
                    [start.dateTimePicker('observeStatus'), end.dateTimePicker('observeStatus')],
                    (start, end) => new RangeStatus(start, end)
                )
                .distinctUntilChanged()
                .subscribe(status)
        );

        this._disposables.add(
            this._status.subscribe((status) => {
                this._trigger('change', null, status.value);
                this._trigger('status', null, status);
            })
        );
    },

    _calculateStartMinDate(minDate, endDate, maxInterval, bounded=false) {
        const minDateStartOfYear = minDate
            .clone()
            .startOf('year');
        if (!maxInterval.isValid() || !endDate.isValid()) {
            if(this.options.allowRangeOutOfBoundaries && !bounded){
                return minDateStartOfYear;
            }
            return minDate;
        }
        if(this.options.allowRangeOutOfBoundaries && !bounded) {
            return moment.max(minDateStartOfYear, endDate.clone().subtract(maxInterval));
        }
        return moment.max(minDate, endDate.clone().subtract(maxInterval));
    },

    _calculateStartMinDateBounded(minDate, endDate, maxInterval) {
        return this._calculateStartMinDate(minDate, endDate, maxInterval, true);
    },

    _calculateStartMaxDate(maxDate, endDate, minInterval, bounded=false) {
        const maxDateEndOfYear = maxDate
            .clone()
            .add(1, 'year')
            .startOf('year');
        let actualMax = endDate.isValid() ? moment.min(maxDate, endDate) : maxDate;
        if(this.options.allowRangeOutOfBoundaries && !bounded) {
            actualMax = endDate.isValid() ? moment.min(maxDateEndOfYear, endDate) : maxDateEndOfYear;
        }
        if (!minInterval.isValid()) {
            return actualMax;
        }
        return actualMax.clone().subtract(minInterval);
    },

    _calculateStartMaxDateBounded(maxDate, endDate, minInterval) {
        return this._calculateStartMaxDate(maxDate, endDate, minInterval, true);
    },

    _calculateEndMinDate(minDate, startDate, minInterval, bounded=false) {
        const minDateStartOfYear = minDate
            .clone()
            .startOf('year');
        let actualMin = startDate.isValid() ? moment.max(minDate, startDate) : minDate;
        if(this.options.allowRangeOutOfBoundaries && !bounded) {
            actualMin = startDate.isValid() ? moment.max(minDateStartOfYear, startDate) : minDateStartOfYear;
        }
        if (!minInterval.isValid()) {
            return actualMin;
        }
        return actualMin.clone().add(minInterval);
    },

    _calculateEndMinDateBounded(minDate, startDate, minInterval) {
        return this._calculateEndMinDate(minDate, startDate, minInterval, true);
    },

    _calculateEndMaxDate(maxDate, startDate, maxInterval, bounded=false) {
        const maxDateEndOfYear = maxDate
            .clone()
            .add(1, 'year')
            .startOf('year');
        if (!startDate.isValid() || !maxInterval.isValid()) {
            if(this.options.allowRangeOutOfBoundaries && !bounded) {
                return maxDateEndOfYear;
            }
            return maxDate;
        }
        if(this.options.allowRangeOutOfBoundaries && !bounded) {
            return moment.min(maxDateEndOfYear, startDate.clone().add(maxInterval));
        }
        return moment.min(maxDate, startDate.clone().add(maxInterval));
    },

    _calculateEndMaxDateBounded(maxDate, startDate, maxInterval) {
        return this._calculateEndMaxDate(maxDate, startDate, maxInterval, true);
    },

    setDateTimeRange(range) {
        const start = range && range.start;
        const end = range && range.end;
        this._start.dateTimePicker('setDateTime', start);
        this._end.dateTimePicker('setDateTime', end);
    },

    getDateTimeRange() {
        return this.getStatus().value;
    },

    observeDateTimeRange() {
        return this._status.pluck('value');
    },

    getStatus() {
        return this._status.getValue();
    },

    observeStatus() {
        return this._status;
    }
});

module.exports = widget;
