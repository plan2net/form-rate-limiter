<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Middleware;

use Plan2net\FormRateLimiter\Configuration\RateLimiterSettings;
use Plan2net\FormRateLimiter\Http\ClientAddress;
use Plan2net\FormRateLimiter\Http\RateLimitResponseFactory;
use Plan2net\FormRateLimiter\Security\IpAccessPolicy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class FormRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterSettings $settings,
        private IpAccessPolicy $ipAccessPolicy,
        private RateLimitResponseFactory $responseFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $formData = $this->getFormDataFromRequest($request);
        if (null === $formData) {
            return $handler->handle($request);
        }

        $clientAddress = ClientAddress::fromRequest($request);
        if (!$this->ipAccessPolicy->isBlocked($clientAddress)) {
            return $handler->handle($request);
        }

        $formIdentifier = (string) array_key_first($formData);

        if ($this->settings->loggingEnabled) {
            $this->logger->error('Form submission blocked - IP blacklisted', [
                'form' => $formIdentifier,
                'ip' => $clientAddress,
                'userAgent' => $request->getHeaderLine('User-Agent'),
            ]);
        }

        return $this->responseFactory->forbidden($request, $formIdentifier);
    }

    /**
     * @return array<mixed>|null
     */
    private function getFormDataFromRequest(ServerRequestInterface $request): ?array
    {
        if ('POST' !== $request->getMethod()) {
            return null;
        }

        $parsedBody = $request->getParsedBody();
        $formData = is_array($parsedBody) ? $parsedBody['tx_form_formframework'] ?? null : null;

        return is_array($formData) && [] !== $formData ? $formData : null;
    }
}
