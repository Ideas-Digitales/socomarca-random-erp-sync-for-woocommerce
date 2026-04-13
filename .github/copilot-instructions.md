# Copilot Instructions for socomarca-random-erp-sync-for-woocommerce

## Build, test, and lint commands

This plugin is PHP/WordPress-based and does not define a separate build step.

- Install dependencies:
  - `composer install`

- Run tests (Pest):
  - Full suite: `composer test` or `./vendor/bin/pest`
  - Unit tests only: `composer test:unit` or `./vendor/bin/pest tests/Unit/`
  - Integration tests (real Random ERP API): `composer test:integration` or `./vendor/bin/pest tests/Integration/`
  - Fast unit tests in parallel: `composer test:fast`

- Run a single test:
  - By file: `./vendor/bin/pest tests/Unit/BaseApiServiceTest.php`
  - By test name: `./vendor/bin/pest --filter="test name fragment"`

- Lint (as used in CI):
  - `phpcs`
  - Uses `.phpcs.xml.dist` and WordPress Coding Standards setup in CI.

## High-level architecture

- **Bootstrap and wiring**
  - `index.php` defines plugin constants, loads Composer autoload (if present), registers the custom autoloader, then initializes `Socomarca\RandomERP\Plugin` on `plugins_loaded`.
  - `src/Plugin.php` is the central composition root. It instantiates Admin pages, AJAX handlers, services, shortcode/filter integrations, and registers activation/deactivation/uninstall hooks.

- **Admin UI + AJAX orchestration**
  - `src/Admin/AdminPages.php` registers the top-level Socomarca admin page, enqueues `assets/js/main.js`, and localizes AJAX URL + nonce.
  - Sync operations are driven from admin UI via `wp_ajax_*` handlers in `src/Ajax/*AjaxHandler.php`.

- **Service layer and ERP communication**
  - Services in `src/Services` hold business logic (entities, categories, products, price lists, brands, documents, cron sync).
  - `BaseApiService` handles API mode selection (development vs production), token lifecycle, login (`/login`), and authenticated requests to Random ERP endpoints.
  - Domain services transform ERP payloads into WooCommerce/WordPress objects.

- **Batch processing model**
  - Large sync operations are split into fetch/cache + batch processing phases.
  - Data/progress is persisted in WordPress options (`sm_*` keys), and batch endpoints consume `offset` + `batch_size` (default 10).
  - Combined deletion is explicitly phased in order: products → categories → users (`CombinedAjaxHandler`).

- **Automation and order-document integration**
  - `CronSyncService` schedules/executes daily full sync (`sm_erp_auto_sync`) and stores last-run metadata/logs in options.
  - `DocumentService` hooks WooCommerce order completion (when enabled) to create ERP documents and log details to `logs/documents.log` + order notes.

## Key repository-specific conventions

- Namespace and class layout:
  - Use `Socomarca\RandomERP\...` namespace; classes map to `src/` paths (PSR-4 + custom autoloader compatibility).

- Option key conventions:
  - Plugin state, credentials, cache, and progress are stored in `wp_options` with `sm_` prefix (plus `random_erp_token`).
  - New long-running workflows should persist resumable state in options, following existing cache/progress patterns.

- AJAX naming and response shape:
  - AJAX actions are prefixed `sm_` (except `validate_connection` legacy action).
  - Handlers extending `BaseAjaxHandler` should use `sendSuccessResponse`, `sendErrorResponse`, or `sendJsonResponse` for consistent payload shape.

- Security patterns for mutating actions:
  - Use capability checks (`manage_options`) and explicit confirmation text for destructive operations (`DELETE_ALL_*` patterns).
  - Nonces are used in admin form and selected AJAX endpoints; preserve or extend existing nonce flows when adding endpoints.

- ERP auth behavior by mode:
  - Development mode authenticates with user/password and auto-refreshes token.
  - Production mode uses manually configured token and treats 401 as hard failure.

- Operational diagnostics:
  - Existing code uses `error_log` heavily for execution tracing; preserve actionable logging in sync and API error paths.
