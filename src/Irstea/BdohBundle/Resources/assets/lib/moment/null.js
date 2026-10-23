/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
const _ = require('lodash');
const BaseMoment = require('moment');

const nullAccessor = function (...args) {
    return args.length > 0 ? this : NaN
};
const returnThis = function () {
    return this;
};
const returnFalse = function () {
    return false;
};

// Singleton "null"
const nullSingleton = {
    isNull() {
        return true;
    },

    inspect() {
        return 'moment.null()';
    },

    toDate() {
        return 'null';
    },

    toString() {
        return 'null';
    },

    toISOString() {
        return 'null';
    },

    format() {
        return 'null';
    },

    isSame(date) {
        return moment.isNull(date);
    },

    isBeforeOrSame(date) {
        return moment.isNull(date);
    },

    isAfterOrSame(date) {
        return moment.isNull(date);
    },

    isValid: returnFalse,

    isBefore: returnFalse,
    isAfter: returnFalse,

    isLeapYear: returnFalse,
    isDST: returnFalse,
    isDSTShifted: returnFalse,

    second: nullAccessor,
    seconds: nullAccessor,
    millsecond: nullAccessor,
    milliseconds: nullAccessor,
    minute: nullAccessor,
    minutes: nullAccessor,
    hour: nullAccessor,
    hours: nullAccessor,
    date: nullAccessor,
    dates: nullAccessor,
    day: nullAccessor,
    days: nullAccessor,
    weekday: nullAccessor,
    dayOfYear: nullAccessor,
    week: nullAccessor,
    weeks: nullAccessor,
    isoWeek: nullAccessor,
    isoWeeks: nullAccessor,
    month: nullAccessor,
    months: nullAccessor,
    quarter: nullAccessor,
    quarters: nullAccessor,
    year: nullAccessor,
    years: nullAccessor,
    weekYear: nullAccessor,
    isoWeekYear: nullAccessor,
    isoWeeksInYear: nullAccessor,
    get: nullAccessor,
    unix: nullAccessor,
    valueOf: nullAccessor,
    zone: nullAccessor,
    utcOffset: nullAccessor,
    diff: nullAccessor,
    daysInMonth: nullAccessor,

    add: returnThis,
    subtract: returnThis,
    startOf: returnThis,
    endOf: returnThis,
    utc: returnThis,
    local: returnThis,
    clone: returnThis
};

// Extension de la librairie

function moment(date, ...args) {
    return moment.isNull(date) ? nullSingleton : BaseMoment.call(this, date, ...args);
}
moment.prototype = moment.fn = BaseMoment.prototype;

const baseUtc = BaseMoment.utc;
const baseLocal = BaseMoment.local;
const baseMin = BaseMoment.min;
const baseMax = BaseMoment.max;

_.assign(
    moment,
    BaseMoment,
    {
        null() {
            return nullSingleton;
        },

        isNull(value) {
            return value === null || value === nullSingleton;
        },

        nonNullDates(...ms) {
            return _.reject(ms, moment.isNull);
        },

        utc(date, ...args) {
            return moment.isNull(date) ? nullSingleton : baseUtc(date, ...args);
        },

        local(date, ...args) {
            return moment.isNull(date) ? nullSingleton : baseLocal(date, ...args);
        },

        min(...dates) {
            const nonNulls = _.reject(dates, moment.isNull);
            return nonNulls.length > 0 ? baseMin(...nonNulls) : nullSingleton;
        },

        max(...dates) {
            const nonNulls = _.reject(dates, moment.isNull);
            return nonNulls.length > 0 ? baseMax(...nonNulls) : nullSingleton;
        }
    }
);

const baseIsSame = BaseMoment.fn.isSame;
const baseIsBeforeOrSame = BaseMoment.fn.isBeforeOrSame;
const baseIsAfterOrSame = BaseMoment.fn.isAfterOrSame;
const baseIsBefore = BaseMoment.fn.isBefore;
const baseIsAfter = BaseMoment.fn.isAfter;

_.assign(
    BaseMoment.fn,
    {
        isNull() {
            return false;
        },

        isSame(date) {
            return !moment.isNull(date) && baseIsSame.call(this, date);
        },

        isBeforeOrSame(date) {
            return !moment.isNull(date) && baseIsBeforeOrSame.call(this, date);
        },

        isAfterOrSame(date) {
            return !moment.isNull(date) && baseIsAfterOrSame.call(this, date);
        },

        isBefore(date) {
            return !moment.isNull(date) && baseIsBefore.call(this, date);
        },

        isAfter(date) {
            return !moment.isNull(date) && baseIsAfter.call(this, date);
        },
    }
);

module.exports = moment;
