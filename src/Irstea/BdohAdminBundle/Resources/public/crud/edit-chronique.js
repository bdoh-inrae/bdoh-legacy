// Le formulaire d'une chronique : son code se propose d'après la station et le paramètre. Sur le
// jQuery de Sonata. Repris de Resources/assets/entries/crud/edit-chronique.js de l'ancien BDOH.
(function ($) {
    $(() => {
        const url = $('[data-code-generator-path]').data('code-generator-path');
        const $code = $('form [name$="[code]"]');
        const $station = $('form [name$="[station]"]');
        const $parametre = $('form [name$="[parametre]"]');

        function generateCode() {
            $code.val('');
            $.ajax({
                url,
                data: {station: $station.val(), parametre: $parametre.val()}
            }).done((data) => $code.val(data));
        }

        if ('' === $code.val()) {
            generateCode();
        }

        $station.add($parametre).on('change', generateCode);
    });
})(jQuery);
