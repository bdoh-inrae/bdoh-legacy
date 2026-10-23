/**
 * From a list (IN) of distinct elements, allows the user to fill a finite size list (OUT), by dragging and dropping.
 *    => each element of IN can appear only once in OUT ;
 *    => empty elements of OUT are filled by a default element .
 *
 * @param {String}  containerSelector  jquery selector of the container DOM element
 * @param {Object}  elements           An associative array of distinct elements
 * @param {Object}  defaultElement     Format : { name: 'a_name_for_default', value: 'My default element' }
 * @param {Integer} numOut             The size of the list to fill
 *
 * @return An associative array containing the DOM element of lists IN and OUT
 *
 * @throws {event : change.finiteList} When an element of list OUT is added or removed
 *
 * @requires jquery
 * @requires jquery-ui
 *
 * @example
 * var lists = $.finiteList(
 *      '#my_lists_parent',
 *      { elt_1: 'Element one', elt_2: 'Element two', elt_3: 'Element three' },
 *      { name: 'default', value: 'Default element' },
 *      2
 * );
 *
 * $('#my_lists_parent').append(lists.In).append(lists.Out);
 */
define([
    'jquery',
    'jquery-ui',
    // --
    'jquery-ui/ui/widget',
    'jquery-ui/ui/widgets/draggable',
    'jquery-ui/ui/widgets/droppable',
    'jquery-ui/ui/widgets/sortable',
], ($) => {
    'use strict';

    $.finiteList = function (containerSelector, elements, defaultElement, numOut) {

        // Default values
        defaultElement = defaultElement || { name: '', value: '' };
        numOut = numOut || 1;

        let $listIn  = $('<ul name="listIn"/>'),
            $listOut = $('<ol name="listOut"/>'),
            $delete  = $('<span class="glyphicon glyphicon-remove"/>').css('float', 'right')
                .css('cursor', 'default')
                .css('margin-right', '15px'),
            e;

        /**
         * If the $delete tool of a $listOut element is clicked :
         *  => Enables this element in $listIn ;
         *  => And, in $listOut, replaces it by the 'defaultElement' .
         */
        $delete.on('click', function () {
            const parent = $(this).parent();
            $listIn.children(`[name = "${parent.attr('name')}"]`).draggable('enable');
            parent.css('cursor', '').attr('name', defaultElement.name)
                .text(defaultElement.value)
                .trigger('change.finiteList');
        });

        // Adds elements to the $listIn
        for (e in elements) {
            if (elements.hasOwnProperty(e)) {
                $('<li/>').css('cursor', 'move')
                    .attr('name', e)
                    .text(elements[e])
                    .appendTo($listIn);
            }
        }

        // For the $listOut, prepares 'numOut' <li> with 'defaultElement'
        for (e = 0; e < numOut; e += 1) {
            $('<li/>').attr('name', defaultElement.name)
                .text(defaultElement.value)
                .appendTo($listOut);
        }

        // Defines $listIn.li as draggable
        $listIn.children().draggable({
            revert: 'invalid',
            cursor: 'move',
            helper (event) {
                return $('<div class="ui-widget-header"/>').text($(this).text());
            }
        });

        // Defines $listOut as sortable
        $listOut.sortable({
            items: 'li',
            placeholder: 'ui-state-highlight',
            cancel: `[name = "${defaultElement.name}"]`
        });

        // Defines $listOut.li as droppable
        $listOut.children().droppable({
            accept      : `${containerSelector} [name = "listIn"] > li`,
            activeClass : 'ui-state-highlight',
            hoverClass  : 'ui-state-active',

            drop (event, ui) {
                // Enables the old element in $listIn
                $listIn.children(`[name = "${$(this).attr('name')}"]`).draggable('enable');

                // Adds the new in $listOut
                $(this).css('cursor', 'move')
                       .attr('name', ui.draggable.attr('name'))
                       .text(ui.draggable.text())
                       .append($delete.clone(true));

                // and disables it in $listIn
                ui.draggable.draggable('disable');

                $(this).trigger('change.finiteList');
            }
        });

        return { In: $listIn, Out: $listOut };
    };

});
