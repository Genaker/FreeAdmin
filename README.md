# Genaker FreeAdmin Module

## Overview
This module allows free admin login when the Magento_Backend module is disabled in `app/etc/config.php`.

## How It Works
The module uses a before plugin on `Magento\Backend\Model\Auth::login()` method to:
1. **Security Check**: Never bypass authentication in production mode
2. Check if `Magento_Backend` module is disabled using Magento's `ModuleList` and `DeploymentConfig` classes
3. If disabled AND not in production mode, bypass normal authentication by:
   - First trying to find admin user by the provided email/username
   - If not found, falling back to the first available admin user
4. If enabled or in production mode, proceed with normal authentication

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
- **Never bypasses authentication in production mode** - additional safety measure
- It bypasses authentication when enabled by using existing admin users
- Requires at least one admin user to exist in the system
- Only works in development or default modes

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

### Still Asking for Credentials
1. Ensure `Magento_Backend` is set to `0` in `config.php`
2. Check file permissions on `config.php`
3. Verify the plugin is properly registered in `di.xml`

## Customization
To modify the authentication bypass logic, edit `SimpleLoginPlugin.php` in the `beforeLogin` method.

## Support
For issues or questions, check the module logs or contact the development team.
