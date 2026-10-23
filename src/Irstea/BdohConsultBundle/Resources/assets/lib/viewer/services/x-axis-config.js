/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'translator',
    '@IrsteaBdohBundle/lib/moment',
    '@IrsteaBdohBundle/lib/jqplot'
], ($, Translator, moment) => {

    const ds=Translator.trans('dateSeparator', {}, 'messages');

    const formats = {
        'second': `DD${ds}MM  HH:mm:ss.SSS`,
        'minute': `DD${ds}MM${ds}YY  HH:mm:ss`,
        'hour': `DD${ds}MM${ds}YYYY  HH[h]mm`,
        'day': `DD${ds}MM${ds}YYYY`,
        'month': `DD${ds}MM${ds}YYYY`,
        'year': `DD${ds}MM${ds}YYYY`
    };

    const intervals = $.map(
        [
            [1, 'second'], [2, 'second'], [5, 'second'], [10, 'second'], [30, 'second'],
            [1, 'minute'], [2, 'minute'], [5, 'minute'], [10, 'minute'], [30, 'minute'],
            [1, 'hour'], [2, 'hour'], [6, 'hour'], [12, 'hour'],
            [1, 'day'], [2, 'day'], [5, 'day'], [10, 'day'],
            [1, 'month'], [2, 'month'], [6, 'month'],
            [1, 'year']
        ],
        ([amount, unit]) => ({
            amount,
            unit,
            duration: moment.duration(amount, unit).asMinutes(),
            format: formats[unit]
        })
    );

    function tickInterval(start, end, targetNumberOfTicks) {
        const targetInterval = end.diff(start, 'minutes', true) / targetNumberOfTicks;
        let best;
        let bestDelta;
        intervals.forEach((interval) => {
            const delta = Math.abs(targetInterval - interval.duration);
            if (!best || delta <= bestDelta) {
                best = interval;
                bestDelta = delta;
            }
        });
        return best;
    }

    function momentFormatter(format, val) {
        return moment(val).utc()
            .format(format);
    }

    function createXAxisConfig({start, end}) {
        if (!moment.isMoment(start)) {
            return {'error': `invalid range start: ${start}`};
        }
        if (!moment.isMoment(end)) {
            return {'error': `invalid range end: ${end}`};
        }
        if (start.isSame(end)) {
            end.add(1, 'ms');
        }

        const interval = tickInterval(start, end, 10);
        const firstTick = start.utc().ceil(interval.amount, interval.unit);
        const ticks = [];

        if (start.isBefore(firstTick)) {
            ticks.push(start.valueOf())
        }
        const d = firstTick.clone();
        while (d.isBefore(end)) {
            ticks.push(d.valueOf());
            d.add(interval.amount, interval.unit)
        }
        ticks.push(end.valueOf());

        return {
            label:`${Translator.trans('Date')} [${Translator.trans('UTC')}]`,
            min: start.valueOf(),
            max: end.valueOf(),
            ticks,
            tickRenderer: $.jqplot.CanvasAxisTickRenderer,
            tickOptions: {
                angle: -90,
                formatString: interval.format,
                formatter: momentFormatter,
                enableFontSupport: true,
                fontSize: '0.95em'
            }
        };
    }

    return createXAxisConfig;
});

