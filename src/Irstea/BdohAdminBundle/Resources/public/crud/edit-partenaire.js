// Le formulaire d'un partenaire : les types de financement ne se montrent qu'aux partenaires qui ne
// sont pas financeurs. Sur le jQuery de Sonata. Repris de Resources/assets/entries/crud/edit-partenaire.js
// de l'ancien BDOH, sans ses traces de mise au point (console.log).
(function ($) {
    $(() => {

        const paramEl = $('[data-is-funding]');
        const isFunding = paramEl.data('is-funding');

        const typeFundingsSelector = $('select[id$="_typeFundings"]');

        const selectIsFunding = $('select[id$="_estFinanceur"] input[type="radio"]');

        function getInputIsFundingId() {
            return selectIsFunding.val();
        }

        function updateDirectionSelector() {
            const isFinanceur = isFunding[getInputIsFundingId()] === true;
            typeFundingsSelector
                .attr({disabled: isFinanceur})
                .closest('.form-group')
                .toggle(!isFinanceur);
        }

        updateDirectionSelector();

    });
})(jQuery);
