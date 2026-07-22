<?php

declare(strict_types=1);

/**
 * Plugins configuration — Workstream 13.
 *
 * Each key is a plugin ID (lower-kebab). The value is the fully-
 * qualified service-provider class that boots the plugin. The
 * platform's App\Providers\PluginsServiceProvider iterates this
 * map at register-time and registers each provider — so removing a
 * key here disables the whole plugin (routes disappear, middleware
 * aliases are gone, any container bindings are removed).
 *
 * Plugins vs. modules:
 *   • Modules (config/modules.php) own domain data + business logic
 *     — a JEA-service module doesn't make sense without JEA.
 *   • Plugins (this file) add cross-domain capabilities that any
 *     tenant may choose to enable — captcha, AI schema generation,
 *     future SSO providers, etc. They're install-time optional.
 *
 * A plugin MUST NOT be listed here if its provider class doesn't
 * exist yet — Composer will throw at register-time.
 */

return [
    'enabled' => [
        'ai-schema' => \Plugins\AiSchema\Providers\AiSchemaServiceProvider::class,
        'captcha'   => \Plugins\Captcha\Providers\CaptchaServiceProvider::class,
    ],
];
