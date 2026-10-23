/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const $ = require('jquery');
const Rx = require('rx');
const _ = require('lodash');

require('jquery-ui/ui/widget');

function join(x) {
    if (_.isFunction(_.get(x, 'subscribe')) || _.isFunction(_.get(x, 'then'))) {
        return x;
    }
    return Rx.Observable.of(x);
}

const widget = $.widget('bdoh.rxWidget', {
    _optionsInput: null,
    _options: null,
    _disposables: null,

    _create() {
        this._optionsInput = new Rx.Subject();
        this._disposables = new Rx.CompositeDisposable();

        this._optionObservables = {};
        this._disposables.add(this._optionsInput);

        this._options = this._optionsInput.scan(_.assign).publish();
        this._collectDataOptions();
    },

    _init() {
        this._disposables.add(this._options.connect());
        this._optionsInput.onNext(this.options);
    },

    _collectDataOptions() {
        for (const key in this.options) {
            if (!this.options.hasOwnProperty(key)) {
                continue;
            }
            const value = this.element.data(_.kebabCase(key));
            if (value !== undefined) {
                this.options[key] = value;
            }
        }
    },

    _destroy() {
        this._disposables.dispose();
        delete this._attributeObservables;
        delete this._optionObservables;
    },

    _setOptions(options) {
        this._optionsInput.onNext(options);
        return this;
    },

    _observeOption(key, mapValue, distinctBy) {
        if (this._optionObservables[key]) {
            return this._optionObservables[key];
        }

        let obs = this._options
            .pluck(key)
            .flatMap(join);
        if (mapValue) {
            obs = obs.map(_.unary(mapValue));
        }
        obs = obs
            .distinctUntilChanged(distinctBy)
            .shareReplay(1);

        this._optionObservables[key] = obs;
        return obs;
    },

    _observeAttribute(name) {
        if (!this._attributeObservables) {
            this._initAttributeObservable();
        }

        if (this._attributeObservables[name]) {
            return this._attributeObservables[name];
        }

        const sub = this._attributeObservables[name] =
            this._attributeObservable
                .filter((mut) => {
                    return mut.name === name;
                })
                .pluck('value')
                .shareValue(this.element.attr(name));

        this._attributeObserver.observe(
            this.element[0],
            {attributes: true, attributeFilter: _.keys(this._attributeObservables)}
        );

        return sub;
    },

    _initAttributeObservable() {
        this._attributeObservables = {};

        const sub = new Rx.Subject();
        const ao = this._attributeObserver = new MutationObserver(_.bindKey(sub, 'onNext'));

        this._attributeObservable = sub
            .flatMap(_.identity)
            .map((record) => {
                const attr = record.target.attributes[record.attributeName];
                return {name: record.attributeName, value: attr ? attr.value : undefined};
            })
            .share();

        this._disposables.add(sub);
        this._disposables.add(new Rx.Disposable(_.bindKey(ao, 'disconnect')));
    },

    _debug: _.rest(function (prefix) {
        prefix.unshift(`${this.element.attr('id')} >>`);
        return _.rest((args) => {
            // eslint-disable-next-line no-console
            console.debug(...prefix.concat(args));
        });
    }),
});

module.exports = widget;
