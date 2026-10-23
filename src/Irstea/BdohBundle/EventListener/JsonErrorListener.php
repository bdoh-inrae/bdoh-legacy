<?php

namespace Irstea\BdohBundle\EventListener;

use Negotiation\Negotiator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Répond à une erreur sur une requête au format JSON comme le faisait Symfony 2 (gabarit
 * error.json.twig de son TwigBundle) : {"error": {"code": 403, "message": "Forbidden"}}. Le
 * visualiseur lit ces réponses ; Symfony 7 rendrait une page HTML.
 *
 * Le format suit la règle de l'ancien : la requête interne qui rendait l'erreur repassait par
 * ContentTypeNegociator, si bien qu'un en-tête Accept décide (le HTML d'abord, comme lui) ; sans
 * en-tête, le format fixé par le contrôleur reste.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: -100)]
final class JsonErrorListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $format = $request->getRequestFormat();
        if ($accept = $request->headers->get('Accept')) {
            $match = (new Negotiator())->getBest($accept, ['text/html', 'application/json']);
            $format = $match ? $request->getFormat($match->getNormalizedValue()) : $format;
        }
        if ($format !== 'json') {
            return;
        }

        $exception = $event->getThrowable();
        $code = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        $event->setResponse(new JsonResponse(
            ['error' => ['code' => $code, 'message' => Response::$statusTexts[$code] ?? '']],
            $code,
            $headers
        ));
    }
}
