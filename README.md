# dp-insights

Articles module for `daems-platform`. Provides public read API + backstage
CRUD admin + i18n-aware sparkline stats + scheduled-publishing lifecycle.

## Layout

```
backend/
├── bindings.php          Production DI bindings (uses SqlInsightRepository)
├── bindings.test.php     Test DI bindings (uses InMemoryInsightRepository)
├── routes.php            HTTP route registrations
├── migrations/           Schema + data migrations (applied by core runner)
├── src/                  PSR-4 root for DaemsModule\Insights\
└── tests/                PHPUnit tests (Unit/Integration/Isolation)

frontend/
├── public/               Public-facing pages (mounted at /insights/*)
├── backstage/            Admin pages (mounted at /backstage/insights/*)
└── assets/               Per-module CSS/JS/images (mounted at /modules/insights/assets/*)

module.json               Manifest read by core's ModuleRegistry at boot
```

## Loading

Sites consume this module by symlinking or cloning into
`C:\laragon\www\modules\insights\` next to `daems-platform`. The platform's
`ModuleRegistry` scans `modules/*/module.json` at boot — no per-module
config needed in the consuming platform.

## Verification commands (from `daems-platform/`)

```
composer analyse
composer test
composer test:e2e
```

## License

Internal use, daems-platform tenants.
