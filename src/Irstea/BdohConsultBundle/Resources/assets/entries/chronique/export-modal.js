// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
/**
 * Javascript definitions for 'chroniques_export' macro in 'IrsteaBdohConsultBundle::macros.html.twig' template.
 */
const $ = require('jquery');
const _ = require('lodash');
const Translator = require('translator');

require('jquery-ui/ui/widget');
require('@IrsteaBdohBundle/lib/date-time/range-picker');

function alwaysTrue() {
    return true
}

// Les formats qui disent une période (début et fin) : BDOH, et le CSV (dev/plans/2026-11-13_fair-exports.md)
function isBdohFormat(fmt) {
    return fmt === 'Bdoh' || fmt === 'Csv';
}

// La période ne dépend plus du pas de temps : l'ancien refusait ici plus de 2 ans par minute de pas, une garde technique
// que le serveur ne vérifiait pas. Le serveur compte désormais le volume de la demande (ExportController::volume ;
// Lou, 2026-11-16, dev/differences.md, « Le volume d'un export »).

const allowedTypes = {
    'Bdoh': ['identical', 'instantaneous', 'mean', 'cumulative'],
    'Qtvar': ['identical', 'instantaneous'],
    'Csv': ['identical', 'instantaneous', 'mean', 'cumulative']
};

const exportTypes = {
    identical: {
        timeStepSelector: 'none',
        isFormatValid: alwaysTrue,
        isTimeStepValid: alwaysTrue
    },
    instantaneous: {
        timeStepSelector: 'free',
        isFormatValid: alwaysTrue,
        isTimeStepValid(timeStep) {
            return 0 < timeStep && timeStep <= 1440;
        }
    },
    mean: {
        timeStepSelector: 'list',
        isFormatValid: isBdohFormat,
        isTimeStepValid: alwaysTrue
    },
    cumulative: {
        timeStepSelector: 'list',
        isFormatValid: isBdohFormat,
        isTimeStepValid: alwaysTrue
    }
};

$.widget(
    'bdoh.exportModal',
    {
        options: {},

        _create() {
            this._dateTimeRangePicker = this.element.find('.daterange').dateTimeRangePicker({
                minDate: this.options.dateFirst,
                maxDate: this.options.dateLast
            });

            this._timezone = this.element.find('[name="timezone"]');
            this._exportType = this.element.find('[name="exportType"]');
            this._exportFormat = this.element.find('[name="exportFormat"]');
            this._timeStepSelectors = {
                free: this.element.find('[name="instantStep"]'),
                list: this.element.find('[name="meanCumulStep"]')
            };
            this._chroniques = this.element.find('[name="chroniques[]"]');
            this._termsOfUses = this.element.find('[name="termsOfUses"]');
            this._submit = this.element.find('[type="submit"]');

            const selfUpdate = _.bindKey(this, '_update');
            this._on(this.element, {
                'change': selfUpdate,
                'click input[type="number"]': selfUpdate,
                'keyup input[type="number"]': selfUpdate
            });

            this._dateTimeRangePicker.dateTimeRangePicker('observeStatus')
                .subscribe(selfUpdate);

            // Par défaut, toute la période, de la première à la dernière mesure (Lou, 2026-11-16) ; le bouton
            // « Exporter » du graphique pose ensuite la sienne
            if (this.options.dateFirst && this.options.dateLast) {
                this.setDateRange({start: moment.utc(this.options.dateFirst), end: moment.utc(this.options.dateLast)});
            }

            selfUpdate();
        },

        setDateRange(range) {
            this._dateTimeRangePicker.dateTimeRangePicker('setDateTimeRange', range);
        },

        _update(showFeedback) {
            const status = this._dateTimeRangePicker.dateTimeRangePicker('getStatus');
            const tz = this._getTimeZone();
            this._updateAvailableTypes();
            const exportType = this._getExportType();
            this._updateTimeStepSelector(exportType);
            const isValid = this._validate(status, exportType, showFeedback);
            this._updateRangeFeedback(isValid, status.value.start, status.value.end, tz);
        },

        _getTimeZone() {
            return parseInt(this._timezone.val(), 10);
        },

        _getTimeStep() {
            const exportType = this._getExportType();
            switch (exportType.timeStepSelector) {
                case 'none':
                    return 0;
                case 'free':
                    return parseInt(this._timeStepSelectors.free.val(), 10);
                case 'list':
                    return parseInt(this._timeStepSelectors.list.children(':selected').data('time-step'), 10) / 60;
            }
        },

        _getExportType() {
            const type = this._exportType.val();
            if (!(type in exportTypes)) {
                throw `Invalid export type: ${type}`;
            }
            return exportTypes[type];
        },

        _updateRangeFeedback(show, start, end, tz) {
            const el=this.element.find('.range-in-timezone');
            const format = this._dateTimeRangePicker.dateTimeRangePicker('option', 'format');
            const s = start.clone()
                .utcOffset(60 * tz)
                .format(format);
            const e = end.clone()
                .utcOffset(60 * tz)
                .format(format);
            if(s !== 'null' && e !== 'null'){
                el.text(`${s}${' - '}${e}`).show();
            }else {
                el.text('').hide();
            }
        },

        _updateAvailableTypes() {
            const format = this._exportFormat.val();
            const types = this._exportType[0];
            $.each(types, (_, type) => {
                type.disabled = !allowedTypes[format].includes(type.value);
                if (type.selected && type.disabled) {
                    type.selected = false;
                }
                if(type.disabled){
                    $(type).hide();
                }else{
                    $(type).show();
                }
            });
        },

        _updateTimeStepSelector(exportType) {
            $.each(this._timeStepSelectors, (type, selector) => {
                const group = selector.closest('.form-group');
                if (type === exportType.timeStepSelector) {
                    group.show(200);
                } else {
                    group.hide(200);
                }
            });
        },

        _validate(status, exportType, showFeedback) {
            const startError = this._getRangeError(status.start);
            const endError = this._getRangeError(status.end);
            const errors = {
                start: startError ? `start.${startError}` : null,
                end: endError ? `end.${endError}` : null,
                timeStep: this._getTimeStepError(exportType, this._getTimeStep()),
                termsOfUses: this._getTermsOfUsesError(),
                chroniques: this._getChroniquesError()
            };

            this._showErrors(showFeedback ? errors : {});

            const isValid = ! _.some(errors);
            this._submit
                .toggleClass('btn-success', isValid)
                .attr('disabled', isValid ? null : 'disabled')
                .prop('disabled', !isValid);
            return isValid;
        },

        _getRangeError(status) {
            return status.isValid ? false : status.status;
        },

        _getTimeStepError(exportType, timeStep) {
            return exportType.isTimeStepValid(timeStep) ? false : 'invalid-time-step';
        },

        _getTermsOfUsesError() {
            // La case n'existe que pour une chronique cachée ; le reste est sous Licence Ouverte
            if (this._termsOfUses.length && !this._termsOfUses.prop('checked')) {
                return 'check-term-of-uses';
            }
            return false;
        },

        _getChroniquesError() {
            if (!this._chroniques.val()) {
                return 'select-a-chronique';
            }
            return false;
        },

        _showErrors(errors) {
            const el = this.element;

            el
                .find('.error-report')
                .hide()
                .find('.errors')
                .empty();

            this._showError(el.find('.daterange .start'), errors.start);
            this._showError(el.find('.daterange .end'), errors.end);
            this._showError(el.find('.time-step.form-group'), errors.timeStep);
            this._showError(this._termsOfUses.closest('.form-group'), errors.termsOfUses);
            this._showError(this._chroniques.closest('.form-group'), errors.chroniques);
        },

        _showError(widget, error) {
            widget
                .toggleClass('has-success', error === false)
                .toggleClass('has-error', !!error);
            if (!error) {
                return
            }
            const report = this._findErrorReport(widget);
            report
                .show()
                .find('.errors')
                .append(
                    $('<div></div>').text(Translator.trans(`validation.${error}`))
                );
        },

        _findErrorReport(widget) {
            return widget.closest('.form-group').find('.error-report')
                .first();
        }
    }
);

$(() => {
    $('[data-export-modal] a[href="#chronique-export-help"]').tooltip();
    // Chaque fenêtre de la page a son widget, créé à sa première ouverture : l'ancien ne le créait que pour la première
    // fenêtre ouverte (« one »), si bien qu'une autre, ouverte ensuite sur la même page (la liste d'une station, d'un
    // jeu de données), n'avait ni sélecteur de dates ni validation (dev/differences.md)
    $(document).on('show.bs.modal', '[data-export-modal]', (ev) => {
        const modal = $(ev.target);
        if (!modal.is('[data-export-modal]') || modal.exportModal('instance')) {
            return;
        }
        modal.exportModal({
            dateFirst: modal.data('min-date'),
            dateLast: modal.data('max-date')
        });
    });
});
