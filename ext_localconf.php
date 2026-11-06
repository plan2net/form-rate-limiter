<?php

declare(strict_types=1);

use Plan2net\FormRateLimiter\Form\AddRateLimitFinisher;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterInitializeCurrentPage'][1762437600] = AddRateLimitFinisher::class;
