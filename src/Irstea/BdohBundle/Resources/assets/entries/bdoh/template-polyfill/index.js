/*
 * © 2017 IRSTEA
 * Guillaume Perréal <guillaume.perreal@irstea.fr>
 * Tous droits réservés.
 */
require('./style.css');

document.addEventListener('ready', () => {
    if ('content' in document.createElement('template')) {
        return;
    }
    const templates = document.getElementsByTagName('template');
    for (const template of templates) {
        const fragment = document.createDocumentFragment();
        for (const node of template.childNodes) {
            fragment.appendChild(node);
        }
        template.content = fragment;
    }
});
