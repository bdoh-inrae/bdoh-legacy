/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    './serie',
    '../services/ref-count-disposable'
], (Serie, RefCountDisposable) => {
    'use strict';

    function Measures(json) {
        this.series = [];
        this._refCount = 0;
        this.color = undefined;
        this.shown = true;
        this.id = json.chronicleId;
        this.name = json.name;
        this.stationName = json.station;
        this.dataType = json.dataType;
        this.unit = json.dataUnit;
        this.samplingType = json.samplingType;
        this.reverseAxis = json.reverseAxis;
        this.pointCount = json.nPts;
        this.validCount = json.validMeasureCount;
        this.downSampled = json.tooManyPoints;
        this.timeStep = json.timeStep;
        this.stationCode = json.stationCode;
        this.chroniqueCode = json.chroniqueCode;
        this.chroniqueClass = json.chroniqueClass;
        this.checkpointsOnCumulative = json.checkpointsOnCumulative;
        this.ahead = json.ahead;
    }

    Measures.prototype.acquire = function () {
        return new RefCountDisposable(this);
    };

    Measures.prototype.dispose = function () {
        if (this.disposed) {
            return;
        }
        this.disposed = true;
        const oldSeries = this.series;
        this.series = null;
        oldSeries.forEach((s) => {
            s.dispose();
        });
    };

    Measures.prototype.isEmpty = function () {
        return this.pointCount === 0;
    };

    Measures.prototype.toString = function () {
        return this.name;
    };

    return Measures;
});
