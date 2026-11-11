// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// Les longues descriptions repliées (.read-more, homes.css) : sous celles qui dépassent, un bouton les déplie ou les
// replie. Les textes du bouton viennent du conteneur (data-read-more, data-read-less). Le texte entier est toujours
// dans la page : sans JavaScript, il reste replié mais se lit en entier à l'impression et par les lecteurs d'écran.
(function () {
    'use strict';

    document.querySelectorAll('[data-read-more]').forEach((container) => {
        container.querySelectorAll('.read-more').forEach((text) => {
            if (text.scrollHeight <= text.clientHeight + 4) {
                text.classList.add('read-more-short');
                return;
            }
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-link btn-xs read-more-toggle';
            button.textContent = container.dataset.readMore;
            button.setAttribute('aria-expanded', 'false');
            button.addEventListener('click', () => {
                const open = text.classList.toggle('read-more-open');
                button.textContent = open ? container.dataset.readLess : container.dataset.readMore;
                button.setAttribute('aria-expanded', String(open));
            });
            text.after(button);
        });
    });
})();
