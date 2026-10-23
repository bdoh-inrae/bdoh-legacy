/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx-jquery',
    '../services/ajax-queue'
], ($, Rx, AjaxQueue) => {
    'use strict';

    function BaseRepository(queue, url, start, end) {
        if (!(queue instanceof AjaxQueue)) throw `Invalid queue: ${queue}`;
        if (!url || typeof(url) !== 'string') throw `Invalid url: ${url}`;

        this.start = start;
        this.end = end;

        this._queue = queue;
        this._url = url;
        this._start = start.toISOString();
        this._end = end.toISOString();
        this._disposables = new Rx.CompositeDisposable();
    }

    BaseRepository.prototype.dispose = function() {
        if (!this._disposables) {
            return;
        }
        const d = this._disposables;
        this._disposables = this._queue = null;
        d.dispose();
    };

    return BaseRepository;
});
