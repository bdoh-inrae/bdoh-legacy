/**
 * For each element of a <ul> or <ol> list :
 *    => allows to switch it to a default element (by a 'delete' tool) ;
 *    => allows to redo this switching (by a 'redo' tool) .
 *
 * @param {Object}  defaultElement  Format : { name: 'a_name_for_default', value: 'My default element' }
 *
 * @throws {event : change.switchDefaultList} When any element of a list is changed
 *
 * @requires jquery
 * @requires jquery-ui
 *
 * @example
 * $('#my_list').switchDefaultList({ name: 'default', value: 'Default element' });
 */
define([
    'jquery'
], ($) => {
    'use strict';

    $.fn.switchDefaultList = function (defaultElement) {
        $(this).each(function () {

            // DOM element must be a valid list (<ol> or <ul>)
            if (this.tagName !== 'OL' && this.tagName !== 'UL') { return; }

            // Tools
            const $delete = $(`<span title="${defaultElement.titleDelete}" class="glyphicon glyphicon-remove"/>`).css('color', 'red')
                .css('margin-left', '4px');
            const $redo   = $(`<span title="${defaultElement.titleRedo}" class= "glyphicon glyphicon-refresh"/>`).css('color', 'green')
                .css('margin-left', '4px');

            // Behaviors of tools
            $delete.on('click', function () {
                $(this).parent()
                    .attr('name', defaultElement.name)
                    .text(defaultElement.value)
                    .append($redo.clone(true))
                    .trigger('change.switchDefaultList');
            });

            $redo.on('click', function () {
                const parent = $(this).parent();
                parent.attr('name', parent.attr('originalName'))
                    .text(parent.attr('originalValue'))
                    .append($delete.clone(true))
                    .trigger('change.switchDefaultList');
            });

            // Adds 2 attributes and a 'delete' tool for each list elements
            $(this).children('li')
                .each(function () {
                    $(this).attr('originalName',  $(this).attr('name') || '')
                       .attr('originalValue', $(this).text())
                       .append($delete.clone(true));
            });

        });
        return $(this);
    };

});
