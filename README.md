# Genaker FreeAdmin Module

## ⚠️ CRITICAL SECURITY WARNING ⚠️

**This module is strictly for development and testing environments only!**

- 🚫 **NEVER use in production environments**
- 🚫 **NEVER use with real customer data**
- 🚫 **NEVER expose systems with this module to the internet**
- ✅ **Automatically disabled in production mode**
- ✅ **Only works in developer or default modes**

This module bypasses authentication entirely when enabled. Use only in isolated development environments.

---

## Overview
This module allows free admin login when authentication is disabled in `app/etc/env.php`.

## How It Works
The module uses a before plugin on `Magento\Backend\Model\Auth::login()` method to:
1. **Production Mode Check**: Automatically disabled in production mode - will never bypass authentication
2. **Configuration Check**: Checks if `backend/auth` is set to `false` in `app/etc/env.php`
3. **User Lookup**: If conditions are met:
   - First tries to find an active admin user by the provided email/username
   - If not found, falls back to the first available active admin user
   - Validates that the user account is active before allowing login
4. **Normal Flow**: If not enabled or in production mode, proceeds with normal authentication
5. **Logging**: All authentication bypass attempts are logged using Magento's PSR-3 logger

## Installation

### Method 1: Manual Installation
1. Copy the module to `app/code/Genaker/FreeAdmin/`
2. Enable the module:
   ```bash
   php bin/magento module:enable Genaker_FreeAdmin
   ```
3. Run setup:
   ```bash
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento cache:flush
   ```

### Method 2: Composer Installation
1. Add the module to your project's `composer.json`:
   ```json
   {
       "repositories": [
           {
               "type": "path",
               "url": "app/code/Genaker/FreeAdmin"
           }
       ]
   }
   ```
2. Install via Composer:
   ```bash
   composer require genaker/free-admin:1.0.0
   ```
3. Run setup:
   ```bash
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento cache:flush
   ```

## Usage

### To Enable Free Admin Access:
1. Set `backend/auth` to `false` in your deployment configuration (e.g., `app/etc/env.php`):
```php
'backend' => [
    'auth' => false
],
```

2. Clear caches:
```bash
php bin/magento cache:flush
```

3. Now any username/password combination will work for admin login

### To Disable Free Admin Access:
1. Set `backend/auth` to `true` or remove the configuration in your deployment configuration:
```php
'backend' => [
    'auth' => true
],
```

2. Clear caches:
```bash
php bin/magento cache:flush
```

## Security Warning
⚠️ **IMPORTANT**: This module is for development/testing purposes only.

### Security Features:
- ✅ **Production Mode Protection**: Automatically disabled when Magento is in production mode
- ✅ **Active User Validation**: Only allows login with active admin accounts
- ✅ **Secure Logging**: Uses Magento's PSR-3 logger without exposing sensitive information
- ✅ **Optimized Queries**: Improved database queries to prevent performance issues
- ⚠️ **Development Only**: Should only be used in isolated development environments

### What This Module Does:
- Bypasses standard authentication when `backend/auth` is set to `false`
- Requires at least one active admin user to exist in the system
- Only works in development or default modes (never in production)
- Logs all authentication bypass attempts

## Module Structure
```
Genaker/FreeAdmin/
├── Plugin/
│   └── Backend/
│       └── Model/
│           └── Auth/
│               └── SimpleLoginPlugin.php
├── etc/
│   ├── di.xml
│   └── module.xml
├── composer.json
├── registration.php
└── README.md
```

## Troubleshooting

### Module Not Working
1. Check if module is enabled: `php bin/magento module:status Genaker_FreeAdmin`
2. Verify plugin is loaded: `php bin/magento setup:di:compile`
3. Clear caches: `php bin/magento cache:flush`
4. Check logs in `var/log/system.log` for FreeAdmin messages
5. Verify you're not in production mode: `php bin/magento deploy:mode:show`
6. Verify `backend/auth` is set to `false` in `app/etc/env.php`
7. You may also need to disable Two Factor Auth modules

### Common Issues
- **Not working in production**: By design - module is automatically disabled in production mode
- **No admin users found**: Ensure at least one active admin user exists in the system
- **Still requires password**: Check that `backend/auth` is explicitly set to `false` (not just missing) 

## Customization
To modify the authentication bypass logic, edit the `beforeLogin` method in `Plugin/Backend/Model/Auth/SimpleLoginPlugin.php`.

### Code Quality Features:
- **PSR-3 Logging**: Uses Magento's standard logger interface
- **Type Hints**: Full PHP 7+ type hints for better IDE support and error detection
- **Optimized Queries**: Single database query for user lookup instead of multiple queries
- **Active User Validation**: Checks user status before allowing authentication bypass
- **Method Validation**: Checks for method existence before calling fallback methods
- **Exception Handling**: Proper exception handling with detailed logging

## Support
For issues or questions, check the module logs or contact the development team.
