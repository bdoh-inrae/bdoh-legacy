// L'éditeur des textes en HTML (Form/widgets.html.twig : wysiwyg_widget), par Trumbowyg 2.8.1 chargé
// à la demande, sur le jQuery de Sonata. Repris de Resources/assets/lib/htmleditor.js de l'ancien BDOH.
(function ($) {
    const vendor = document.currentScript.src.replace(/lib\/htmleditor\.js.*$/, 'vendor/trumbowyg/');

    const script = (file) => $.ajax({url: vendor + file, dataType: 'script', cache: true});

    $(() => {
        const editors = $('textarea.htmleditor');
        if (!editors.length) {
            return;
        }

        $('<link rel="stylesheet">').attr('href', vendor + 'ui/trumbowyg.min.css').appendTo('head');
        script('trumbowyg.min.js').then(() => script('langs/fr.min.js')).done(() => {
            editors.trumbowyg({
                lang: $('html').attr('lang') || 'fr',
                autogrowOnEnter: true,
                svgPath: vendor + 'ui/icons.svg',
                btns: [
                    ['undo', 'redo'], // Only supported in Blink browsers
                    ['formatting'],
                    ['strong', 'em', 'del'],
                    ['superscript', 'subscript'],
                    ['link'],
                    ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
                    ['unorderedList', 'orderedList'],
                    ['horizontalRule'],
                    ['removeformat'],
                    ['fullscreen']
                ]
            });
        });
    });
})(jQuery);
