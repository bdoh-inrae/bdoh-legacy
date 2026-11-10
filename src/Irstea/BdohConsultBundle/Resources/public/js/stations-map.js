// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
// La carte des stations (templates/Core/stations-map.html.twig), sur le plan de l'IGN, par Leaflet (vendor/leaflet/,
// chargé avant). Script simple, sans compilation. Les textes des stations s'écrivent par textContent, jamais en HTML.
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

    document.querySelectorAll('.stations-map').forEach((element) => {
        const stations = JSON.parse(element.dataset.stations);
        const observatories = new Set(stations.map((station) => station.observatoire));
        const map = L.map(element, {scrollWheelZoom: false});
        L.tileLayer(PLAN_IGN, {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.ign.fr/">IGN</a>, Plan IGN',
        }).addTo(map);
        const markers = stations.map((station) => L.circleMarker([parseFloat(station.lat), parseFloat(station.lon)], {
            radius: 6, weight: 1, color: '#ffffff', fillColor: '#0050a0', fillOpacity: 0.9,
        }).bindPopup(popup(station, element.dataset.seeStation, observatories.size > 1)));
        L.featureGroup(markers).addTo(map);
        const framed = 'fitFrance' in element.dataset
            ? markers.filter((marker) => FRANCE.contains(marker.getLatLng())) : markers;
        if (framed.length) {
            map.fitBounds(L.featureGroup(framed).getBounds(), {padding: [20, 20], maxZoom: 13});
        } else {
            map.setView([46.6, 2.5], 5);  // la France métropolitaine
        }
    });
})();
