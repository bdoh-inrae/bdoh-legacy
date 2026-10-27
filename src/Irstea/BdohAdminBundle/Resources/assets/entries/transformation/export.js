define([
    'jquery'
], ($) => {

    $(() => {

        const pathToGetBaremesForExport = $('[data-baremes-export]').data('baremes-export');

        let $exportDataTable;
        const blocks = ['Principale', 'Secondaire'];
        for (const i in blocks) {
            const block = blocks[i];
            $(`#exportBareme${block}`).on('click', {block}, (ev) => {
                $.ajax({
                    url: pathToGetBaremesForExport,
                    success(data) {
                        $('#table-export-bareme-container')
                            .css('visibility', 'hidden')
                            .html(data);
                        checkBaremesForExport(ev.data.block);
                        $exportDataTable = $('#table-export-bareme').dataTable({
                            'searching': false,
                            'paging': false,
                            'info': false,
                            'ordering': true,
                            'processing': false,
                            'order': [[2, 'desc']],
                            'columnDefs': [{
                                'orderable': false,
                                'targets': 0,
                            },],
                            'destroy': true,
                            'scrollY': '240px',
                            'scrollCollapse': true,
                        });
                        $('#bareme-export').modal();
                    }
                });
            });
        }

        function checkBaremesForExport(block) {
            $("#table-export-bareme input[type='checkbox']").selected(false);
            $(`.listeBaremes${block} select`).each(function () {
                const $cb = $(`#check-bareme-${$(this).val()}`);
                if ($cb.length) {
                    setTimeout(() => {
                        $cb.selected();
                    }, 0);
                }
            });
        }

        $('#bareme-export').on('shown.bs.modal', () => {
            $exportDataTable.fnDraw();
            $('#table-export-bareme-container').css('visibility', '');
        });

        $('#form-bareme-export').submit(() => {
            const $checkedBaremes = $("#table-export-bareme input[type='checkbox']:checked");
            if (!$checkedBaremes.length) {
                $('#no-bareme-to-export').show();
                return false;
            }

            // Create & fill object to bear information on the selected baremes
            const baremesInfo = {};
            $checkedBaremes.each(function () {
                const baremeId = $(this).attr('name');
                const $baremeLine = getLineForBareme(baremeId);
                if ($baremeLine) {
                    const block = $baremeLine.parent().attr('block');
                    baremesInfo[baremeId] = {
                        chroniqueMere: $(`#selectChroniqueMere${block}`).val(),
                        dateBegin: $baremeLine.find('.beginDate').val(),
                        dateEnd: $baremeLine.find('.endDate').val()
                    }
                }
                else {
                    baremesInfo[baremeId] = null;
                }
            });

            // Fill a hidden input with the content of baremesInfo
            const inputLines = [];
            for (const baremeId in baremesInfo) {
                let inputLine = baremeId;
                const oneBaremeInfo = baremesInfo[baremeId];
                if (oneBaremeInfo) {
                    inputLine +=
                        `_${oneBaremeInfo.chroniqueMere
                            }_${oneBaremeInfo.dateBegin
                            }_${oneBaremeInfo.dateEnd}`
                }

                inputLines.push(inputLine);
            }
            $("[name='baremes-export-info']").val(inputLines.join('|'));

            // Copy the child chronicle name/code into a hidden input
            //chroniqueCalculee
            $("[name='child-chronicle-name']").val($(`#chroniqueCalculee option[value=${
                $('#chroniqueCalculee').val()}]`).text()
                .trim());

            // Remove "No chronicle to export" error message and close the modal window
            $('#no-bareme-to-export').hide();
            $('#bareme-export').modal('hide');

            // Finally, allow the form to be submitted
            return true;

        });

        function getLineForBareme(baremeId) {
            const $select = $('select.bareme').filter(function () {
                return $(this).val() == baremeId;
            });
            if ($select.length) {
                return $($select.get(0)).parent()
                    .parent();
            }
            return null;
        }

    });
});
