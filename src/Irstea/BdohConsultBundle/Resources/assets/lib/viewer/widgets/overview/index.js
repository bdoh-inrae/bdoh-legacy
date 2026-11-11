// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// La bande de toute la chronique, sous son graphique (2026-11-11, dev/differences.md, « Graphique d'une chronique ») :
// le minimum et le maximum de ses valeurs valides par tranche de temps (route bdoh_data_chronique_overview), et une
// fenêtre, la période du graphique, qu'on glisse, dont on tire les bords, ou qu'on recentre d'un clic. Au clavier :
// flèches gauche et droite pour la déplacer, haut et bas pour l'élargir ou la resserrer. Ctrl (ou ⌘) et la molette sur
// le graphique zooment autour du curseur ; la molette seule fait défiler la page, comme partout. La période passe par
// le sélecteur de dates du graphique (setDateRange), dont la limite de 5 ans tient toujours.
define([
    'jquery',
    '@IrsteaBdohBundle/lib/moment',
    './style.css'
], ($, moment) => {
    'use strict';

    const DAY = 864e5;
    const MAX_SPAN = moment.duration(5, 'years').asMilliseconds();
    const FORMAT = 'YYYY-MM-DDTHH:mm:ss[Z]';
    // La grille du graphique dans son cadre (simple.js : gridPadding.left 70 ; jqPlot : 10 à droite)
    const GRID_LEFT = 70;
    const GRID_RIGHT = 10;

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text) {
            node.textContent = text;
        }
        return node;
    }

    const day = (t) => moment.utc(t).format('DD/MM/YYYY');

    return function setupOverview(container, viewer) {
        const box = container[0];
        if (!box) {
            return;
        }
        const head = el('div', 'graph-overview-head');
        const range = el('span', 'graph-overview-range');
        head.append(el('span', 'graph-overview-title', box.dataset.title), range, el('span', 'graph-overview-hint', box.dataset.hint));
        const area = el('div', 'graph-overview-area');
        const canvas = el('canvas');
        const shadeLeft = el('div', 'graph-overview-shade');
        const shadeRight = el('div', 'graph-overview-shade');
        const win = el('div', 'graph-overview-window');
        win.tabIndex = 0;
        win.setAttribute('role', 'slider');
        win.setAttribute('aria-label', box.dataset.title);
        const handleLeft = el('span', 'graph-overview-handle left');
        const handleRight = el('span', 'graph-overview-handle right');
        win.append(handleLeft, handleRight);
        area.append(canvas, shadeLeft, shadeRight, win);
        const axis = el('div', 'graph-overview-axis');
        const axisStart = el('span');
        const axisEnd = el('span');
        axis.append(axisStart, axisEnd);
        box.append(head, area, axis);

        const title = document.querySelector('h1');
        const color = (title && getComputedStyle(title).color) || '#275662';
        win.style.color = color;
        head.firstChild.style.color = color;
        const graph = viewer.find('.graph')[0];
        let begin = null;
        let end = null;
        let start = null;
        let stop = null;
        let timer = null;

        const width = () => area.clientWidth;
        const toX = (t) => (t - begin) / (end - begin) * width();
        const toT = (x) => begin + x / width() * (end - begin);
        const clamp = (anchor) => {
            const span = Math.min(Math.max(stop - start, DAY), MAX_SPAN, end - begin);
            if (anchor === 'stop') {
                start = stop - span;
            } else {
                stop = start + span;
            }
            if (start < begin) {
                start = begin;
                stop = start + span;
            }
            if (stop > end) {
                stop = end;
                start = stop - span;
            }
        };
        const place = () => {
            if (begin === null || start === null) {
                return;
            }
            const left = toX(start);
            const right = toX(stop);
            win.style.left = `${left}px`;
            win.style.width = `${Math.max(right - left, 6)}px`;
            shadeLeft.style.left = '0';
            shadeLeft.style.width = `${left}px`;
            shadeRight.style.left = `${right}px`;
            shadeRight.style.width = `${Math.max(width() - right, 0)}px`;
            range.textContent = `${day(start)} – ${day(stop)}`;
            win.setAttribute('aria-valuetext', range.textContent);
        };
        const apply = (delay) => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                viewer.simpleViewer('setDateRange', moment.utc(start).format(FORMAT), moment.utc(stop).format(FORMAT), true);
            }, delay);
        };

        // La période du graphique, d'où qu'elle vienne (dates saisies, zoom dans le graphique, cette bande)
        viewer.simpleViewer('observeGraphDateRange').subscribe((value) => {
            start = moment.utc(value.start).valueOf();
            stop = moment.utc(value.end).valueOf();
            place();
        });

        // Glisser la fenêtre, tirer un bord, ou cliquer ailleurs pour la recentrer
        let drag = null;
        area.addEventListener('pointerdown', (ev) => {
            if (begin === null || start === null) {
                return;
            }
            const x = ev.clientX - area.getBoundingClientRect().left;
            let mode = 'move';
            if (ev.target === handleLeft) {
                mode = 'left';
            } else if (ev.target === handleRight) {
                mode = 'right';
            } else if (ev.target !== win) {
                const span = stop - start;
                start = toT(x) - span / 2;
                stop = start + span;
                clamp();
                place();
            }
            drag = {mode, x, start, stop};
            area.setPointerCapture(ev.pointerId);
            ev.preventDefault();
        });
        area.addEventListener('pointermove', (ev) => {
            if (!drag) {
                return;
            }
            const dt = toT(ev.clientX - area.getBoundingClientRect().left) - toT(drag.x);
            if (drag.mode === 'move') {
                start = drag.start + dt;
                stop = drag.stop + dt;
                clamp();
            } else if (drag.mode === 'left') {
                start = Math.max(Math.min(drag.start + dt, stop - DAY), stop - MAX_SPAN, begin);
            } else {
                stop = Math.min(Math.max(drag.stop + dt, start + DAY), start + MAX_SPAN, end);
            }
            place();
        });
        const release = () => {
            if (drag) {
                drag = null;
                apply(0);
            }
        };
        area.addEventListener('pointerup', release);
        area.addEventListener('pointercancel', release);

        win.addEventListener('keydown', (ev) => {
            if (start === null) {
                return;
            }
            const span = stop - start;
            const center = (start + stop) / 2;
            const moves = {
                ArrowLeft: () => { start -= span / 5; stop -= span / 5; },
                ArrowRight: () => { start += span / 5; stop += span / 5; },
                ArrowUp: () => { start = center - span * 0.625; stop = center + span * 0.625; },
                ArrowDown: () => { start = center - span * 0.4; stop = center + span * 0.4; }
            };
            if (moves[ev.key]) {
                moves[ev.key]();
                clamp();
                place();
                apply(400);
                ev.preventDefault();
            }
        });

        // Ctrl (ou ⌘) et la molette sur le graphique : zoomer autour du curseur
        graph.addEventListener('wheel', (ev) => {
            if (!(ev.ctrlKey || ev.metaKey) || start === null || begin === null) {
                return;
            }
            ev.preventDefault();
            const rect = graph.getBoundingClientRect();
            const f = Math.min(Math.max((ev.clientX - rect.left - GRID_LEFT) / (rect.width - GRID_LEFT - GRID_RIGHT), 0), 1);
            const at = start + f * (stop - start);
            const k = ev.deltaY > 0 ? 1.25 : 0.8;
            start = at - (at - start) * k;
            stop = at + (stop - at) * k;
            clamp();
            place();
            apply(300);
        }, {passive: false});

        // Le dessin : une colonne par tranche, du minimum au maximum, et la ligne de leur milieu
        const draw = (data) => {
            const w = Math.round(width());
            const h = area.clientHeight;
            const ratio = window.devicePixelRatio || 1;
            canvas.width = w * ratio;
            canvas.height = h * ratio;
            const ctx = canvas.getContext('2d');
            ctx.scale(ratio, ratio);
            const values = data.buckets.reduce((acc, [, lo, hi]) => [Math.min(acc[0], lo), Math.max(acc[1], hi)], [Infinity, -Infinity]);
            const y = (v) => h - 5 - (v - values[0]) / ((values[1] - values[0]) || 1) * (h - 10);
            const x = (i) => (data.begin + i * data.step - begin) / (end - begin) * w;
            const cell = Math.max(data.step / (end - begin) * w, 1);
            ctx.fillStyle = color;
            ctx.globalAlpha = 0.25;
            data.buckets.forEach(([i, lo, hi]) => ctx.fillRect(x(i), y(hi), cell, Math.max(y(lo) - y(hi), 1)));
            ctx.globalAlpha = 0.9;
            ctx.strokeStyle = color;
            ctx.lineWidth = 1.2;
            ctx.beginPath();
            let previous = null;
            data.buckets.forEach(([i, lo, hi]) => {
                const px = x(i) + cell / 2;
                const py = y((lo + hi) / 2);
                if (previous === i - 1) {
                    ctx.lineTo(px, py);
                } else {
                    ctx.moveTo(px, py);
                }
                previous = i;
            });
            ctx.stroke();
        };

        $.getJSON(box.dataset.url).then((data) => {
            if (!data.buckets || !data.buckets.length) {
                return;
            }
            begin = data.begin;
            end = data.end;
            axisStart.textContent = day(begin);
            axisEnd.textContent = day(end);
            box.style.display = '';
            draw(data);
            place();
            $(window).on('resize', () => {
                draw(data);
                place();
            });
        });
    };
});
