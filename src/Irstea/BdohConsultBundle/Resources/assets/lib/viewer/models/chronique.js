/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define(['./serie'], (Serie) => {
    'use strict';

    function Chronique(station, id, label, code, paramName, unitId, unitLabel, samplingType, reverseAxis, hasMeasures, measuresFetcher) {
        this.station = station;
        this.id = id;
        this.label = label;
        this.code = code;
        this.paramName = paramName;
        this.unitId = unitId;
        this.unitLabel = unitLabel;
        this.samplingType = samplingType;
        this.reverseAxis = reverseAxis;
        this.hasMeasures = hasMeasures;
        this._measuresFetcher = measuresFetcher;
        this._measures = null;
        this.measuresDisposed = false;
    }

    Chronique.prototype.dispose = function() {
        const oldMeasures = this._measures;
        this._measures = this._measuresFetcher = this.station = null;
        oldMeasures && oldMeasures.dispose();
    };

    Chronique.prototype.getMeasures = function() {
        if (!this._measures || this.measuresDisposed) {
            this._measures = this._measuresFetcher(this.measuresDisposed);
        }
        return this._measures;
    };

    Chronique.prototype.toString = function() {
        return `${this.station} / ${this.code}`;
    };

    Chronique.prototype.isSameAs = function(other) {
        return (other instanceof Chronique) && this.id === other.id;
    };

    Chronique.prototype.isDisplayableWith = function(other) {
        return (other instanceof Chronique) && this.unitId === other.unitId && this.reverseAxis === other.reverseAxis;
    };

    return Chronique;

});
