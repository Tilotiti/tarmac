<?php

namespace App\Service\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Injects current HTTP request information into log records under extra.web.
 * Uses RequestStack for reliable detection even behind reverse proxies.
 */
class WebRequestLogProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return $record;
        }

        $record->extra['web'] = [
            'url' => $request->getUri(),
            'http_method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
            'referrer' => $request->headers->get('Referer'),
        ];

        return $record;
    }
}
