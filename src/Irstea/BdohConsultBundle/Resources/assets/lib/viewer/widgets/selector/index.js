/*
 * © 2016 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    'rx',
    'translator',
    // --
    'jquery-ui/ui/widget',
    'jquery-ui/ui/widgets/selectable',
    'jquery-ui/ui/widgets/draggable',
    'jquery-ui/ui/widgets/droppable',
    'jquery-ui/ui/widgets/sortable',
    './style.css'
], ($, Rx, Translator) => {

    let selectorTemplate;
    (function () {
        const tpl = $('#cv-selector');
        selectorTemplate = $(tpl.html());
        tpl.remove();
    })();

    const widget = $.widget(
        'viewer.selector',
        {
            options: {
                index: 0,
                side: null,
                selection: [],
                observatoire: null,
                stations: null
            },

            _create() {
                const element = this.element;
                const selection = new Rx.BehaviorSubject(this.options.selection);

                // Widgets internes

                const stationList = this._stationList =
                    element
                        .find('.stations .list')
                        .selectable({
                            cancel: '.loading',
                            filter: 'li.has-measures'
                        });

                this._chroniqueList = element.find('.chroniques .list');

                const selectionList = this._selectionList =
                    element
                        .find('.selection .list')
                        .sortable({});

                const confirmButton = element.find('button.confirm');

                const selectedStations = stationList
                    .onAsObservable('selectablestop')
                    .map(this._getSelectedStations.bind(this));

                // Tous les abonnements sont enregistrés dans un CompositeDisposable pour tout nettoyer à la fermeture
                this._disposables = new Rx.CompositeDisposable(
                    selection,
                    selectedStations,

                    // Met à jour le titre
                    this.options.observatoire
                        .subscribe(this._setTitle.bind(this)),

                    // Met à jour la liste des stations
                    this.options.stations
                        .subscribe(this._setStations.bind(this)),

                    // Met à jour la liste des chroniques
                    selectedStations
                        .map(this._getChroniqueStations.bind(this))
                        .combineLatest([selection, selectedStations], this._filterChroniques.bind(this))
                        .shareValue('emptySelection')
                        .subscribe(this._setChroniques.bind(this)),

                    // Met à jour l'affichage de la sélection
                    selection
                        .subscribe(this._setSelection.bind(this)),

                    // Met à jour la sélection quand on dépose ou retire une chronique
                    selectionList
                        .onAsObservable('sortstop')
                        .merge(
                            selectionList.onAsObservable('click', '.close')
                                .do((ev) => {
                                    $(ev.target).closest('.chronique')
                                        .remove();
                                })
                        )
                        .map(this._getSelection.bind(this))
                        .subscribe(selection),

                    // Bouton ok
                    selection
                        .sample(confirmButton.onAsObservable('click'))
                        .subscribe(this.options.callback),

                    // Gestion de la fermeture de la modale (bootstrap)
                    element
                        .onAsObservable('hidden.bs.modal')
                        .subscribe(() => {
                            element.remove();
                        })
                );

                // Affiche la modale
                element.modal('show');
            },

            _destroy() {
                this._disposables.dispose();
            },

            _setTitle() {
                this.element.find('h1.title').text(
                    `${Translator.trans('selector.graph', {}, 'viewer')} ${this.options
                        .index} - ${Translator.trans(`${this.options.side}-axis`, {}, 'viewer')}`
                );
            },

            _setStations(stations) {
                const list = this._stationList;
                list.empty();
                $.each(stations, (id, station) => {
                    $(`<li class="station"><strong>${station.name}</strong> - ${station.code}</li>`)
                        .toggleClass('has-measures', station.hasMeasures)
                        .data('station', station)
                        .appendTo(list);
                });
            },

            _getSelectedStations() {
                const stations = [];
                this._stationList.find('.station.ui-selected').each((i, el) => {
                    stations.push($(el).data('station'));
                });
                return stations;
            },

            _getChroniqueStations(stations) {
                const chroniques = {};
                $.each(stations, (id, station) => {
                    $.extend(chroniques, station.chroniques);
                });
                return chroniques;
            },

            _filterChroniques(chroniques, selection, stations) {
                if (stations.length === 0) {
                    return 'emptySelection';
                } else if ($.isEmptyObject(chroniques)) {
                    return 'noChronicles';
                }

                const selected = {};
                selection.forEach((chronique) => {
                    selected[chronique.id] = true;
                });

                const first = selection[0];
                let available = 0;
                const result = [];

                $.each(chroniques, (idx, chronique) => {
                    if (selected[chronique.id] || !chronique.hasMeasures) {
                        return;
                    }
                    available++;
                    if (!first || chronique.isDisplayableWith(first)) {
                        result.push(chronique);
                    }
                });

                if (available === 0) {
                    return 'noMoreChronicles';
                } else if (result.length === 0) {
                    return 'noCompatibleChronicles';
                }

                return result;
            },

            _setChroniques(chroniques) {
                const list = this._chroniqueList;
                list.empty();

                if (typeof chroniques === 'string') {
                    list.html(Translator.trans(`selector.chroniques.${chroniques}`, {}, 'viewer'));
                    return;
                }

                $.each(chroniques, (id, chronique) => {
                    const widget = $(`<li class="chronique"><strong>${chronique}</strong> - ${ 
                        chronique.paramName} [${chronique.unitLabel}]</li>`)
                        .data('chronique', chronique)
                        .toggleClass('has-measures', chronique.hasMeasures)
                        .appendTo(list);

                    if (chronique.hasMeasures) {
                        widget.draggable({
                            revert: 'invalid',
                            connectToSortable: '.selection .list'
                        });
                    }
                });
            },

            _setSelection(chroniques) {
                const list = this._selectionList;
                list.empty();
                $.each(chroniques, (id, chronique) => {
                    const c = $(`<li class="chronique"><button class="close pull-right">&times;</button>${ 
                        chronique} [${chronique.unitLabel}]</li>`)
                        .data('chronique', chronique)
                        .appendTo(list);
                });
            },

            _getSelection() {
                const sel = [];
                this._selectionList.find('.chronique').each((i, el) => {
                    sel.push($(el).data('chronique'));
                });
                return sel;
            }
        }
    );

    widget.open = function (options) {
        return selectorTemplate.clone().appendTo($('body'))
            .selector(options);
    };

    return widget;
});
