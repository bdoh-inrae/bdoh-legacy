define([
    'jquery',
    'translator',
    './style.css'
], ($, Translator) => {

    const $formColors = $('#formColorsId');
    const $jeuQualite = $('#jeuQualiteId');
    const $jeuQualiteChanged = $('#jeuQualiteChangedId');


    // gestion du selecteur du jeu de qualités
    $jeuQualite.on('change', () => {
        $jeuQualiteChanged.val('jeuQualiteChanged');
        $formColors.submit();
    });
    // force l'initialisation du selecteur en cas de navigation (back)
    function pageReset() {
        if (typeof $jeuQualite.data('id') !== 'undefined') {
            $jeuQualite.val($jeuQualite.data('id'));
        } else {
            $jeuQualite.val('');
        }
        $jeuQualiteChanged.val('');
    }
    $(window).on('pageshow', pageReset);


    // ajout des colorpicker
    const colorpickerconfig = {
        letterCase:'uppercase',
        theme:'bootstrap'
    };
    const colorpickers = $('input.colorpicker');
    if (colorpickers.length) {
        require('@claviska/jquery-minicolors');
        require('@claviska/jquery-minicolors/jquery.minicolors.css');
        $(colorpickers).minicolors(colorpickerconfig);
    }

    // affichage du tooltip
    $('sup.strokeHelp').tooltip();


    // gestion du reset du formulaire
    function resetColorpickers(){
        for(const colorpicker of colorpickers) {
            $(colorpicker).minicolors('value', [$(colorpicker).val()]);
        }
    }
    $formColors.on('reset', () => {
        window.setTimeout(resetColorpickers, 1);
    });

});
