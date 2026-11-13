# Form rate limiter for TYPO3

Limits how often a visitor can send a [TYPO3 form](https://docs.typo3.org/c/typo3/cms-form/main/en-us/), and blocks or allows IP addresses.

Works with TYPO3 12.4, 13.4 and 14.3 on PHP 8.2 to 8.5.

## Installation

```bash
composer require plan2net/form-rate-limiter
```

The limit applies to every form. You don't need to change your forms.

## How it works

- Only a finished form counts. Moving between steps and fixing field errors does not.
- Over the limit, the form is not sent and the visitor gets an error page (HTTP 429) that says how long to wait.
- Counts are kept per IP address, for each form or for all forms together.

## Settings

**Admin Tools > Settings > Extension Configuration > form_rate_limiter**

| Setting | Default | |
|---|---|---|
| Enable rate limiting | on | Blocked IP addresses stay blocked when off. |
| Rate limiting mode | Per form | *Per form* or *Global* (one count for all forms). |
| Maximum submissions | 5 | Finished forms per IP address and time interval. |
| Time interval | 15 minutes | For example `30 seconds` or `1 hour`. |
| Allowed IP addresses | empty | Never limited. Comma-separated, `*` as wildcard (`192.168.1.*`). |
| Blocked IP addresses | empty | Cannot send any form (HTTP 403). Blocked wins over allowed. |
| Enable logging | off | Logs blocked and rejected submissions. |

A wrong value, such as a maximum of `0` or a misspelled interval, falls back to the default and logs a warning.

## Error pages

The site's error handler for 429 or 403 is used if configured, otherwise TYPO3's error page in English or German. AJAX requests get JSON with `error`, `formIdentifier` and `retryAfter` (seconds).

### Your own error page

Add a handler for 429 (too many submissions) and 403 (blocked address) to the site configuration, as you would for 404. The status code stays the same.

**A page of the site**, in its design and edited like any other page:

```yaml
# config/sites/<site>/config.yaml
errorHandling:
  -
    errorCode: 429
    errorHandler: Page
    errorContentSource: 't3://page?uid=123'
```

The page cannot show how long to wait, so write the interval into its text ("in one hour at the latest") or say "later", and give another way to get in touch, such as a phone number.

**A Fluid template** gets the message with the wait time as `{message}`:

```yaml
errorHandling:
  -
    errorCode: 429
    errorHandler: Fluid
    errorFluidTemplate: 'EXT:my_site/Resources/Private/Templates/Error/TooManyRequests.html'
```

**A PHP class** that implements `PageErrorHandlerInterface` (`errorHandler: PHP`, `errorPhpClassFQCN`) gets the same message as `$message`.

The visitor's input is lost on the error page, unless the browser keeps it when they go back.

## Things to know

- **Behind a proxy or load balancer**, set `$GLOBALS['TYPO3_CONF_VARS']['SYS']['reverseProxyIP']`, or all visitors share the proxy's count.
- **With several web servers**, give the `ratelimiter` cache a shared backend (database or Redis), or each server counts on its own.
- **With logging on**, the log contains IP addresses and browser names, which are personal data.

## License

GPL-2.0-or-later. Made by [plan2net](https://www.plan2.net/).
