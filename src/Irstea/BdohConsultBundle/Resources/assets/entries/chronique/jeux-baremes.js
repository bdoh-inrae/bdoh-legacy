/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
define([
    'jquery',
    '@IrsteaBdohBundle/lib/datatables'
], ($) => {

    const dtJeuBaremes = function ($table) {
        return $table.dataTable({
            bDestroy: true,
            sScrollY: '500px',
            bSort: false,
            bProcessing: false,
            sDom: 't'
        }).rowGrouping();
    };

    const $jeuxBaremesTables = $('.table-jeux-baremes');
    const $jeuxBaremesDetails = [];
    $jeuxBaremesTables.each(function () {
        $jeuxBaremesDetails.push(dtJeuBaremes($(this)));
    });

    $('#detail-jeux-baremes')
        .one('show', () => {
            $jeuxBaremesTables.css('visibility', 'hidden');
        })
        .one('shown', () => {
            $($jeuxBaremesDetails).each(function () {
                this.fnDraw();
            });
            $jeuxBaremesTables.css('visibility', '');

        });
});

