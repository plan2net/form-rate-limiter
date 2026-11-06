<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Form;

use Plan2net\FormRateLimiter\Configuration\RateLimiterSettings;
use Plan2net\FormRateLimiter\Http\ClientAddress;
use Plan2net\FormRateLimiter\Http\RateLimitResponseFactory;
use Plan2net\FormRateLimiter\Security\IpAccessPolicy;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Event\AfterCurrentPageIsResolvedEvent;

final readonly class RejectBlockedAddresses
{
    public function __construct(
        private RateLimiterSettings $settings,
        private IpAccessPolicy $ipAccessPolicy,
        private RateLimitResponseFactory $responseFactory,
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener('plan2net/form-rate-limiter/reject-blocked-addresses')]
    public function __invoke(AfterCurrentPageIsResolvedEvent $event): void
    {
        $this->rejectIfBlocked($event->formRuntime);
    }

    /**
     * TYPO3 v12 and v13.
     *
     * @param array<mixed> $requestArguments
     */
    public function afterInitializeCurrentPage(FormRuntime $formRuntime, ?Page $currentPage, ?Page $lastPage, array $requestArguments): ?Page
    {
        $this->rejectIfBlocked($formRuntime);

        return $currentPage;
    }

    private function rejectIfBlocked(FormRuntime $formRuntime): void
    {
        if (!$formRuntime->canProcessFormSubmission()) {
            return;
        }

        $request = $formRuntime->getRequest();
        $clientAddress = ClientAddress::fromRequest($request);
        if (!$this->ipAccessPolicy->isBlocked($clientAddress)) {
            return;
        }

        $formIdentifier = $formRuntime->getIdentifier();
        if ($this->settings->loggingEnabled) {
            $this->logger->error('Form submission blocked - IP blacklisted', [
                'form' => $formIdentifier,
                'ip' => $clientAddress,
                'userAgent' => $request->getHeaderLine('User-Agent'),
            ]);
        }

        throw new PropagateResponseException($this->responseFactory->forbidden($request, $formIdentifier), 1762437602);
    }
}
