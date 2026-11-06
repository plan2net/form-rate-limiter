# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- TYPO3 14.3 support, next to 12.4 and 13.4.
- English and German labels for the rejection pages and the extension settings.
- Unit and functional tests, PHPStan, php-cs-fixer and a CI matrix for PHP 8.2–8.5 and TYPO3 12.4–14.3.

### Fixed

- Only a completed submission counts. Going to the next or previous page of a multi-step form, and resubmitting after a validation error, no longer use up the budget, so a visitor could get blocked before ever finishing the form.
- The budget is kept per form the form framework actually renders. A crafted extra form key in the POST data can no longer move a submission into another counter.
- `Retry-After` and the "try again in" text give the seconds to wait. They gave the Unix timestamp of the retry time.
- Blocked IP addresses get 403 Forbidden instead of 429 Too Many Requests.
- An address on both lists is blocked, as the README always said. The allow list won.
- An invalid setting (a misspelled interval, a limit of 0, an unknown mode) falls back to its default and logs a warning. A misspelled interval made every form submission fail.
- The rejection page no longer shows the form identifier or a link to the TYPO3 exception documentation.
