/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx-jquery',
    './base',
    './measure',
    '../models/observatoire',
    '../models/station',
    '../models/chronique',
    'lodash'
], ($, Rx, BaseRepository, MeasureRepository, Observatoire, Station, Chronique, _) => {
    'use strict';

    const SCHEDULER = Rx.Scheduler.async;

    function MetadataRepository(queue, url, start, end, measureRepository) {
        if (!(measureRepository instanceof MeasureRepository)) throw `Invalid measureRepository: ${measureRepository}`;
        this._measureRepository = measureRepository;

        BaseRepository.call(this, queue, url, start, end);

        this._queryMetadata();
        this._setupObservatoire();
        this._setupStations();
        this._setupChroniques();
    }

    MetadataRepository.prototype = Object.create(BaseRepository.prototype);

    MetadataRepository.prototype._queryMetadata = function () {
        const queue = this._queue;
        const dates = {start: this._start, end: this._end};

        this._metadata = Rx.Observable.fromPromise(
            queue
                .request({
                    url: this._url,
                    data: dates
                })
                .then((response) => {
                    return {dates, response}
                })
        );
    };

    MetadataRepository.prototype._setupObservatoire = function () {
        this.observatoire = this._metadata
            .pluck('response', 'observatory')
            .map(this._hydrateObservatoire, this)
            .shareReplay(1);
    };

    MetadataRepository.prototype._setupStations = function () {
        const hydrateStation = this._hydrateStation.bind(this);
        this.stations = this._metadata
            .flatMap((metadata) => {
                return Rx.Observable
                    .pairs(metadata.response.stations, SCHEDULER)
                    .flatMap((pair) => {
                        return hydrateStation(metadata.dates, pair[1]);
                    })
                    .toArray()
                    .map(s => s.sort((a, b) => {
                            return a.name.localeCompare(b.name, 'fr', {'sensitivity':'base'});
                        }));
            })
            .shareReplay(1);
    };

    MetadataRepository.prototype._setupChroniques = function () {
        this.chroniques = this.stations
            .flatMap((stations) => {
                return Rx.Observable
                    .from(stations, null, null, SCHEDULER)
                    .pluck('chroniques')
                    .reduce((acc, chroniques) => {
                        _.forEach(chroniques, chro => {
                          acc[chro.id] = chro;
                        });
                        return acc;
                    }, {});
            })
            .shareReplay(1);
    };

    MetadataRepository.prototype._hydrateObservatoire = function (response) {
        return new Observatoire(response.name);
    };

    MetadataRepository.prototype._hydrateStation = function (dates, response) {
        const chroniques = {};
        const station = new Station(response.id, response.name, response.code, response.hasMeasures, chroniques);

        const hydrateChronique = this._hydrateChronique.bind(this, dates, station);

        return Rx.Observable
            .pairs(response.chronicles, SCHEDULER)
            .map((pair) => {
                pair[1] = hydrateChronique(pair[1]);
                return pair;
            })
            .reduce(
                (acc, pair) => {
                    acc[pair[0]] = pair[1];
                    return acc;
                },
                chroniques
            )
            .map((x) => {
                return station;
            });
    };

    MetadataRepository.prototype._hydrateChronique = function (dates, station, response) {
        const measureRepository = this._measureRepository;
        const fetcher = function (force) {
            return measureRepository
                .getMeasures(chronique.id, force);
            };
        const chronique = new Chronique(
            station,
            response.id,
            response.label,
            response.code,
            response.paramName,
            response.unitId,
            response.unitLabel,
            response.samplingType,
            response.reverseAxis,
            response.hasMeasures,
            fetcher
        );
        return chronique;
    };

    return MetadataRepository;
});

