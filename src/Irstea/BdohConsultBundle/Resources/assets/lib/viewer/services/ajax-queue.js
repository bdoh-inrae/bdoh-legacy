/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx-jquery'
], ($, Rx) => {
    'use strict';

    function sum(x, y) {
        return x + y;
    }

    // AjaxQueue: queue de requêtes Ajax ; n'éxecute qu'un nombre limité de requêtes en simultanée.

    function AjaxQueue() {
        this._disposables = new Rx.CompositeDisposable();
        this._queue = new Rx.Subject();
        this._maxTries = 3;

        this._setupState();
        this._setupVisibilityControl();
        this._setupProcessing();
    }

    AjaxQueue.prototype._setupState = function () {
        const queued = this._queued = new Rx.BehaviorSubject(0);
        const completed = this._completed = new Rx.BehaviorSubject(0);
        const past = this._past = new Rx.BehaviorSubject(0);
        const show = this._show = new Rx.BehaviorSubject(false);
        const completedTotal = this._completedTotal = completed.scan(sum).shareReplay(1);
        const queuedTotal = this._queuedTotal = queued.scan(sum).shareReplay(1);

        this._state = Rx.Observable.combineLatest(
            [
                queuedTotal,
                completedTotal,
                past.delay(300),
                show
            ],
            (queued, completed, past, show) => {
                const c = completed - past;
                const t = queued - past;
                return {
                    progress: t ? c / t : 0,
                    show
                };
            }
        );

        this._disposables.add(
            this._completedTotal
                .sample(
                    show
                        .distinctUntilChanged()
                        .filter((x) => {
                            return !x;
                        })
                )
                .subscribe(this._past)
        );
    };

    AjaxQueue.prototype._setupVisibilityControl = function () {
        const hasWork = Rx.Observable
            .combineLatest(
                [
                    this._completedTotal,
                    this._queuedTotal
                ],
                (completed, queued) => {
                    return queued > completed;
                }
            )
            .debounce((show) => {
                return Rx.Observable.just(show ? 500 : 300);
            });

        this._disposables.add(
            hasWork.subscribe(this._show)
        );
    };

    AjaxQueue.prototype._setupProcessing = function () {
        const token = new Rx.Subject();
        const startRequest = this._startRequest.bind(this);
        const nextSerial = this._nextSerial.bind(this);
        const completed = this._completed;

        this._disposables.add(
            this._queue
                .zip([token])
                .flatMap((data) => {
                    const req = data[0];
                    const prom = startRequest(req.options);
                    prom.then(req.resolve, req.reject);
                    return prom.then(nextSerial, nextSerial);
                })
                .do(() => {
                    completed.onNext(1);
                })
                .subscribe(token)
        );

        token.onNext(nextSerial());
    };

    AjaxQueue.prototype._startRequest = function (options) {
        const doRequest = function () {
                return Promise.resolve($.ajax(options));
            };
        let prom = doRequest();
        for (let i = 1; i < this._maxTries; i++) {
            prom = prom.then(null, doRequest);
        }
        return prom;
    };

    AjaxQueue.prototype._nextSerial = function () {
        return ++this._serial;
    };

    AjaxQueue.prototype.request = function (options) {
        const queue = this._queue;
        this._queued.onNext(1);
        return new Promise(((resolve, reject) => {
            queue.onNext({options, resolve, reject});
        }));
    };

    AjaxQueue.prototype.observeState = function () {
        return this._state;
    };

    AjaxQueue.prototype.dispose = function () {
        this._disposables.dispose();
    };

    return AjaxQueue;
});
