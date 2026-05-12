# Classic Editor

![Plugin Version](https://img.shields.io/badge/Version-1.6.7-blue)
![WordPress Version](https://img.shields.io/badge/WordPress-6.4-blue)
![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)
![License](https://img.shields.io/badge/License-GPL--2.0-green)
![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen)

> **Personal Fork / Independent Project** — This is not an official WordPress release. It is a clean-room fork maintained for core modernization, plugin development, or theme authorship under personal standards that exceed the WordPress project's own requirements. No upstream compatibility is guaranteed or intended.

---

## Overview

Enables the WordPress classic editor and the old-style Edit Post screen with TinyMCE, Meta Boxes, and more. This fork modernizes the original codebase to PHP 8.2+ standards while improving security, accessibility, and user experience.

---

## Requirements

| Requirement       | Minimum         | Recommended     |
|-------------------|-----------------|-----------------|
| PHP               | 8.2             | 8.2+            |
| WordPress Core    | 6.4             | Latest stable   |
| PHPStan           | Level 8         | Level 9+        |

---

## Installation

```bash
git clone https://github.com/cbuntingde/classic-editor.git
cd classic-editor
wp plugin activate classic-editor
```

---

## Features

### Core Functionality

- **Editor Selection** — Administrators can set the default editor for all users or allow individual choice.
- **Quick Switching** — Keyboard shortcut `Alt+Shift+E` and bidirectional UI links to switch between editors instantly.
- **REST API** — Programmatic management of editor preferences via the `/wp-json/classic-editor/v1/preferences` endpoint.
- **User Preferences** — Granular control over "remember per post" editor choices and default editor settings.

### Security

- Full OWASP 2026 compliance across all input/output handling.
- Enhanced nonce verification for all AJAX and REST actions.
- Input sanitization for all preference updates and settings handlers.
- Permission checks enforced on all endpoints to prevent unauthorized access.

### Code Quality

- PHP 8.2 strict types throughout — no exceptions.
- PSR-12 / PER coding style enforced.
- PHPStan level 8+ static analysis applied to the entire codebase.
- Zero deprecated functions, zero legacy patterns.

---

## Configuration

### Network Settings (Multisite)

When running a WordPress Multisite installation, you can configure network-wide defaults via **Network Admin → Settings**.

### Allow Users to Switch Editors

Users can choose their preferred editor in **Profile → Personal Options** when the "Allow users to switch editors" setting is enabled.

### REST API

Manage preferences programmatically:

```bash
# Get current preferences
curl -X GET https://example.com/wp-json/classic-editor/v1/preferences \
  -H "Authorization: Bearer $TOKEN"

# Update preferences
curl -X PUT https://example.com/wp-json/classic-editor/v1/preferences \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"editor":"block","remember_per_post":true}'
```

---

## Changelog

Changes are organized by feature area.

### REST API

- Added `/wp-json/classic-editor/v1/preferences` GET/PUT endpoints.
- Implemented structured preference storage for editor selection and "remember per post" settings.
- Added strict permission callbacks for all REST routes.

### UI / UX

- Added `Alt+Shift+E` keyboard shortcut for rapid editor switching.
- Implemented bidirectional switching buttons within the block editor toolbar and classic editor title area.
- Added support for editor switching directly from post row actions in the admin list table.
- Added "Remember editor per post" option to user profile and writing settings.

### Accessibility & UI

- Added ARIA labels and focus states to all new interactive elements.
- Implemented high contrast and reduced motion support for administrative UI.
- Improved CSS for better visual consistency with modern WordPress admin themes.
- Fixed Safari 18 negative horizontal margin issues on floats.

### Security & Infrastructure

- Refactored AJAX handlers to use modern nonce verification and permission checks.
- Implemented strict input sanitization for all user-submitted preferences.
- Replaced legacy parameter handling with explicit typed methods.
- Added REST API GET endpoint permission callback to require `edit_posts` capability.
- Fixed potential null dereference in block editor settings hook handler.
- Removed unused JavaScript variables from admin.js.

### Compatibility

- Fixed WordPress 6.7+ compatibility for the Categories postbox by replacing `post.js` logic.
- Ensured compatibility with WordPress 5.8+ block editor settings and hooks.

### Modernization (PHP 8.2+)

- Converted the entire codebase to use PHP 8.2 strict typing.
- Removed all legacy patterns (no `extract()`, no global state leaks).
- Replaced `@`-suppressed operations with explicit exception handling.

### Removed

- Removed support for PHP versions below 8.2.
- Removed legacy "Gutenberg" plugin compatibility shims for versions before WP 5.0.
- Removed all `eval()` and dynamic function generation.
- Replaced `global $wpdb` usage with modern database service patterns where applicable.

---

## Development

### Local Setup

```bash
# Clone the repository
git clone https://github.com/cbuntingde/classic-editor.git
cd classic-editor

# Install PHP dependencies
composer require --dev phpstan/phpstan squizlabs/php_codesniffer

# Run static analysis
vendor/bin/phpstan analyse --level=8

# Run code style check
vendor/bin/phpcs --standard=PSR12
```

### Commit Standards

Commits describe completed work, not process or sequence:

```
✅ add typed preferences REST endpoint
✅ resolve Safari 18 CSS margin regression
✅ remove legacy PHP 7.4 compatibility shims
```

---

## Security Policy

This project follows OWASP 2026 guidelines. If you discover a vulnerability:

1. Do **not** open a public issue.
2. Email `cbuntingde@gmail.com` with a description and reproduction steps.
3. Expect acknowledgment within 72 hours.

---

## Standards & References

- [PHP 8.3 Documentation](https://www.php.net/releases/8.3/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PER Coding Style](https://www.php-fig.org/per/coding-style/)
- [OWASP 2026 Top Ten](https://owasp.org/www-project-top-ten/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)

---

## License

GPLv2 or later. See LICENSE.md for details.

---

## Credits

Based on the [Classic Editor](https://wordpress.org/plugins/classic-editor/) plugin by WordPress contributors.

---

*This project is independently maintained. It is not affiliated with, endorsed by, or supported by Automattic or the WordPress Foundation.*
