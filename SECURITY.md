# Security policy

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Report them privately through GitHub: **Security → Report a vulnerability** on this repository. We aim to reply within 3 working days and to release a fix within 14 days for confirmed issues, and we will credit you in the changelog if you wish.

## Supported versions

Only the latest release on WordPress.org receives security fixes.

## How this plugin is checked

Every change runs these checks before it can be merged or released:

- secret scanning;
- PHP syntax on PHP 7.4–8.4;
- WordPress security sniffs (escaping, sanitizing, nonces, prepared SQL) with PHPCS;
- PHP compatibility;
- the official WordPress.org Plugin Check;
- tests inside a real WordPress;
- browser tests.

Releases to WordPress.org need a manual approval.
