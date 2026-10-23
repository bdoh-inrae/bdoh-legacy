/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const moment = require('./null');

moment.fn.floor = function(precision, unit) {
    const offset = 'date' === unit ? 1 : 0;
    const floor = this.clone().startOf(unit);
    return floor.set(unit, offset + Math.floor((floor.get(unit) - offset) / precision) * precision);
};

moment.fn.ceil = function(precision, unit) {
    const floor = this.floor(precision, unit);
    return this.isAfter(floor) ? floor.add(precision, unit) : floor;
};

moment.fn.round = function(precision, unit) {
    const floor = this.floor();
    const ceil = floor.clone().add(precision, unit);
    const mean = (floor.valueOf() + ceil.valueOf()) / 2;
    return this.valueOf() <= mean ? floor : ceil;
};

module.exports = moment;
