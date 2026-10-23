/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx-jquery',
    './base',
    '../models/measures',
    '../models/serie'
], ($, Rx, BaseRepository, Measures, Serie) => {
    'use strict';

    const SCHEDULER = Rx.Scheduler.async;

    function arrayConcat(arrays) {
        if(arrays.length === 0) {
            return [];
        }
        const head = arrays.shift();
        return head.concat(...arrays);
    }

    function MeasureRepository(queue, url, start, end) {
        BaseRepository.call(this, queue, url, start, end);
        this._cache = {};
    }

    MeasureRepository.prototype = Object.create(BaseRepository.prototype);

    MeasureRepository.prototype.getMeasures = function (id, force) {
        if (!(id in this._cache) || force) {
            this._cache[id] = this._queryMeasures(id);
        }
        return this._cache[id];
    };

    MeasureRepository.prototype._queryMeasures = function (id) {
        const queue = this._queue;
        const url = this._url.replace('-ID-', `${id}`);
        const hydrateMeasures = this._hydrateMeasures.bind(this);

        return queue
            .request({
                url,
                data: {beginDate: this._start, endDate: this._end}
            })
            .then((response) => {
                return hydrateMeasures(response.chronicles[0]);
            });
    };

    MeasureRepository.prototype._hydrateMeasures = function (response) {
        const measures = new Measures(response);

        this._disposables.add(measures);

        // Ici, on utilise un observable avec scheduler pour délayer le traitement dans le temps
        // Cela évite de bloquer le navigateur en cas de gros traitement.

        return Rx.Observable
            .from(response.series, null, null, SCHEDULER)
            .reduce(
                (series, serie) => {
                    const flag = serie.seriesFlag;
                    const values = serie.values;
                    const style = serie.style;
                    const label = serie.label;
                    const showLabel = serie.showLabel;
                    if (values.length > 0) {
                        if (!(flag in series)) {
                            series[flag]={};
                            series[flag]['values'] = [values];
                        } else {
                            series[flag]['values'].push([[null, null]], values);
                        }
                        series[flag]['style'] = style;
                        series[flag]['label'] = label;
                        series[flag]['showLabel'] = showLabel;
                    }
                    return series;
                },
                {}
            )
            .map((series) => {
                for(const type in series) {
                    const values = arrayConcat(series[type]['values']);
                    measures.series.push(new Serie(type, measures, values, series[type]['style'], series[type]['label'], series[type]['showLabel']));
                }
                return measures;
            })
            .toPromise();
    };

    return MeasureRepository;
});

