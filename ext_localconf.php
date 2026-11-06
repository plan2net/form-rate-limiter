<?php

declare(strict_types=1);

use Plan2net\FormRateLimiter\Form\AddRateLimitFinisher;
use Plan2net\FormRateLimiter\Form\RejectBlockedAddresses;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterInitializeCurrentPage'][1762437599] = RejectBlockedAddresses::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterInitializeCurrentPage'][1762437600] = AddRateLimitFinisher::class;
