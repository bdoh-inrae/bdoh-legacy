define([
    'jquery',
    // --
    'jquery-form/jquery.form',
    '@IrsteaBdohBundle/lib/date-time/picker',
    '@IrsteaBdohBundle/lib/datatables',
    './import',
    './export',
    './step1',
    './step2',
    './style.css'
], ($) => {

    $(() => {

        const Translator = require('translator');
        const pathToGetChroniqueCalculee = $('[data-chroniques-list]').data('chroniques-list');
        const pathToViewChroniqueCalculee = $('[data-chroniques-get]').data('chroniques-get');

        //First the big divs from the page
        const blocks = ['Principale', 'Secondaire'];
        const $principale = $('#chroniqueMerePrincipale');
        const $secondaire = $('#chroniqueMereSecondaire');
        //then the smaller divs
        const $uniteDiv = $('#uniteDiv');
        const $majDiv = $('#majDiv');
        const $coefficientDiv = $('#coefficientDiv');
        const $genealogieDiv = $('#genealogieDiv');
        const $genealogieEnDiv = $('#genealogieEnDiv');
        const $btnDiv = $('#btnDiv');
        //then the value fields
        const $chroniqueCalculee = $('#chroniqueCalculee');
        const $unite = $('#uniteChroniqueFille');
        const $maj = $('#majChroniqueFille');
        const $coefficient = $('#coefficient');
        const $genealogie = $('#genealogie');
        const $genealogieEn = $('#genealogieEn');
        const $propagateGroup = $('#propagateGroup');
        const $divsDelaiPropagation = $('.div-delai-propagation');
        const $stationMerePrincipale = $('#selectStationPrincipale');
        const $stationMereSecondaire = $('#selectStationSecondaire');
        const $chroniqueMerePrincipale = $('#selectChroniqueMerePrincipale');
        const $chroniqueMereSecondaire = $('#selectChroniqueMereSecondaire');

        const $formTransformation = $('#formTransformation');
        const $formButton = $formTransformation.find("[type='submit']");
        const $transformationErrors = $('#errors-transformation');
        const $transformationSubmitted = $('#submitted-transformation');
        const $lineSubmitted = $('#submitted-info');
        const $lineSubmittedHtml = $lineSubmitted.html();
        const $closeSubmitted = $('#submitted-close');

        let isFirstEdition = false;

        //On opening, we hide almost everything
        function hideData() {
            let $elementsToHide = $principale
                .add($secondaire)
                .add($uniteDiv)
                .add($coefficientDiv)
                .add($genealogieDiv)
                .add($genealogieEnDiv)
                .add($majDiv)
                .add($btnDiv)
                .add($propagateGroup)
                .add($divsDelaiPropagation);

            blocks.forEach((block) => {
                $elementsToHide = $elementsToHide.add($(`#div-value-limits-${block}`));
            });

            // Réinitialisation des stations et des chroniques mères
            $chroniqueMerePrincipale.find('option').show();
            $chroniqueMereSecondaire.find('option').show();
            $stationMerePrincipale.find('option[value="-1"]').selected();
            $stationMereSecondaire.find('option[value="-1"]').selected();
            $chroniqueMerePrincipale.find('option[value="-1"]').selected();
            $chroniqueMereSecondaire.find('option[value="-1"]').selected();
            filterChroniquesMeresByStation();

            // Réinitialisation des barèmes
            initBaremes('Principale');

            $elementsToHide.hide();
            $transformationErrors.hide();
            $transformationSubmitted.hide();
        }

        //What to do when ChroniqueCalculee is chosen
        function getChroniqueCalculee() {

            // Reinitialize everything
            hideData();

            //A ChroniqueCalculee is selected
            if ($chroniqueCalculee.val() !== '-1') {

                //Then we get the info for that ChroniqueCalculee
                $.ajax({
                    url: pathToGetChroniqueCalculee,
                    data: {id: $chroniqueCalculee.val()}
                }).done((data) => {
                    // Is this the 1st time we edit this chronicle?
                    isFirstEdition = data['isFirstEdition'];

                    //Assign the right values
                    $unite.text(data.unite);
                    $coefficient.val(data.coefficient);
                    $genealogie.val(data.genealogie);
                    $genealogieEn.val(data.genealogieEn)
                    //Show the result
                    $principale.add($uniteDiv)
                        .add($genealogieDiv)
                        .add($genealogieEnDiv)
                        .add($coefficientDiv)
                        .show();

                    if (!isFirstEdition) {
                        $maj.text(data.maj);
                        $majDiv.show();
                    }

                    for (const b in blocks) {
                        const block = blocks[b];
                        let chroniqueMereId = data[`chroniqueMere${block}`];
                        if (typeof chroniqueMereId === 'undefined'){
                            chroniqueMereId = -1;
                        }
                        const valueToSelect = isFirstEdition ? -1 : chroniqueMereId;
                        const $selectParent = $(`#selectChroniqueMere${block}`);

                        $selectParent.children(`option[value=${valueToSelect}]`).selected();
                        initBaremes(block, data[`baremes${block}`]);

                        // Propagation delays
                        const $propagationDelay = $(`input[name=delaiPropagation${block}]`);
                        $propagationDelay.val(data[`delaiPropagation${block}`] || 0);

                        // If the parent chronicle select is disabled, then put its value in the appropriate hidden input
                        // Copy propagation delays as well
                        const $hiddenParent = $(`#hiddenChroniqueMere${block}`);
                        const $visibleDelay = $(`#delaiPropagation${block}`);
                        const $hiddenDelay = $(`#hiddenDelaiPropagation${block}`);
                        $hiddenParent.val($selectParent.val());
                        $hiddenDelay.val($visibleDelay.val());
                        toggleControl($hiddenParent.add($hiddenDelay), !isFirstEdition);

                        // Value limit policies
                        const $valueLimitPolicy = $(`select[name=valueLimits${block}]`);
                        $valueLimitPolicy.val(data[`valueLimitPolicy${block}`] || 'chronique.lq_ld.options.true_value');
                        const $valueLimitPlaceholder = $(`#limitPlaceholder${block}`);
                        $valueLimitPlaceholder.val(data[`valueLimitPlaceholder${block}`] || 0.0);
                    }

                    setLockedChroniquesMeres(!isFirstEdition);
                    updateValueLimitVisibilities();

                    if (data['chroniqueMereSecondaire']) {
                        $secondaire.show();
                    }

                    if (data['hasChildren']) {
                        $('#propagateSpanId').text(Translator.transChoice('measure.import.question.computeChildren', data['childrenCount'], {}, 'messages'));
                        $('#propagateHelpId').attr('data-content', data['childrenList'])
                            .attr('data-original-title', Translator.transChoice('measure.import.help.children', data['childrenCount']), {}, 'messages');
                        // Displays, in a bootstrap-popover, the list of the descendant time series
                        $('.childrenHelp').popover({trigger: 'hover', 'html': true});
                        $propagateGroup.show();
                    }

                    setUseCoefficient();
                });
            }
        }

        function filterChroniquesMeresByStation(block) {
            if (!block) {
                for (const b in blocks) {
                    filterChroniquesMeresByStation(blocks[b]);
                }
            } else {
                const stationId = $(`#selectStation${block}`).val();
                const options = $(`#selectChroniqueMere${block}`).children();
                const blankOption = options.filter(':not([station-id])');
                options.hide();
                const selectedTSId = $chroniqueCalculee.val();
                options.filter(`[station-id=${stationId}]:not([value=${selectedTSId}])`)
                    .add(blankOption)
                    .show();
                if (!options.filter('[selected]').is(':visible')) {
                    blankOption.selected();
                }
            }
        }

        function setLockedChroniquesMeres(locked) {
            for (const b in blocks) {
                const $selectChronique = $(`#selectChroniqueMere${blocks[b]}`);
                const $selectStation = $(`#selectStation${blocks[b]}`);
                const $delayInput = $(`input[type="text"][name="delaiPropagation${blocks[b]}"]`);
                toggleControl($selectChronique.add($selectStation).add($delayInput), !locked);
                if (locked) {
                    const stationId = $selectChronique.children('option:selected').attr('station-id');
                    if ($.isNumeric(stationId)) {
                        $selectStation.children(`option[value=${stationId}]`).selected();
                    }
                }
            }
        }

        //What to do when a ChroniqueMere is chosen
        function initBaremes(block, baremeData) {
            $(`.listeBaremes${block}`).empty();
            if($(`#selectChroniqueMere${block}`).val() === '-1'){
                if(block === 'Principale'){
                    $('.baremePrincipale').add($secondaire)
                        .add($btnDiv)
                        .add($divsDelaiPropagation)
                        .hide();
                    $('.listeBaremesSecondaire').empty();
                    $stationMereSecondaire.find('option[value="-1"]').selected();
                    filterChroniquesMeresByStation('Secondaire');
                }else{
                    $('.baremeSecondaire')
                      .add($divsDelaiPropagation)
                      .hide();
                }
            }
            //A ChroniqueMere is selected
            else {
                if (baremeData && baremeData.length) {
                    let dataLine;
                    for (const i in baremeData) {
                        dataLine = baremeData[i];
                        createLineBareme(block, null, dataLine.id, dataLine.begin, dataLine.end || null);
                    }
                } else {
                    createLineBareme(block);
                }

                $('.baremePrincipale').add($btnDiv)
                    .show();
                if (isFirstEdition) {
                    $secondaire.show();
                }

                if ($chroniqueMereSecondaire.val() === '-1') {
                    $('.baremeSecondaire').add($divsDelaiPropagation)
                        .hide();
                }
                //A ChroniqueMereSecondaire is selected
                else {
                    $('.baremeSecondaire').add($divsDelaiPropagation)
                        .show();
                }
            }

            $.filterBaremesByUnit();
            updateValueLimitVisibilities();
        }

        function updateValueLimitVisibilities() {
            blocks.forEach((block) => {
                const allowValueLimits = !!$(`#selectChroniqueMere${block} option:selected`).attr('allow-limits');
                const $limitPolicy = $(`#div-value-limits-${block}`);
                if (allowValueLimits) {
                    $limitPolicy.show();
                    const valueLimitPolicy = $limitPolicy.find(`select[name=valueLimits${block}]`).val();
                    const usePlaceholder = valueLimitPolicy === 'chronique.lq_ld.options.placeholder';
                    const $placeholder = $(`#div-limit-placeholder-${block}`);
                    if (usePlaceholder) {
                        $placeholder.show();
                    } else {
                        $placeholder.hide();
                    }
                } else {
                    $limitPolicy.hide();
                }
            });
        }

        blocks.forEach((block) => {
            $(`select[name=valueLimits${block}]`).on('change', updateValueLimitVisibilities);
        });

        function setUseCoefficient() {
            ($chroniqueMerePrincipale.val() === '-1' || $chroniqueMereSecondaire.val() === '-1') ?
                $coefficientDiv.hide() : $coefficientDiv.show();
            setTimeout($.filterBaremesByUnit, 0);
        }

        $.filterBaremesByUnit = function () {
            let block;
            for (const i in blocks) {
                block = blocks[i];

                // filter available baremes: their input unit must be the same as that of their parent chronicle
                const $select = (block === 'Principale') ? $chroniqueMerePrincipale : $chroniqueMereSecondaire;
                let $selectedOption = $select.find(`option[value='${$select.val()}']`);
                const chroniqueUnitId = $selectedOption.attr('unit-id');

                // If there is only one parent chronicle, the bareme output unit must also match that of the child chronicle
                // Else, we must ensure that the output units for the different baremes of a given parent chronicle are the same
                let optProperties = (chroniqueUnitId) ? `[input-unit-id='${chroniqueUnitId}']` : '';
                if ($chroniqueMereSecondaire.val() === '-1') {
                    $selectedOption = $chroniqueCalculee.find(`option[value='${$chroniqueCalculee.val()}']`);
                    optProperties += `[output-unit-id='${$selectedOption.attr('unit-id')}']`;
                } else {
                    // Strategy: (i) find 1st "bareme" select for the parent chronicle, (ii) retrieve its selected option,
                    // (iii) get the output unit for the selected "bareme", (iv) if that unit exists, then use it to filter the "baremes"
                    const $baremeSelects = $(`.listeBaremes${block} select`);
                    if ($baremeSelects.length > 1) {
                        $selectedOption = $($(`.listeBaremes${block} option[value='${$($baremeSelects.get(0)).val()}']`).get(0));
                        const outputUnit = $selectedOption.attr('output-unit-id');
                        if (outputUnit) {
                            optProperties += `[output-unit-id='${outputUnit}']`;
                        }
                    }
                }

                const opt = `.listeBaremes${block} option`;
                $(`${opt}[input-unit-id]`).hide();
                $(opt + optProperties).show();

                if ($chroniqueMereSecondaire.val() === '-1') {
                    break;
                }
            }
        };

        //Links to the right events
        $chroniqueCalculee.on('change', () => {
            getChroniqueCalculee();
            $('#errors-transformation').hide();
        });
        $chroniqueMerePrincipale.on('change', () => {
            initBaremes('Principale');
            setUseCoefficient();
        });
        $chroniqueMereSecondaire.on('change', () => {
            initBaremes('Secondaire');
            setUseCoefficient();
        });
        for (const i in blocks) {
            $(`.listeBaremes${blocks[i]} select`).on('change', $.filterBaremesByUnit);
        }
        $stationMerePrincipale.on('change', () => {
            filterChroniquesMeresByStation('Principale');
            initBaremes('Principale');
            setUseCoefficient();
        });
        $stationMereSecondaire.on('change', () => {
            filterChroniquesMeresByStation('Secondaire');
            initBaremes('Secondaire');
            setUseCoefficient();
        });

        function createLineBareme(block, baremeLine, idBareme, dateBegin, dateEnd) {
            const $line = $($('#templateLineBareme').html());

            if (idBareme) {
                $line.find(`option[value='${idBareme}']`).selected();
            } else {
                $line.find("option[value='-1']").selected();
            }

            const $beginDate = $line.find('.beginDate');
            const $endDate = $line.find('.endDate');
            const $addBareme = $line.find('.addBareme');

            $beginDate.dateTimePicker({
                initialDateTime: dateBegin || undefined,
                setButtons: false,
                resetButton: false
            });

            $endDate.dateTimePicker({
                initialDateTime: dateEnd || undefined,
                setButtons: false,
                resetButton: true
            });

            $beginDate.dateTimePicker('observeStatus')
                .subscribe((status) => {
                    const endStatus = $endDate.dateTimePicker('getStatus').value;
                    toggleControl($addBareme, status.value.isValid() && endStatus.isValid());
                });

            $endDate.dateTimePicker('observeStatus')
                .subscribe((status) => {
                    const beginStatus = $beginDate.dateTimePicker('getStatus').value;
                    toggleControl($addBareme, status.value.isValid() && beginStatus.isValid());
                });

            $endDate.dateTimePicker('observeStatus')
                .subscribe(() => {
                    updateBeginDates(block);
                });

            $line.on('change', 'select', $.filterBaremesByUnit);

            $line.on('click', '.addBareme', (event) => {
                const status = $endDate.dateTimePicker('getStatus');
                if (!status.isValid) {
                    return;
                }
                createLineBareme(block, $line, null, status.value.clone());
                event.target.blur();
                event.target.parentElement.blur();
            });

            $line.on('click', '.removeBareme', () => {
                const nLines = $line.siblings().length;
                $line.off();
                $line.remove();
                if (nLines === 0) {
                    createLineBareme(block);
                }
                if (nLines === 1) {
                    $.filterBaremesByUnit();
                }
                setRemoveLastDateButton(block);
                updateBeginDates(block);
            });

            // Patch: force 1st option of bareme select to be selected when no bareme ID is provided
            if (!idBareme) {
                $line.find('.bareme option').first()
                    .selected();
            }

            if (baremeLine) {
                baremeLine.after($line);
            } else {
                $(`.listeBaremes${block}`).append($line);
            }

            $line.show();
            $.filterBaremesByUnit();

            setRemoveLastDateButton(block);
            updateBeginDates(block);
        }

        function setRemoveLastDateButton(block) {
            const $baremeBlock = $(`.listeBaremes${block}`);
            const $endDates = $baremeBlock.find('.endDate');
            const lastI = $endDates.length - 1;
            $endDates.each((i, picker) => {
                $(picker).attr('required', i < lastI ? true : null);
            });
        }

        function updateBeginDates(block) {
            const $baremeBlock = $(`.listeBaremes${block}`);
            const $baremes = $baremeBlock.find('div.bareme');
            let $previousEndDate = null;
            $baremes.each((i, bareme) => {
                const $beginDate = $(bareme).find('input.beginDate');
                const $endDate = $(bareme).find('input.endDate');
                if ($previousEndDate){
                    toggleControl($beginDate, false);
                    if ($previousEndDate.dateTimePicker('getStatus').isValid){
                        $beginDate.dateTimePicker('setDateTime', $previousEndDate.dateTimePicker('getStatus').value.clone());
                    } else {
                        $beginDate.dateTimePicker('setDateTime', '');
                    }
                } else {
                    toggleControl($beginDate, true);
                }
                $previousEndDate=$endDate;
            });
        }

        $formTransformation.on('submit', () => {
            for (const i in blocks) {
                const  block = blocks[i];
                const values = [];
                const baremeLines = $(`.listeBaremes${block} > .bareme`);
                baremeLines.each(function () {
                    const line = $(this);
                    const baremeId = line.find('select[name="bareme"]').val();
                    const beginDate = line.find('.beginDate').dateTimePicker('getDateTime');
                    const endDate = line.find('.endDate').dateTimePicker('getDateTime');
                    const beginDateStr = beginDate.isValid() ? beginDate.toISOString() : '';
                    const endDateStr = endDate.isValid() ? endDate.toISOString() : '';

                    values.push(`${baremeId}_${beginDateStr}_${endDateStr}`);
                });

                $(`#jeuBareme${block}`).val(values.join('|'));
            }
        });

        $('#bareme-import').on('show.bs.modal', function () {
            const $baremeModalClone = $('#bareme-modal-step1-template').clone(true)
                .attr('id', 'bareme-modal-step1');
            $baremeModalClone.children('#format-description-template').attr('id', 'format-description');
            $(this).empty()
                .append($baremeModalClone.show());

            setTimeout($.baremeImport.step1, 0);
        });

        $btnDiv.find('input[type=reset]').on('click', () => {
            const val = $chroniqueCalculee.val();
            setTimeout(() => {
                $chroniqueCalculee.val(val);
                getChroniqueCalculee();
            }, 0);
        });

        $formTransformation.ajaxForm({
            dataType: 'json',
            global: false, // prevent ajaxError from firing on cancel (on xhr.abort() just beloow)
            beforeSend (xhr) {
                $transformationErrors.hide();
                $transformationSubmitted.hide();
                toggleControl($formButton, false);
                toggleControl($chroniqueCalculee, false);
                if (isFirstEdition && !confirm($('#div-confirm-computation').text())) {
                    xhr.abort();
                    toggleControl($formButton, true);
                    toggleControl($chroniqueCalculee, true);
                }
            },
            success (data) {
                toggleControl($chroniqueCalculee, true);
                if ('errors' in data) {
                    $transformationErrors.find('#errors-transformation-details').html(data.errors);
                    $transformationErrors.show();
                }
                if ('submitted' in data) {
                    // First of all, given that the transformation parameters were successfully saved,
                    // copy the IDs of parent time series into hidden inputs and block the parent TS selects
                    // Copy propagation delays as well
                    isFirstEdition = false;
                    for (const b in blocks) {
                        const block = blocks[b];
                        const $selectParent = $(`#selectChroniqueMere${block}`);
                        const $hiddenParent = $(`#hiddenChroniqueMere${block}`);
                        const $visibleDelay = $(`#delaiPropagation${block}`);
                        const $hiddenDelay = $(`#hiddenDelaiPropagation${block}`);
                        $hiddenParent.val($selectParent.val());
                        $hiddenDelay.val($visibleDelay.val());
                        toggleControl($hiddenParent.add($hiddenDelay), !isFirstEdition);
                    }

                    setLockedChroniquesMeres(!isFirstEdition);

                    const link = pathToViewChroniqueCalculee
                        .replace('_codeStation_', data.submitted.stationCode)
                        .replace('_codeChronique_', data.submitted.chroniqueCode);

                    $lineSubmitted.html($lineSubmittedHtml
                        .replace('_chroniqueName_', data.submitted.chroniqueName)
                        .replace('_jobId_', data.submitted.jobId)
                        .replace('_linkToJobPage_', data.submitted.jobPage)
                        .replace('_linkToChronique_', link));
                    $transformationSubmitted.show();
                }
                toggleControl($formButton, true);
            }
        });

        $closeSubmitted.on('click', () => {
            $transformationSubmitted.hide();
        });

        $('#coefficient-help').add('.help-delay')
            .tooltip();
        setTimeout(getChroniqueCalculee, 0);

    });

    function toggleControl(button, enable) {
        $(button)
            .toggleClass('disabled', !enable)
            .attr('disabled', !enable);
    }

});
