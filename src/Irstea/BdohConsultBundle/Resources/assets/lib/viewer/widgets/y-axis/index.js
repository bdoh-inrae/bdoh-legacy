/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'translator',
    'rx',

    // --
    'jquery-ui/ui/widget',
    './style.css'
], ($, Translator, Rx) => {
    'use strict';

    let rowTemplate;
    (function () {
        const tpl = $('#cv-selection-row');
        rowTemplate = $(tpl.html());
        tpl.remove();
    })();

    // L'axe en entier

    return $.widget('viewer.yAxis', {
        _create() {
            const hidden = new Rx.BehaviorSubject({});
            const selection = this._selection = Rx.Observable
                .combineLatest(
                    [this.options.selection, hidden],
                    this._assignVisibility
                )
                .shareValue([]);

            this._tableRows = this.element.find('table.selection tbody');

            this._disposables = new Rx.CompositeDisposable(
                hidden,

                // Met à jour le tableau de chroniques
                selection.subscribeOnNext(this._updateTable, this)
            );

            this._on(this.element, {
                // Clic sur le bouton d'édition de la liste
                'click .select'(ev) {
                    ev.preventDefault();
                    this._trigger('select', ev, {side: this.options.side});
                },
                // Clic sur le toggle d'une chronique
                'click .toggle'(ev) {
                    ev.preventDefault();
                    const id = $(ev.target).closest('.chronique')
                        .data('item').id;
                    const flags = hidden.getValue();
                    flags[id] = !flags[id];
                    hidden.onNext(flags);
                }
            });
        },

        _destroy() {
            try {
                this._disposables.dispose();
            } catch (e) {
                // eslint-disable-next-line no-console
                console.error(e);
            }
        },

        _updateTable(selection) {
            const container = this._tableRows;
            const rows = container.find('tr');
            container.closest('table').toggle(selection.length > 0);
            if (selection.length < rows.length) {
                rows.slice(selection.length).remove();
            }
            let timeStepText = '';
            for(const item of selection){
                if(item.samplingType === 'cumulative' && item.timeStep){
                    let readableTimeStep;
                    switch(item.timeStep) {
                        case 3600:
                            readableTimeStep = Translator.trans('oneHour', {}, 'messages');
                            break;
                        case 86400:
                            readableTimeStep = Translator.trans('oneDay', {}, 'messages');
                            break;
                        default:
                            readableTimeStep = Translator.trans('nSeconds(%n%)', {'n':item.timeStep}, 'messages')
                    }
                    timeStepText = Translator.trans('histogramTimeStepsIs(%ts%)', {'ts': readableTimeStep}, 'viewer');
                    break;
                }
            }
            selection.forEach((item, i) => {
                let row;
                if (i < rows.length) {
                    row = $(rows[i]);
                } else {
                    row = rowTemplate.clone().addClass('chronique')
                        .appendTo(container);
                    row.find('.toggle').data('index', i);
                }
                row.data('item', item);
                row.find('.infoTimeStep')
                    .attr('title', timeStepText)
                    .tooltip({html:true})
                    .tooltip('fixTitle')
                    .toggle(item.samplingType === 'cumulative' && !!item.timeStep);
                row.find('.downSampled')
                    .attr('title', Translator.trans('subsampledInfo(%num%)', {'num':item.validCount}, 'viewer'))
                    .tooltip('fixTitle')
                    .toggle(item.downSampled);
                row.find('.noDataOnRange')
                    .attr('title', Translator.trans('timeSeriesNoMeasures', {}, 'viewer'))
                    .tooltip({html:true})
                    .tooltip('fixTitle')
                    .toggle(!item.series || item.series.length === 0);
                row.find('.title')
                    .text(item)
                    .attr('title', Translator.trans('chroniqueTitle(%station%, %typeParam%)',
                        {'station':item.stationName, 'typeParam':`${item.dataType} [${item.unit}]`}, 'viewer'))
                    .tooltip({html:true})
                    .tooltip('fixTitle');
                row.find('.title').text(`${item} [${item.unit.replace('unite constructeur', 'u.c.')}]`);
                row.find('.toggle')
                    .css({
                        opacity: item.shown ? 1.0 : 0.4,
                        color: `rgb(${item.color[0]}, ${item.color[1]}, ${item.color[2]})`
                    })
                    .tooltip({trigger:'hover'})
                    .tooltip('fixTitle');
                const chroniqueLink = row.find('a.chroniqueLink');
                const chroniqueLinkHref = rowTemplate.find('a.chroniqueLink').attr('href');
                chroniqueLink.attr('href', chroniqueLinkHref
                    .replace('_STATION_CODE_', item.stationCode)
                    .replace('_CHRONIQUE_CODE_', item.chroniqueCode)
                );
                row.find('.chroniqueLinkIcon')
                    .tooltip({trigger:'hover', container:$('body')})
                    .tooltip('fixTitle');
            });
        },

        _assignVisibility(items, hidden) {
            items.forEach((item) => {
                item.shown = !hidden[item.id];
            });
            return items;
        },

        observeSelection() {
            return this._selection;
        },
    });
});
