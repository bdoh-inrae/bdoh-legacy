define([
    'jquery',
    'translator',
    './style.css'
], ($, Translator) => {

    const $formConversion = $('#formConversionId');
    const $chroniqueConvertie = $('#chroniqueConvertieId');
    const $chroniqueChanged = $('#chroniqueChangedId');
    const $chroniqueMere = $('#chroniqueMereId');
    const $paramConversion = $('#paramConversionId');
    const $displayParamConversion = $('#displayParamConversionId');
    const $errorsConversionClose = $('#errorsConversionCloseId');
    const $errorsConversionAlert = $('#errorsConversionAlertId');
    const $successConversionClose = $('#successConversionCloseId');
    const $successConversionAlert = $('#successConversionAlertId');
    const $btnDiv = $('#btnDivId');


    // gestion du selecteur des chroniques converties
    function checkChroniqueConvertie(){
        const val = $chroniqueConvertie.val() ? parseInt($chroniqueConvertie.val().replace(/[^0-9]/gi, ''), 10) : null;
        return !(isNaN(val) || val <= 0)
    }
    $chroniqueConvertie.on('change', () => {
        $chroniqueChanged.val('chroniqueChanged');
        $formConversion.submit();
    });
    // force l'initialisation du selecteur en cas de navigation (back)
    function pageReset() {
        if (typeof $chroniqueConvertie.data('id') !== 'undefined') {
            $chroniqueConvertie.val($chroniqueConvertie.data('id'));
        } else {
            $chroniqueConvertie.val('');
        }
        $chroniqueChanged.val('');
    }
    $(window).on('pageshow', pageReset);


    // gestion de la chronique mère
    function checkChroniqueMere(){
        // pas de chronique mère
        if($chroniqueMere.length === 0 || typeof $chroniqueMere.data('noChroniqueMere') !== 'undefined'){
            return false;
        }
        // chronique mère déjà définie
        if(typeof $chroniqueMere.data('id') !== 'undefined'){
            return true;
        }
        // selecteur de chronique mère
        const val = $chroniqueMere.val() ? parseInt($chroniqueMere.val().replace(/[^0-9]/gi, ''), 10) : null;
        if(isNaN(val) || val <= 0) {
            $chroniqueMere.parent()
            .addClass('has-error');
            return false;
        }
        $chroniqueMere.parent()
        .removeClass('has-error');
        return true;
    }
    $chroniqueMere.on('change', checkChroniqueMere);
    $chroniqueMere.on('focus', checkChroniqueMere);
    $chroniqueMere.on('blur', checkChroniqueMere);


    // gestion du paramètre de conversion
    function minutesFormater(duration){
        const times = {
            'jour': 1440,
            'heure': 60,
            'minute': 1,
        };
        let text = '';
        if(duration >= 60) {
            for (const time in times) {
                if (text !== '') {
                    text += ' ';
                }
                if (duration >= times[time]) {
                    const nb = Math.floor(duration / times[time]);
                    duration %= times[time];
                    text += `${nb} ${Translator.transChoice(time, nb, {}, 'messages')}`;
                }
            }
            return text;
        }
        return Translator.transChoice('minute', duration, {}, 'messages');
    }
    function displayParamConversion(val){
        if(val === '') {
            $displayParamConversion.text(Translator.trans('conversion.paramConversionError', {}, 'messages'));
            $displayParamConversion.addClass('paramConversionError');
        }else{
            $displayParamConversion.removeClass('paramConversionError');
            $displayParamConversion.text(minutesFormater(val));
        }
    }
    function checkParamConversion(){
        const val = parseInt($paramConversion.val(), 10);
        if(isNaN(val) || val <= 0) {
            displayParamConversion('');
            $paramConversion.parent()
            .addClass('has-error');
            return false;
        }
        displayParamConversion(val);
        $paramConversion.parent()
        .removeClass('has-error');
        return true;
    }
    $paramConversion.on('input', () => {
        const val = $paramConversion.val().replace(/[^0-9]/gi, '');
        $paramConversion.val(val);
        if(!isNaN(parseInt(val, 10))){
            $paramConversion.val(Math.min(val, 9999999));
        }
        checkParamConversion();
        stateOfSubmit();
    });
    $paramConversion.on('keydown', (e) => {
        const val = parseInt($paramConversion.val().replace(/[^0-9]/gi, ''), 10);
        if(e.which === 38){ // up arrow
            if(isNaN(val) || val <= 0){
                $paramConversion.val(1);
            }else{
                $paramConversion.val(Math.min(val + 1, 9999999));
            }
        }else if(e.which === 40) { // down arrow
            if(isNaN(val) || val <= 1){
                $paramConversion.val(1);
            }else{
                $paramConversion.val(val - 1);
            }
        }
        checkParamConversion();
        stateOfSubmit();
    });
    $paramConversion.on('change', checkParamConversion);
    $paramConversion.on('focus', checkParamConversion);
    $paramConversion.on('blur', checkParamConversion);


    // gestion du bouton de validation
    const $submitButton = $formConversion.find('[type = "submit"]');
    $submitButton.enable = function () {
        return $(this).removeAttr('disabled')
            .addClass('btn-success');
    };
    $submitButton.disable = function () {
        return $(this).attr('disabled', true)
            .removeClass('btn-success');
    };
    $submitButton.disable();
    function stateOfSubmit(){
        // force chaque test pour afficher toutes les erreurs
        const checkCC = checkChroniqueConvertie();
        const checkCM = checkChroniqueMere();
        const checkPC = checkParamConversion();
        if (checkCC && checkCM && checkPC) {
            $submitButton.enable();
        } else {
            $submitButton.disable();
        }
    }
    $chroniqueConvertie.on('change', stateOfSubmit);
    $chroniqueConvertie.on('focus', stateOfSubmit);
    $chroniqueConvertie.on('blur', stateOfSubmit);
    $chroniqueMere.on('change', stateOfSubmit);
    $chroniqueMere.on('focus', stateOfSubmit);
    $chroniqueMere.on('blur', stateOfSubmit);
    $paramConversion.on('change', stateOfSubmit);
    $paramConversion.on('focus', stateOfSubmit);
    $paramConversion.on('blur', stateOfSubmit);
    stateOfSubmit();


    // gestion du reset du formulaire
    $formConversion.on('reset', () => {
        $displayParamConversion.text('');
        $paramConversion.parent()
        .removeClass('has-error');
        $chroniqueMere.parent()
        .removeClass('has-error');
        setTimeout(stateOfSubmit, 1);
    });


    // gestion du submit du formulaire
    $formConversion.on('submit', () => {
        // ce n'est pas un changement de chronique
        if($chroniqueChanged.val() !== 'chroniqueChanged'){
            // le form est validé
            if($submitButton.hasClass('btn-success')){
                // la chronique mère déjà définie
                if(typeof $chroniqueMere.data('id') !== 'undefined'){
                    return true;
                }
                // la chronique mère n'est pas déjà définie on demande confirmation pour la figer
                return confirm(Translator.trans('conversion.confirmConversion', {}, 'messages'));
            }
            return false;
        }
        return true;
    });


    // affichage des tooltip
    // (afficher la station, afficher la chronique nmère et aides sur le paramètre de conversion)
    $('a.viewStation, a.viewChroniqueMere, #paramConversionId').tooltip({trigger: 'hover'});
    $('sup#paramConversionHelpId').tooltip({html:true});

    // affichage du popover pour la liste des chroniques filles
    $('#childrenHelpId').popover({trigger: 'hover', 'html': true});


    // gestion des boutons de fermeture sur les boites de message (erreur et succès)
    $errorsConversionClose.on('click', () => {
        $errorsConversionAlert.hide();
    });
    $successConversionClose.on('click', () => {
        $successConversionAlert.hide();
    });


    // affichage des boutons (après la maj du stateOfSubmit)
    $btnDiv.show();


    // focus sur le selecteur des chroniques converties si pas de message
    if($errorsConversionAlert.length === 0 && $successConversionAlert.length === 0) {
        $chroniqueConvertie.focus();
    }

});
