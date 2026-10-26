// Le choix des couleurs (Form/widgets.html.twig : couleur_widget), par jquery-minicolors 2.2.6 chargé
// à la demande, sur le jQuery de Sonata. Repris de Resources/assets/lib/colorpicker.js de l'ancien BDOH.
(function ($) {
    const vendor = document.currentScript.src.replace(/lib\/colorpicker\.js.*$/, 'vendor/minicolors/');

    const config = {
        letterCase: 'uppercase',
        theme: 'bootstrap'
    };

    $(() => {
        const inputs = $('input.colorpicker');
        if (!inputs.length) {
            return;
        }
        $('<link rel="stylesheet">').attr('href', vendor + 'jquery.minicolors.css').appendTo('head');
        $.ajax({url: vendor + 'jquery.minicolors.min.js', dataType: 'script', cache: true})
            .done(() => $(inputs).minicolors(config));
    });
})(jQuery);
