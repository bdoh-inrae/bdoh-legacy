// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// L'étoile d'une chronique (BdohSecurityBundle, Favorite/_star.html.twig) : un clic la met en favori ou l'en retire,
// sans quitter la page (FavoriteController::toggleAction ; dev/plans/2026-11-12_favorites.md). L'étoile ne change
// qu'à la réponse, et toutes celles de la même chronique avec elle ; une réponse refusée (session finie, jeton
// échu) recharge la page, qui montre l'état réel.
const $ = require('jquery');

function show(url, on) {
    $('button[data-favorite]').filter((i, b) => b.dataset.favorite === url).each((i, button) => {
        button.dataset.favoriteOn = on ? '1' : '0';
        button.classList.toggle('on', on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        button.title = on ? button.dataset.titleOn : button.dataset.titleOff;
    });
}

$(document).on('click', 'button[data-favorite]', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const button = this;
    if (button.disabled) {
        return;
    }
    const url = button.dataset.favorite;
    const on = button.dataset.favoriteOn !== '1';
    const body = new URLSearchParams();
    body.append('_token', button.dataset.favoriteToken);
    body.append('on', on ? '1' : '0');
    button.disabled = true;
    fetch(url, {method: 'POST', body, credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
        .then((data) => {
            button.disabled = false;
            show(url, data.favorite === true);
        })
        .catch(() => window.location.reload());
});
