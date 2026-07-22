# Plugins and integrations — the other two extension points

Workstream 16 · post-W13/W14

## Three extension points, one pattern

ESP v2 has three parallel extension mechanisms that all follow the same
service-provider + registry pattern:

| kind | folder | registry | semantic |
|------|--------|----------|----------|
| **module** | `backend/modules/<Name>/` | `config/modules.php` | Owns domain data + business logic (a service module) |
| **plugin** | `backend/plugins/<Name>/` | `config/plugins.php` | Install-time optional cross-domain capability |
| **integration** | `backend/integrations/<Name>/` | `config/integrations.php` | Adapter for ONE external system |

Each has its own `App\Providers\XxxServiceProvider` that iterates the
matching `config('xxx.enabled')` map at register-time and boots each
provider.

## Plugins

A **plugin** adds a capability that any tenant may choose to enable,
independent of the domain. Removing a plugin's key from
`config/plugins.php` cleanly removes:
- Its routes (from its `routes.php`)
- Its middleware aliases (registered inside the provider)
- Its migrations (loaded by the provider)
- Its console commands
- Any container bindings

Current plugins:

| id | backend | owns |
|----|---------|------|
| `ai-schema` | `backend/plugins/AiSchema/` | Claude-backed schema-authoring endpoints for the admin (3 routes) |
| `captcha` | `backend/plugins/Captcha/` | Public-form challenge/response (login, register). Owns the `captcha` middleware alias. |

### Plugin dep-direction

- `PLG → PC`: allowed (plugins consume platform primitives)
- `PLG → SM`: allowed (plugins may depend on service modules — e.g.
  `ai-schema` reads `Modules\JeaServices\Models\ServiceDefinition` to
  write generated schemas back)
- `PLG → PLG`: allowed but rare
- `SM → PLG`: FORBIDDEN (services must not depend on install-time optional plugins)
- `PC → PLG`: FORBIDDEN (platform mustn't depend on install-time optional plugins)

## Integrations

An **integration** is an adapter for exactly one external system. Same
provider + registry pattern as plugins. Difference: an integration
brings a whole external world into scope (its own auth, its own logs,
its own middleware, its own outbound calls).

Current integrations:

| id | backend | owns |
|----|---------|------|
| `gsb` | `backend/integrations/Gsb/` | Jordan Government Service Bus adapter (MODEE Annex 4.15). 4 routes, `gsb.ip_whitelist` middleware, GSB call-log table, prune command. |
| `nashmi` | `backend/integrations/Nashmi/` | Contractor-management inbound/outbound. 6 routes (outside Sanctum, validated by X-Integration-Key), `integration.key` middleware, integration_cycles table. |

### Integration dep-direction

- `EIA → PC`: allowed
- `EIA → SM`: allowed but rare (Nashmi doesn't do this today; a
  future adapter that acknowledges an Application ID could)
- `PC → EIA`: FORBIDDEN (with one documented allowlist — the composition
  root `AppServiceProvider` binds `Integrations\Gsb\Services\*` into the
  container; can move into `GsbServiceProvider` itself in a future WS)
- `SM → EIA`: FORBIDDEN (services must be adapter-agnostic)

## When to choose which

Deciding whether new work is a module, plugin, or integration:

- **New JEA-specific business capability that manages its own tables +
  workflow?** → Module.
- **Cross-domain capability (auth flavor, spam-prevention, AI writing
  assistant, feature flag system) that any tenant may want?** → Plugin.
- **New external system this app talks to?** → Integration.

## Middleware-alias ownership

Middleware aliases moved from `bootstrap/app.php` into their owning
plugin / integration provider when the module owns the alias. This
means disabling the plugin drops its alias too — no dangling reference
to a class the autoloader can't find.

Aliases currently owned by their extension:

| alias | provider | class |
|-------|----------|-------|
| `captcha` | `Plugins\Captcha\Providers\CaptchaServiceProvider` | `Plugins\Captcha\Http\Middleware\VerifyCaptcha` |
| `gsb.ip_whitelist` | `Integrations\Gsb\Providers\GsbServiceProvider` | `Integrations\Gsb\Http\Middleware\GsbIpWhitelist` |
| `integration.key` | `Integrations\Nashmi\Providers\NashmiServiceProvider` | `Integrations\Nashmi\Http\Middleware\ValidateIntegrationKey` |

Aliases still in `bootstrap/app.php` are all platform-owned
(`role`, `token.inactivity`, `password.policy`, `track.activity`).

## Adding one

- Plugin walkthrough → [`07-adding-a-plugin.md`](07-adding-a-plugin.md).
- Integration walkthrough → same file, "Adding an integration" section
  (the diff from plugins is small — different config key, different
  folder, different dep-direction rules).
