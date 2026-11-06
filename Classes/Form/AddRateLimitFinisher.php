<?php

declare(strict_types=1);

namespace Plan2net\FormRateLimiter\Form;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Event\AfterCurrentPageIsResolvedEvent;

/**
 * Runs after FormRuntime::initialize() applied the variants, because a variant with
 * finishers replaces the whole finisher list.
 */
final class AddRateLimitFinisher
{
    #[AsEventListener('plan2net/form-rate-limiter/add-rate-limit-finisher')]
    public function __invoke(AfterCurrentPageIsResolvedEvent $event): void
    {
        $this->prependTo($event->formRuntime);
    }

    /**
     * TYPO3 v12 and v13.
     *
     * @param array<mixed> $requestArguments
     */
    public function afterInitializeCurrentPage(FormRuntime $formRuntime, ?Page $currentPage, ?Page $lastPage, array $requestArguments): ?Page
    {
        $this->prependTo($formRuntime);

        return $currentPage;
    }

    private function prependTo(FormRuntime $formRuntime): void
    {
        $form = $formRuntime->getFormDefinition();
        $finishers = $form->getFinishers();
        if (($finishers[0] ?? null) instanceof RateLimitFinisher) {
            return;
        }

        $rateLimitFinisher = GeneralUtility::makeInstance(RateLimitFinisher::class);
        $rateLimitFinisher->setFinisherIdentifier('RateLimit');

        $form->setOptions(['finishers' => []], true);
        $form->addFinisher($rateLimitFinisher);
        foreach ($finishers as $finisher) {
            $form->addFinisher($finisher);
        }
    }
}
