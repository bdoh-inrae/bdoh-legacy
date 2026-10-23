<?php declare(strict_types=1);
/*
 * Copyright (C) 2012-2019 IRSTEA
 * All rights reserved.
 */

namespace Irstea\BdohBundle\EventListener;

use Negotiation\Accept;
use Negotiation\Negotiator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class ContentTypeNegociator.
 */
class ContentTypeNegociator implements EventSubscriberInterface
{
    /**
     * @var Negotiator
     */
    private $negotiator;

    /**
     * @var string[]
     */
    private $priorities;

    /**
     * ContentTypeNegociator constructor.
     *
     * @param Negotiator $negotiator
     */
    public function __construct(?array $priorities = null, ?Negotiator $negotiator = null)
    {
        $this->negotiator = $negotiator ?? new Negotiator();
        $this->priorities = $priorities ?? ['text/html', 'application/json'];
    }

    /**
     * @param RequestEvent $ev
     */
    public function onRequest(RequestEvent $ev)
    {
        $request = $ev->getRequest();
        $accept = $request->headers->get('Accept');
        if (!$accept) {

            return;
        }

        /** @var Accept|null $match */
        $match = $this->negotiator->getBest($accept, $this->priorities);
        if (!$match) {
            $ev->setResponse(new Response(null, Response::HTTP_NOT_ACCEPTABLE));
            $ev->stopPropagation();

            return;
        }

        $format = $request->getFormat($match->getNormalizedValue());
        if ($format) {
            $request->setRequestFormat($format);
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onRequest'];
    }
}
