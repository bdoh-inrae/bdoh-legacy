<?php
// SPDX-FileCopyrightText: The BDOH authors
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Irstea\BdohDataBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Les données des graphiques, sans compte, rendent jusqu'à 50 000 valeurs par requête, sur n'importe quelle période
 * (DataProvidingController, le visualiseur) : la voie la plus simple pour aspirer toutes les chroniques. Une limite
 * par adresse IP (graph_data_ip, GRAPH_DATA_PER_HOUR) qu'un humain ne rencontre pas, au-delà de laquelle la réponse
 * est 429, au format JSON que le visualiseur lit (JsonErrorListener). dev/plans/2026-11-26_ai-robots.md.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class GraphDataLimiter
{
    private const ROUTES = ['bdoh_data_chronique_measures', 'bdoh_data_chronique_overview', 'bdoh_consult_loadComplexViewerData'];

    public function __construct(private readonly RateLimiterFactoryInterface $graphDataIpLimiter)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !\in_array($request->attributes->get('_route'), self::ROUTES, true)) {
            return;
        }
        $limit = $this->graphDataIpLimiter->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
