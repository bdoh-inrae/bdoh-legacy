/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx-jquery',
    '@IrsteaBdohBundle/lib/moment'
], ($, Rx, moment) => {
    const DATE_FORMAT = 'YYYYMMDDHHmmss';

    const State = function (window) {
        const newState = this.input = new Rx.Subject();
        const newHash = new Rx.BehaviorSubject(window.location.hash);

        Rx.Observable
            .fromEvent(window, 'popstate')
            .map((ev) => ev.target.location.hash)
            .subscribe(newHash);

        this.output = newHash
            .map((hash) => {
                try {
                    return this.parse(hash);
                } catch (_err) {
                    return false;
                }
            }, this)
            .filter(Rx.helpers.identity)
            .publish();

        newState
            .map(function (state) {
                try {
                    return this.toString(state);
                } catch (err) {
                    return false;
                }
            }, this)
            .filter(Rx.helpers.identity)
            .subscribe((hash) => window.history.pushState(null, null, `viewer#${hash}`));
    };

    State.prototype.connect = function () {
        this.output.connect();
    };

    State.prototype.parse = function (hash) {
        const [dates, ...parts] = hash.substr(1).split('/');
        if (!dates || dates.length < 28) {
            return false;
        }

        return {
            dates: {
                start: moment.utc(dates.substr(0, 14), DATE_FORMAT),
                end: moment.utc(dates.substr(14), DATE_FORMAT)
            },
            graphs: parts.map((graph) => {
                const [left = '', right = ''] = graph.split('|');
                return [left, right]
                    .map((ids) => ids.split(',').map(Number.parseInt));
            })
        };
    };

    State.prototype.toString = function ({dates: {start, end}, graphs}) {
        if (!start || !end) {
            return false;
        }
        const dates = start.utc().format(DATE_FORMAT) + end.utc().format(DATE_FORMAT);
        const parts = graphs.map(graph =>
            graph
                .map(ids => ids.join(','))
                .join('|')
        );

        return [dates, ...parts].join('/');
    };

    return State;
});
