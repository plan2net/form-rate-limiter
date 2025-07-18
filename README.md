# TYPO3 Form Rate Limiter

[![TYPO3 13](https://img.shields.io/badge/TYPO3-13.4-orange.svg)](https://get.typo3.org/version/13)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12.4-orange.svg)](https://get.typo3.org/version/12)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://www.php.net/)

A basic rate limiting extension for TYPO3 forms using the Symfony RateLimiter component.

## 🚀 Features

- **🎯 Middleware-based**: Early interception for maximum efficiency
- **⚙️ Backend Configuration**: Fully configurable via TYPO3 Extension Settings
- **🔒 IP Management**: Whitelist and blacklist support with TYPO3's native IP utilities
- **🌐 AJAX Support**: Works with both traditional and AJAX form submissions
- **📋 Logging**: Optional logging for monitoring and debugging

## 📋 Requirements

- TYPO3 v12.4+ or v13.4+
- PHP 8.3+
- EXT:form
- Symfony RateLimiter component

## 🔧 Installation

Install the extension via Composer:

```bash
composer require plan2net/form-rate-limiter
```

## ⚙️ Configuration

### Global Settings

Navigate to **Admin Tools > Settings > Extension Configuration > form_rate_limiter**:

| Setting | Default | Description |
|---------|---------|-------------|
| Enable Rate Limiting | Yes | Global on/off switch |
| Rate Limiting Mode | Per Form | Apply limits per form or globally across all forms |
| Maximum Attempts | 5 | Max attempts per interval |
| Time Interval | 15 minutes | Time window |
| Whitelist IPs | - | IPs that bypass rate limiting |
| Blacklist IPs | - | IPs that are blocked |
| Enable Logging | No | Log rate limiting events |

## 🎯 Rate Limiting Modes

### Per Form (Default)
- **Behavior**: Each form has separate rate limit counters
- **Configuration**: Uses the same global settings for all forms
- **Example**: 
  - Contact form: 5 attempts per 15 minutes
  - Newsletter form: 5 attempts per 15 minutes
  - **Both use the same rate limit settings, but have independent counters**

### Global (All Forms)  
- **Behavior**: All forms share the same rate limit counter
- **Configuration**: Uses global settings applied across all forms
- **Example**: 5 attempts per 15 minutes across ALL forms combined
- **Use case**: Prevents attackers from switching between forms to bypass limits

## 🔧 Usage Examples

### Basic Setup
```yaml
# Extension Configuration
enabled: true
limitingMode: per_form
defaultLimit: 5
defaultInterval: "15 minutes"
```

### Global Rate Limiting (Anti-Spam)
```yaml
# Apply rate limits across ALL forms
enabled: true
limitingMode: global
defaultLimit: 3
defaultInterval: "1 hour"
```

### Advanced IP Management
```yaml
# Whitelist trusted IPs
whitelistIps: "127.0.0.1, 192.168.1.*, 10.0.0.100"

# Blacklist problematic IPs  
blacklistIps: "192.168.1.50, 10.0.1.*"
```

## 🛡️ Security Features

### IP Whitelisting
Trusted IP addresses that bypass all rate limiting:
- Exact IPs: `127.0.0.1`
- Wildcards: `192.168.1.*`
- Multiple: `127.0.0.1, 192.168.1.*, 10.0.0.50`

### IP Blacklisting
Completely block problematic IP addresses:
- Supports same pattern matching as whitelist
- Takes precedence over whitelisting
- Returns immediate 429 error

## 📊 Error Responses

### HTML Forms
User-friendly error page with:
- Uses configured ErrorHandler f. http status 429 if present.
- Else a error message is shown

### AJAX Forms
JSON response with:
```json
{
    "error": "Rate limit exceeded for form 'contact-form'. Try again in 60 seconds.",
    "formIdentifier": "contact-form",
    "retryAfter": 60
}
```

## 🔍 Monitoring

### Logging
Enable logging to monitor:
- Form submission attempts
- Rate limit violations  
- IP whitelist/blacklist matches

### Cache Management
```bash
# Clear all caches
vendor/bin/typo3 cache:flush
```

## 🐛 Troubleshooting

### Extension Not Working
1. ✅ Check extension is activated
2. ✅ Clear all caches
3. ✅ Verify configuration
4. ✅ Check error logs

### Rate Limiting Not Applied
1. ✅ Global setting enabled?
2. ✅ IP whitelisted?
3. ✅ Form uses TYPO3 form framework?
4. ✅ Middleware registered?

### Always Blocked
1. ✅ IP blacklisted?
2. ✅ Settings too restrictive?
3. ✅ Clear cache storage
4. ✅ Check for conflicts

## 🏗️ Architecture

```
Request → Middleware → IP Check → Rate Limit → Form Processing
   ↓           ↓           ↓           ↓           ↓
 POST      Extract     White/Black   Symfony    TYPO3 Form
Request   Form ID      list Check   RateLimiter  Framework
```

### Key Components

- **FormRateLimitMiddleware**: Intercepts form submissions
- **RateLimiterService**: Creates and manages rate limiters
- **ConfigurationService**: Handles global extension settings
- **Symfony RateLimiter**: Provides sliding window rate limiting algorithm
- **TYPO3 Caching Framework**: Stores rate limiting data

## 🤝 Contributing

Contributions welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Add tests for new features
4. Submit a pull request

## 📄 License

GPL-2.0

## 👥 Credits

Developed by **plan2net GmbH**

## 📞 Support

For support and questions:
- Create an issue on GitHub
- Check the documentation
- Review troubleshooting guide

---

**Made with ❤️ for the TYPO3 community**
