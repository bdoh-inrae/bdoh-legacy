// Les familles de paramètres rangées par niveaux, dans les listes de select2 (familles et types de
// paramètres) : chaque résultat prend la classe de son option, « hierarchy-level-N ». Select2 3.5, celui
// de Sonata 3, le faisait de lui-même ; select2 4, celui de Sonata 4, par ce réglage, posé avant que
// Sonata ne crée les listes. Repris de Resources/assets/entries/crud/familles-types.js de l'ancien BDOH.
(function ($) {
    $.fn.select2.defaults.set('templateResult', function (data, container) {
        if (data.element) {
            $(container).addClass($(data.element).attr('class'));
        }
        return data.text;
    });
})(jQuery);
