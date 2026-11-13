# Lawzeek CRS Calculator (WordPress plugin)

A free, standalone Express Entry CRS calculator that saves results as leads in WordPress. It needs no account and makes no external calls.

- **User-facing documentation:** `readme.txt` (shown on WordPress.org).
- **Development:** this file.

## Develop

Requirements:

- Node 20+
- PHP 7.4+
- Composer
- Docker (for wp-env)

```bash
npm install
composer install
npx wp-env start          # WordPress at http://localhost:8888 (admin / password)
npm run test:parity       # CRS engine vs the reference scores
npm run test:wp           # tests inside WordPress
npm run test:e2e          # browser tests (npx playwright install chromium first)
composer lint             # WordPress security + PHP compatibility checks
npm run build:css         # after changing admin screens (Tailwind source in assets/src)
npm run zip               # dist/lawzeek-crs-calculator-<version>.zip
```

To run the tests against another local WordPress instead of wp-env, set:

- `LZCRS_WP_PATH`: the WordPress folder;
- `LZCRS_PHP`: the PHP binary;
- `WP_BASE_URL`: the site URL.

## Release

1. Bump the version in **all four** places. `bin/build-zip.sh` fails if they differ.
   - `lawzeek-crs-calculator.php`: the plugin header **and** the `LZCRS_VERSION` constant
   - `readme.txt`: `Stable tag`
   - `blocks/crs-calculator/block.json`: `version`
2. Add a changelog entry in `readme.txt`.
3. Commit, then tag and push:
   ```bash
   git tag v1.0.1 && git push origin v1.0.1
   ```
4. The Release workflow re-runs every check, then creates a GitHub Release with the zip and its SHA-256.
5. WordPress.org deploy runs only when both of these hold:
   - the repository variable `WPORG_DEPLOY` is `true`;
   - a reviewer approves the `wordpress-org` environment.

   It needs the `SVN_USERNAME` and `SVN_PASSWORD` secrets, set in GitHub, never in code.

Images for the WordPress.org listing (icon, banner) live in `.wordpress-org/`.
