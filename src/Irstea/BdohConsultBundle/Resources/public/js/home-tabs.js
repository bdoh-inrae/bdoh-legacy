// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// Les onglets de l'accueil d'un observatoire (Présentation, Partenaires, Gestionnaires) : l'adresse porte l'onglet
// ouvert (#managers), pour que l'aide et l'ancienne page de contact (/contact, qui y renvoie) mènent aux
// gestionnaires, et qu'un onglet se partage. Bootstrap ouvre l'onglet au clic d'une pastille (data-toggle="pill") ; ce
// script simple, sans jQuery, clique celle de l'adresse au chargement, et écrit dans l'adresse celle qu'on ouvre. Le
// navigateur descendrait jusqu'à l'onglet ouvert, sous la carte, et perdrait le nom de l'observatoire : la vue revient
// sur la ligne du nom, d'où l'on voit les onglets et, sous la carte, le contenu.
(function () {
    'use strict';

    const pills = Array.from(document.querySelectorAll('#consult-observatoire [data-toggle="pill"]'));
    if (!pills.length) {
        return;
    }
    const first = pills[0].getAttribute('href');
    const wanted = pills.find((pill) => pill.getAttribute('href') === window.location.hash);
    if (wanted && wanted.getAttribute('href') !== first) {
        wanted.click();
        const headline = document.querySelector('.observatory-headline');
        window.addEventListener('load', () => headline && headline.scrollIntoView());
    }
    pills.forEach((pill) => pill.addEventListener('click', () => {
        const hash = pill.getAttribute('href');
        const url = window.location.pathname + window.location.search + (hash === first ? '' : hash);
        window.history.replaceState(null, '', url);
    }));
})();
