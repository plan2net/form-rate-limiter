<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Form;

use Plan2net\FormRateLimiter\Configuration\RateLimiterSettings;
use Plan2net\FormRateLimiter\Http\ClientAddress;
use Plan2net\FormRateLimiter\Http\RateLimitResponseFactory;
use Plan2net\FormRateLimiter\RateLimiting\SubmissionLimiter;
use Plan2net\FormRateLimiter\Security\IpAccessPolicy;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;

final class RateLimitFinisher extends AbstractFinisher
{
    public function __construct(
        private readonly RateLimiterSettings $settings,
        private readonly IpAccessPolicy $ipAccessPolicy,
        private readonly SubmissionLimiter $submissionLimiter,
        private readonly RateLimitResponseFactory $responseFactory,
        private readonly LoggerInterface $rejectionLogger,
    ) {
    }

    protected function executeInternal(): ?string
    {
        $formRuntime = $this->finisherContext->getFormRuntime();
        $request = $formRuntime->getRequest();
        $clientAddress = ClientAddress::fromRequest($request);
        if (!$this->settings->enabled || $this->ipAccessPolicy->isExempt($clientAddress)) {
            return null;
        }

        $formIdentifier = $formRuntime->getIdentifier();
        $rateLimit = $this->submissionLimiter->consume($formIdentifier, $clientAddress);
        if ($rateLimit->isAccepted()) {
            return null;
        }

        if ($this->settings->loggingEnabled) {
            $this->rejectionLogger->warning('Form submission rejected - rate limit exceeded', [
                'form' => $formIdentifier,
                'ip' => $clientAddress,
                'userAgent' => $request->getHeaderLine('User-Agent'),
            ]);
        }

        $retryAfter = max(0, $rateLimit->getRetryAfter()->getTimestamp() - time());
        $response = $this->responseFactory->tooManyRequests($request, $formIdentifier, $retryAfter);

        throw new PropagateResponseException($response, 1762437601);
    }
}
