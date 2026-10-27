/**
 * Step 2 : validation of import
 */
define([
    'jquery',
    './import'
], ($) => {
    'use strict';

    $.baremeImport.step2 = function () {
        let baremeImport   = $.baremeImport.init(),
            data           = baremeImport.data,
            form           = baremeImport.form,
            cancel         = baremeImport.cancel(),
            stateOfSubmit  = baremeImport.stateOfSubmit(() => {
                return true;
            });

        // Send 'importType' to the server
        form.ajaxForm({
            dataType : 'json',
            success (data) {
                baremeImport.nextStep(data, () => {
                    insertNewBareme(data.newBaremeData);
                    $.filterBaremesByUnit();
                });
            }
        });
    };

    const insertNewBareme = function(baremeData){
        const $option = `${'<option'
            + " value='"}${baremeData.id}'`
            + ` input-unit-id='${baremeData.uniteEntreeId}'`
            + ` output-unit-id='${baremeData.uniteSortieId}'>${
             baremeData.nom} [${baremeData.dateCreation}]`;

        const $select = $('select[name="bareme"]');
        $select.find('option:first-of-type').after($option);

        // ajout du nouveau barème dans le template
        const optionDom = document.createElement('option');
        optionDom.setAttribute('value', baremeData.id);
        optionDom.setAttribute('input-unit-id', baremeData.uniteEntreeId);
        optionDom.setAttribute('output-unit-id', baremeData.uniteSortieId);
        optionDom.textContent = `${baremeData.nom}  [${baremeData.dateCreation}]`;
        const selectTemplate = document.querySelector('#templateLineBareme')
            .content.querySelector('select[name="bareme"]');
        selectTemplate.insertBefore(optionDom, selectTemplate.querySelector('option:nth-of-type(2)'));
    };

});
