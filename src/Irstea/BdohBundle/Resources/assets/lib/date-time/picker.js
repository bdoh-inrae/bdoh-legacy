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
const Translator = require('translator');
require('rx-jquery');

function momentUtcOrNull(value) {
    return value === undefined ? moment.null() : moment.utc(value);
}

const toISOString = _.method('toISOString');

const defaults = {
    placeholder: Translator.trans('placeholder', 'datetime'),
    format: Translator.trans('format.long', 'datetime'),
    shortFormat: Translator.trans('format.short', 'datetime'),
    altFormat: moment.ISO_8601()
};


class Status {

    constructor(value = moment.null(), pristine = true) {
        this.value = value;
        this.isPristine = pristine;
        this.status = 'ok';
        this.key = 'status.ok';
        this.isValid = true;
        this.showError = false;
    }

    setError(error, data) {
        this.status = error;
        this.key = `status.${error}`;
        this.isValid = false;
        this.showError = !this.isPristine;
        if (data) {
            this.data = data;
        }
    }

    format(translator, dateFormat) {
        const params = {};
        if (this.data) {
            Object.assign(params, this.data);
            const format = dateFormat || Translator.trans('format.long', 'datetime');
            _.each(params, (value, key) => {
                if (moment.isMoment(value) && value.isValid()) {
                    params[key] = value.format(format);
                }
            });
        }
        return translator.trans(this.key, params);
    }

    toString() {
        return JSON.stringify(this);
    }
}

const widget = $.widget('bdoh.dateTimePicker', rxWidget, {
    options: _.assign({}, defaults, {
        minDate: null,
        maxDate: null,
        minDateBounded: null,
        maxDateBounded: null,
        altInput: null,
        setButtons: true,
        resetButton: true,
        strict: false
    }),

    _create() {
        this._super();

        const el = this.element;
        const inputGroup = el.closest('.input-group');
        if (!inputGroup.length) {
            throw Error('The dateTime must be in a .input-group');
        }

        // Boutons
        this._setupWidget(inputGroup);
        const resetButton = this._resetButton;
        const setMinButton = this._setMinButton;
        const setMaxButton = this._setMaxButton;

        const disposables = this._disposables;

        // Crée des sujets les options très souvents utilisées
        const minDate = this._observeOption('minDate', momentUtcOrNull, toISOString);
        const maxDate = this._observeOption('maxDate', momentUtcOrNull, toISOString);

        let minDateBounded = minDate;
        if(this.options.minDateBounded) {
            minDateBounded = this._observeOption('minDateBounded', momentUtcOrNull, toISOString);
        }
        let maxDateBounded = maxDate;
        if(this.options.maxDateBounded) {
            maxDateBounded = this._observeOption('maxDateBounded', momentUtcOrNull, toISOString);
        }

        const format = this._observeOption('format');

        // Valeur courante de l'input control (view)
        const viewValueInput = new Rx.BehaviorSubject(el.val());
        disposables.add(viewValueInput);
        const viewValue = viewValueInput.distinctUntilChanged().shareReplay(1);

        // Valeur courante sous forme de date
        const value = this._value = new Rx.BehaviorSubject(moment.null());
        disposables.add(value);

        // L'entrée a-t-elle été modifiée par l'utilisateur ?
        const pristine = new Rx.BehaviorSubject(true);
        disposables.add(pristine);

        // Statut de la valeur courante
        const status = this._status = new Rx.BehaviorSubject(new Status());
        disposables.add(status);

        // Point d'entrée pour setDateTime
        const setDateTimeInput = this._setDateTimeInput = new Rx.Subject();
        disposables.add(setDateTimeInput);

        /* Met en place tous les abonnements, en collectant bien les disposables.
         * N.B. : les abonnements sont listés par ordre inverse de dépendance. Cela permet
         * aux dépendances avales d'être initialisées avant que les données arrivent an amont.
         */

        // Double-bind viewValue <=> input control
        disposables.add(
            Rx.Observable
                .merge(
                    el.onAsObservable('keyup').debounce(400),
                    el.onAsObservable('change')
                )
                .map(() => el.val())
                .distinctUntilChanged()
                .do(() => pristine.onNext(false))
                .subscribe(viewValueInput)
        );

        disposables.add(
            viewValue.subscribe((viewValue) => {
                if (viewValue !== el.val()) {
                    el.val(viewValue);
                }
            })
        );

        // Mise à jour sur événéments de l'input
        disposables.add(
            Rx.Observable
                .merge([
                    // L'input control perd le focus
                    el
                        .onAsObservable('blur')
                        .flatMap(() => status.first())
                        .pluck('value'),

                    // L'utilisateur clique sur set-min
                    inputGroup
                        .onAsObservable('click', '.set-min')
                        .flatMap(() => minDateBounded.first()),

                    // L'utilisateur clique sur set-max
                    inputGroup
                        .onAsObservable('click', '.set-max')
                        .flatMap(() => maxDateBounded.first())
                ])
                .do(() => pristine.onNext(false))
                .filter(_.method('isValid'))
                .combineLatest(format, (value, format) => value.format(format))
                .subscribe(viewValueInput)
        );

        // Click bouton reset
        disposables.add(
            inputGroup
                .onAsObservable('click', '.reset')
                .map(_.constant(''))
                .do(() => pristine.onNext(false))
                .subscribe(viewValueInput)
        );

        // Mise à jour via setDateTime
        disposables.add(
            setDateTimeInput
                .combineLatest(
                    format,
                    (value, format) => {
                        const m = moment.utc(value);
                        return m.isValid() ? m.format(format) : value;
                    }
                )
                .subscribe(viewValueInput)
        );

        // Colore le groupe en fonction du statut de validation
        disposables.add(
            status
                .combineLatest(format)
                .subscribe(([status, format]) => {
                    inputGroup.toggleClass('has-error', status.showError);
                    const msg = status.showError ? status.format(Translator, format) : '';
                    el[0].setCustomValidity(msg);
                    el.attr('title', msg);
                })
        );

        // Renseigne l'input secondaire
        disposables.add(
            this._observeOption('altInput')
                .map(_.bindKey(this, '_findAltInput'))
                .filter(_.property('length'))
                .combineLatest(
                    status,
                    this._observeOption('altFormat'),
                    (el, {isValid, value}, altFormat) => ({el, value: isValid ? value.format(altFormat) : ''})
                )
                .subscribe((o) => o.el.val(o.value))
        );

        // Validation de la date
        disposables.add(
            Rx.Observable
                .combineLatest(
                    value,
                    minDate,
                    maxDate,
                    this._observeAttribute('required'),
                    pristine.distinctUntilChanged(),
                    _.bindKey(this, '_validate')
                )
                .distinctUntilChanged()
                .subscribe(status)
        );

        // Parsing de la chaîne en date
        disposables.add(
            Rx.Observable
                .combineLatest(
                    viewValue,
                    format,
                    this._observeOption('shortFormat'),
                    this._observeOption('strict'),
                    _.bindKey(this, '_parse')
                )
                .distinctUntilChanged(toISOString)
                .subscribe(value)
        );

        // Contrôle la visibilité des boutons en fonction des options
        disposables.add(
            this._observeOption('setButtons')
                .subscribe((visible) => {
                    setMaxButton.toggle(!!visible);
                    setMinButton.toggle(!!visible);
                })
        );

        disposables.add(
            Rx.Observable
                .combineLatest(
                    this._observeOption('resetButton'),
                    this._observeAttribute('required'),
                    (wantButton, isRequired) => {
                        return !!wantButton && !isRequired;
                    }
                )
                .subscribe(_.bindKey(resetButton, 'toggle'))
        );

        // Désactive le bouton "reset" si on a pas de valeurs saisies
        disposables.add(
            viewValue.subscribe((val) => {
                const disabled = !val;
                inputGroup
                    .find('.reset')
                    .attr('disabled', disabled)
                    .toggleClass('disabled', disabled);
            })
        );

        // Désactive le bouton "set-min" si on a pas de date minimum
        disposables.add(
            minDateBounded.subscribe((date) => {
                inputGroup
                    .find('.set-min')
                    .attr('disabled', !date.isValid())
                    .toggleClass('disabled', !date.isValid());
            })
        );

        // Désactive le bouton "set-max" si on a pas de date maximum
        disposables.add(
            maxDateBounded.subscribe((date) => {
                inputGroup
                    .find('.set-max')
                    .attr('disabled', !date.isValid())
                    .toggleClass('disabled', !date.isValid());
            })
        );

        // Affiche le placeholder
        disposables.add(
            this
                ._observeOption('placeholder')
                .subscribe((ph) => el.attr('placeholder', ph))
        );

        // Génère des événements
        disposables.add(
            status.subscribe(
                (status) => {
                    this._trigger('change', null, status.value);
                    this._trigger('status', null, status);
                }
            )
        );
    },

    _init() {
        this._super();
        if (this.options.initialDateTime !== undefined) {
            this.setDateTime(this.options.initialDateTime);
            delete this.options.initialDateTime;
        }
    },

    _findAltInput(selector) {
        if (!selector) {
            return $();
        }
        let input;
        switch (selector[0]) {
            case '.':
                input = this.element.closest('.input-group').find(selector);
                break;

            case '#':
                input = $(selector);
                break;

            default:
                input = $(this.element[0].form[selector]);
        }
        return input;
    },

    _setupWidget(inputGroup) {
        const el = this.element;

        // TODO: retoucher la stylesheet pour ne pas en avoir besoin
        el.css({
            height: 'auto',
            'text-align': 'center'
        });

        const mkButton = function (kind, icon) {
            let button = inputGroup.find(`.${kind}`);
            if (!button.length) {
                button = $(
                    `${'<span class="input-group-btn">' +
                    '<a href="javascript:void(0);" tabindex="-1" class="btn btn-default '}${kind}" title="${
                        _.escape(Translator.trans(`buttons.${kind}`, 'datetime'))
                        }"><i class="glyphicon glyphicon-${icon}"></i></a></span>`
                );
            }
            return button;
        };

        this._resetButton = mkButton('reset', 'remove').insertAfter(el);
        this._setMinButton = mkButton('set-min', 'fast-backward').insertBefore(el);
        this._setMaxButton = mkButton('set-max', 'fast-forward').insertAfter(el);
    },

    _parse(val, format, shortFormat, strict) {
        this._debug('_parse', val, format, shortFormat);
        if (!val) {
            return moment.null();
        }
        return moment.utc(val, [format, shortFormat], strict);
    },

    _validate(value, minDate, maxDate, required, pristine) {
        const status = new Status(value, pristine);

        if (moment.isNull(value)) {
            if (required) {
                status.setError('required');
            }
        } else if (!value.isValid()) {
            status.setError('invalid', {input: value._i}); // N.B. : un peu triché
        } else if (minDate.isValid() && value.isBefore(minDate)) {
            status.setError('afterMaxDate', {maxDate: minDate});
        } else if (maxDate.isValid() && value.isAfter(maxDate)) {
            status.setError('beforeMinDate', {minDate: maxDate});
        }

        return status;
    },

    setDateTime(value) {
        this._setDateTimeInput.onNext(value);
    },

    getDateTime() {
        return this._value.getValue();
    },

    observeDateTime() {
        return this._value.asObservable();
    },

    getStatus() {
        return this._status.getValue();
    },

    observeStatus() {
        return this._status.asObservable();
    }
});

$.fn.dateTimePicker.defaults = defaults;

module.exports = {
    defaults,
    momentUtcOrNull,
    Status,
    toISOString,
    widget
};
