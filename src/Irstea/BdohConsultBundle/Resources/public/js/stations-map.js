// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// La carte des stations (templates/Core/stations-map.html.twig), sur le plan de l'IGN, par Leaflet (vendor/leaflet/,
// chargé avant). Script simple, sans compilation. Les textes des stations s'écrivent par textContent, jamais en HTML.
//
// La page peut avoir des éléments qui servent de légende : une fiche d'observatoire (data-map-observatoire, le nom de
// l'observatoire) ou un site (data-map-site, son identifiant). Les survoler ou les atteindre au clavier allume leurs
// stations et grise les autres ; cliquer sur un bouton de site le garde allumé, pour les écrans tactiles. La carte
// ne zoome pas et rien ne se déplace : seule la couleur des points change.
(function () {
    'use strict';

    const PLAN_IGN = 'https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0'
        + '&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/png'
        + '&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}';

    function popup(station, seeStation, withObservatory) {
        const content = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = `${station.nom} (${station.code})`;
        content.appendChild(title);
        if (withObservatory) {
            content.appendChild(document.createElement('br'));
            content.appendChild(document.createTextNode(station.observatoire));
        }
        content.appendChild(document.createElement('br'));
        const link = document.createElement('a');
        link.href = station.url;
        link.textContent = seeStation;
        content.appendChild(link);
        return content;
    }

    // La France métropolitaine, à peu près : la vue de l'accueil du portail (data-fit-france)
    const FRANCE = L.latLngBounds([41, -5.5], [51.5, 10]);
    // Les points prennent la couleur du thème, celle du titre de la page
    const title = document.querySelector('h1');
    const ACCENT = (title && getComputedStyle(title).color) || '#0050a0';
    const BASE = {radius: 6, weight: 1, color: '#ffffff', fillColor: ACCENT, fillOpacity: 0.9};
    const LIT = {radius: 8, weight: 1.5, color: '#ffffff', fillColor: ACCENT, fillOpacity: 1};
    const DIMMED = {radius: 5, weight: 1, color: '#ffffff', fillColor: '#9aa3ad', fillOpacity: 0.35};

    // Ce qu'un élément de légende allume
    function selects(item) {
        if ('mapObservatoire' in item.dataset) {
            return (station) => station.observatoire === item.dataset.mapObservatoire;
        }
        const site = Number(item.dataset.mapSite);
        return (station) => station.sites.includes(site);
    }

    document.querySelectorAll('.stations-map').forEach((element) => {
        const stations = JSON.parse(element.dataset.stations);
        const observatories = new Set(stations.map((station) => station.observatoire));
        const map = L.map(element, {scrollWheelZoom: false});
        L.tileLayer(PLAN_IGN, {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.ign.fr/">IGN</a>, Plan IGN',
        }).addTo(map);
        const markers = stations.map((station) => {
            const marker = L.circleMarker([parseFloat(station.lat), parseFloat(station.lon)], BASE)
                .bindPopup(popup(station, element.dataset.seeStation, observatories.size > 1));
            marker.station = station;
            return marker;
        });
        L.featureGroup(markers).addTo(map);
        const framed = 'fitFrance' in element.dataset
            ? markers.filter((marker) => FRANCE.contains(marker.getLatLng())) : markers;
        if (framed.length) {
            map.fitBounds(L.featureGroup(framed).getBounds(), {padding: [20, 20], maxZoom: 13});
        } else {
            map.setView([46.6, 2.5], 5);  // la France métropolitaine
        }

        // La légende : survol et clavier allument, un clic sur un bouton garde allumé
        const items = Array.from(document.querySelectorAll('[data-map-observatoire], [data-map-site]'));
        let pinned = null;
        const light = (item) => {
            items.forEach((other) => other.classList.toggle('map-lit', other === item));
            if (!item) {
                markers.forEach((marker) => marker.setStyle(BASE));
                return;
            }
            const test = selects(item);
            markers.forEach((marker) => {
                const on = test(marker.station);
                marker.setStyle(on ? LIT : DIMMED);
                if (on) {
                    marker.bringToFront();
                }
            });
        };
        items.forEach((item) => {
            item.addEventListener('mouseenter', () => light(item));
            item.addEventListener('focusin', () => light(item));
            item.addEventListener('mouseleave', () => light(pinned));
            item.addEventListener('focusout', () => light(pinned));
            if (item.tagName === 'BUTTON') {
                item.addEventListener('click', () => {
                    pinned = pinned === item ? null : item;
                    items.filter((other) => other.tagName === 'BUTTON')
                        .forEach((other) => other.setAttribute('aria-pressed', String(other === pinned)));
                    light(pinned);
                });
            }
        });
    });
})();
