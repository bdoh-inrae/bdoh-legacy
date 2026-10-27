/**
 * Step 2 : 'station', timezone and 'chroniques'
 */
define([
    'jquery',
    './main',
    './step3',
    '@IrsteaBdohBundle/lib/switch-default-list-widget',
    '@IrsteaBdohBundle/lib/finite-list-widget'
], ($) => {

    $.measureImport.step2 = function () {
        const measureImport = $.measureImport.init();
        const data = measureImport.data;
        const form = measureImport.form;
        const timezone = measureImport.field('timezone');
        const chroniques = measureImport.field('chroniques');
        const defaultElementFiniteList = {
            name: 'doNotSelect',
            value: data.ignoreColumn,
            titleDelete: data.titleDelete,
            titleRedo: data.titleRedo,
        };
        /*
        // la selection des chroniques à importer n'est plus gérée par le controller / importer
        const defaultElementSwitchList = {
            name: 'doNotSelect',
            value: data.ignoredTimeSeries,
            titleDelete: data.titleDelete,
            titleRedo: data.titleRedo,
        };
        */
        const stateOfSubmit = measureImport.stateOfSubmit(() => {
            const selected = chroniques.find('ol > li[name != "doNotSelect"]').length;
            return (selected > 0 && timezone.val() !== '');
        });
        let lists;

        // No 'chronique' read => adds a 'chroniques' box
        if (data.chroniquesAvailable) {
            lists = $.finiteList(chroniques.selector, data.chroniquesAvailable, defaultElementFiniteList, data.expectedChroniques);
            chroniques.find('[name = "in"]').append(lists.In);
            chroniques.find('[name = "out"]').append(lists.Out)
                .on('change.finiteList', stateOfSubmit);
        } else {
            // All 'chroniques' read => adds a 'delete' tool for each
            /*
            // la selection des chroniques à importer n'est plus gérée par le controller / importer
            chroniques.find('ol').switchDefaultList(defaultElementSwitchList)
                .on('change.switchDefaultList', stateOfSubmit);
            */
        }

        // Enable or not the "submit button"
        timezone.on('change', stateOfSubmit);

        // Gets the code (attribute "name") of selected 'chroniques'
        function getSelectedChroniques() {
            const listChronDOM = chroniques.find('ol > li');
            const listChron = {};

            for (let i = 0; i < listChronDOM.length; ++i) {
                const code = listChronDOM.eq(i).attr('name');
                if (code !== 'doNotSelect') {
                    listChron[i] = code;
                }
            }
            return listChron;
        }

        // Send 'timezone' and 'chronqiues' to the server
        form.ajaxForm({
            dataType: 'json',
            beforeSend() {
                $('#modalStep2')
                    .modal({backdrop: 'static'})
                    .modal('show');
            },
            beforeSerialize($form, options) {
                options.data = {chroniques: getSelectedChroniques()};
            },
            success(data) {
                $('#modalStep2')
                    .modal('hide')
                    .one('hidden.bs.modal', () => {
                        measureImport.nextStep(data, measureImport.step3);
                    });
            }
        });
    };


    /**
     * Pre-step 2 : ask a 'station'
     */
    $.measureImport.step2.askStation = function () {
        let measureImport = $.measureImport.init(),
            form = measureImport.form,
            cancel = measureImport.cancel(),
            station = measureImport.field('station');

        // Enable or not the "submit button"
        station.on('change', measureImport.stateOfSubmit(() => {
            return station.val() !== '';
        }));

        // Send 'station' to the server
        form.ajaxForm({
            dataType: 'json',
            success(data) {
                measureImport.nextStep(data, measureImport.step2);
            }
        });
    };

});
