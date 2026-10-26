// Le formulaire d'une chronique continue : les options d'export suivent l'échantillonnage choisi. Sur
// le jQuery de Sonata. Repris de Resources/assets/entries/crud/edit-chronique-continue.js de l'ancien
// BDOH.
(function ($) {
    $(() => {

        const paramEl = $('[data-oriented-samplings]');
        const orientedSamplings = paramEl.data('oriented-samplings');
        const cumulativeSamplings = paramEl.data('cumulative-samplings');
        const samplingOrders = paramEl.data('sampling-orders');
        const outputTimeSteps = paramEl.data('output-time-steps');

        const samplingSelector = $('select[id$="_echantillonnage"]');
        const outputSamplingBoxes = $('[id$="_echantillonnagesSortieLicites"] input[type="checkbox"]');
        const directionRadios = $('[id$="_directionMesure"] input[type="radio"]');
        const cumulativeUnitSelector = $('select[id$="_uniteCumul"]');
        const outputTimeStepBoxes = $('[id$="_optionsEchantillonnageSortie"] input[type="checkbox"]');

        function getInputSamplingId() {
            return samplingSelector.val();
        }

        function updateCumulativeUnit() {
            let isRequired = false;

            outputSamplingBoxes
                .filter(':checked:visible')
                .each((_, box) => {
                    const $box = $(box);
                    const boxId = $box.val();
                    if (cumulativeSamplings[boxId]) {
                        isRequired = true;
                    }
                });

            cumulativeUnitSelector
                .attr({required: isRequired, disabled: !isRequired});

            cumulativeUnitSelector
                .closest('.form-group')
                .toggle(isRequired);
        }

        function enableCheckbox(box, enable) {
            $(box)
                .attr({'disabled': !enable})
                .closest('.checkbox')
                .toggle(enable);
        }

        function requireOutputTimeSteps() {
            let require = false;
            outputSamplingBoxes
                .filter(':checked:visible')
                .each((_, box) => {
                    if (orientedSamplings[$(box).val()]) {
                        require = true;
                    }
                });
            return require;
        }

        function updateAvailableOutputTimesteps() {
            const showTimesteps = requireOutputTimeSteps();
            const inputSamplingId = getInputSamplingId();

            outputTimeStepBoxes.each((_, box) => {
                const boxId = $(box).val();
                const isAvailable = +outputTimeSteps[boxId] === +inputSamplingId;
                enableCheckbox(box, isAvailable);
            });

            outputTimeStepBoxes
                .closest('.form-group')
                .toggle(showTimesteps);
        }

        function updateAvailableOutputSamplings() {
            const selectedOrder = samplingOrders[getInputSamplingId()];

            outputSamplingBoxes.each((_, box) => {
                const boxOrder = samplingOrders[$(box).val()];
                const isAvailable = boxOrder >= selectedOrder;
                enableCheckbox(box, isAvailable);
            });

            updateCumulativeUnit();
            updateAvailableOutputTimesteps();
        }

        function updateDirectionSelector() {
            const isOriented = orientedSamplings[getInputSamplingId()] === true;
            directionRadios
                .attr({disabled: !isOriented})
                .closest('.form-group')
                .toggle(isOriented);
        }

        samplingSelector.on('change.bdoh', () => {
            updateAvailableOutputSamplings();
            updateDirectionSelector();
        });

        outputSamplingBoxes.on('ifChanged.bdoh', () => {
            updateCumulativeUnit();
            updateAvailableOutputTimesteps();
        });

        updateAvailableOutputSamplings();
        updateDirectionSelector();

    });
})(jQuery);
