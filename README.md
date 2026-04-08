<p align="center">
  <a href="https://perfbase.com">
    <img src="https://cdn.perfbase.com/img/logo-full.svg" alt="Perfbase" width="300">
  </a>
</p>

<h3 align="center">Perfbase for CodeIgniter 4</h3>
<p align="center">
  CodeIgniter 4 integration for <a href="https://perfbase.com">Perfbase</a>.
</p>

<p align="center">
  <a href="https://packagist.org/packages/perfbase/codeigniter4"><img src="https://img.shields.io/packagist/v/perfbase/codeigniter4" alt="Packagist Version"></a>
  <a href="https://github.com/perfbaseorg/codeigniter4/blob/main/LICENSE.txt"><img src="https://img.shields.io/packagist/l/perfbase/codeigniter4" alt="License"></a>
  <a href="https://github.com/perfbaseorg/codeigniter4/actions/workflows/ci.yml"><img src="https://img.shields.io/github/actions/workflow/status/perfbaseorg/codeigniter4/ci.yml?branch=main" alt="CI"></a>
  <img src="https://img.shields.io/badge/php-8.2%2B-blue" alt="PHP Version">
  <img src="https://img.shields.io/badge/codeigniter-4.x-blue" alt="CodeIgniter Version">
</p>

This package is a thin adapter over [`perfbase/php-sdk`](https://packagist.org/packages/perfbase/php-sdk). It wires CodeIgniter request and Spark command lifecycles into the Perfbase SDK without adding its own transport, queueing, or buffering layer.

## What This Package Supports

Supported in v1:

- HTTP request profiling through a global CodeIgniter filter
- Spark CLI profiling through `pre_command` and `post_command` events
- optional authenticated user enrichment through a custom resolver or CodeIgniter Shield
- uncaught HTTP exception cleanup through a Perfbase-aware exception service
- worker-safe lifecycle cleanup for FrankenPHP worker mode

Not supported in v1:

- queue worker adapters
- scheduler adapters
- automatic profiling for third-party task runners
- custom dashboards or CodeIgniter-specific UI

## Requirements

- PHP `>=8.2 <8.5`
- CodeIgniter `^4.3.8`
- Perfbase PHP extension
- `perfbase/php-sdk` `1.0.0`

Optional:

- `codeigniter4/shield` for first-class authenticated user resolution

The current verified support floor is CodeIgniter `4.3.8` on PHP `8.2+`.

## Installation

Install the package:

```bash
composer require perfbase/codeigniter4
```

For monorepo development, this package is already configured to use the local sibling SDK checkout via the path repository in `composer.json`.

## Quick Start

1. Install the package.
2. Run the installer:

```bash
php spark perfbase:install
```

3. Set your API key in the environment or published config.
4. Make sure the Perfbase PHP extension is installed and enabled.
5. Verify the setup:

```bash
php spark perfbase:doctor
```

### What `perfbase:install` Does

The installer:

- publishes `app/Config/Perfbase.php`
- registers the `perfbase` filter alias in `app/Config/Filters.php`
- adds `perfbase` to global `before` and `after` filters

If you only want the alias and do not want the command to modify global filters:

```bash
php spark perfbase:install --alias-only
```

If you prefer to publish the config manually:

```bash
php spark publish --namespace Perfbase\\CodeIgniter4
```

## Configuration

After installation, configuration lives in `app/Config/Perfbase.php`.

The package also supports environment-backed defaults via `src/Config/Perfbase.php`.

### Configuration Options

```php
public bool $enabled = false;
public bool $debug = false;
public bool $logErrors = true;
public string $apiKey = '';
public string $apiUrl = 'https://ingress.perfbase.cloud';
public float $sampleRate = 0.1;
public int $timeout = 10;
public ?string $proxy = null;
public int $flags = FeatureFlags::DefaultFlags;
public string $appVersion = '';
public ?string $userResolver = null;
public array $includeHttp = ['*'];
public array $excludeHttp = [];
public array $includeConsole = ['*'];
public array $excludeConsole = [];
```

### Environment Variables

```dotenv
perfbase.enabled=true
perfbase.debug=false
perfbase.logErrors=true
perfbase.apiKey=
perfbase.apiUrl=https://ingress.perfbase.cloud
perfbase.sampleRate=0.1
perfbase.timeout=10
perfbase.proxy=
perfbase.flags=0
perfbase.appVersion=
perfbase.userResolver=
perfbase.includeHttp=*
perfbase.excludeHttp=
perfbase.includeConsole=*
perfbase.excludeConsole=
```

### Example Production Configuration

```php
<?php

namespace Config;

use Perfbase\CodeIgniter4\Config\Perfbase as BasePerfbase;
use Perfbase\SDK\FeatureFlags;

final class Perfbase extends BasePerfbase
{
    public bool $enabled = true;
    public bool $debug = false;
    public bool $logErrors = true;
    public string $apiKey = '';
    public float $sampleRate = 0.2;
    public int $timeout = 10;
    public int $flags = FeatureFlags::DefaultFlags;
    public string $appVersion = '1.0.0';
    public array $excludeHttp = ['/health', '/up'];
    public array $excludeConsole = ['cache:*', 'migrate:*'];
}
```

## Verification and Diagnostics

Run:

```bash
php spark perfbase:doctor
```

The doctor checks:

- whether profiling is enabled
- whether an API key is configured
- whether the SDK booted successfully
- whether the Perfbase extension is available
- whether the `perfbase` alias exists
- whether global `before` and `after` filters are configured
- whether Shield was detected
- whether FrankenPHP worker mode is active or configured

If worker mode is detected, the doctor also prints a reminder to restart workers after config or deployment changes.

## Compatibility Testing

Normal package CI stays on the declared support floor. Older CodeIgniter 4 minors should be tested explicitly.

### Local Docker Compatibility Probes

Run compatibility probes locally without depending on the host PHP version:

```bash
bin/test-compat --php 8.2 --framework 4.3.8
bin/test-compat --php 8.2 --framework 4.4.8
bin/test-compat --php 8.2 --framework 4.6.5
```

Notes:

- The script mounts the parent `projects/` directory so the local sibling `lib-php-sdk` path repository continues to work inside the container.
- `--ignore-php-platform` is intended for floor exploration when the package's current declared PHP floor is higher than the probe target.
- The script runs `composer update`, `composer run phpstan`, and `composer run test` inside the selected container image.

### GitHub Actions Compatibility Probes

The manual workflow in `.github/workflows/compatibility.yml` runs a small set of explicit PHP/CodeIgniter pairs:

- PHP `8.2` / CodeIgniter `4.3.8`
- PHP `8.2` / CodeIgniter `4.4.8`
- PHP `8.2` / CodeIgniter `4.5.8`
- PHP `8.2` / CodeIgniter `4.6.5`
- PHP `8.4` / CodeIgniter `4.7.2`

This workflow is manual on purpose. It is for compatibility investigation and release-floor validation, not for every pull request.

## HTTP Profiling

HTTP profiling is implemented by the `perfbase` filter.

The package:

- starts profiling in the filter `before()` phase
- finalizes successful requests in `after()`
- finalizes uncaught exception paths through the Perfbase exception service
- excludes query strings from `http_url`
- prefers route patterns for low-cardinality action naming
- falls back to `Controller::method` and then the sanitized path

### HTTP Attributes

The HTTP lifecycle sets:

- `source=http`
- `action`
- `http_method`
- `http_url`
- `http_status_code`
- `user_ip`
- `user_agent`
- `user_id` when available
- `hostname`
- `environment`
- `app_version`
- `php_version`

`http_url` intentionally excludes query strings to avoid leaking secrets and to reduce cardinality.

## Spark CLI Profiling

CLI profiling is wired through `pre_command` and `post_command` events.

The package:

- starts profiling before the command runs
- records the command name as the action
- records exit codes on completion
- records thrown exceptions on failure
- always clears active command state after completion

## Filter Matching

HTTP and console include/exclude filters support:

- `*`
- `.*`
- glob patterns such as `/admin/*` or `cache:*`
- regex patterns such as `/^GET \/api\//`

HTTP matching evaluates:

- request path
- `METHOD path`
- route pattern when available
- `METHOD routePattern`
- controller
- method
- `Controller::method`

Console matching evaluates the Spark command name.

Malformed regex filters fail closed and will not match.

## Sampling

`sampleRate` must be between `0.0` and `1.0`.

- `0.0` disables profiling for that context
- `1.0` profiles every matching request or command
- values between `0.0` and `1.0` are sampled probabilistically

## User Resolution

User resolution runs in this order:

1. configured custom resolver from `$userResolver`
2. runtime CodeIgniter Shield detection
3. a request-attached `$request->user` fallback

Only stable scalar identifiers are recorded.

### Custom User Resolver

Set `userResolver` to either:

- a CodeIgniter service name
- a fully-qualified class name

The resolver must implement `Perfbase\CodeIgniter4\Contracts\UserResolverInterface`:

```php
namespace App\Support;

use CodeIgniter\HTTP\RequestInterface;
use Perfbase\CodeIgniter4\Contracts\UserResolverInterface;

final class PerfbaseUserResolver implements UserResolverInterface
{
    public function resolve(RequestInterface $request): ?string
    {
        return '123';
    }
}
```

Then configure either:

```php
public ?string $userResolver = 'perfbaseUserResolver';
```

or:

```php
public ?string $userResolver = App\Support\PerfbaseUserResolver::class;
```

### Shield Integration

If Shield is installed and its auth service is available, the package will try to resolve the current user automatically. Shield is optional and is not a hard Composer dependency.

## Error Handling

The package fails open by default.

- profiling failures must not break the host application in production
- submission failures are handled internally
- invalid runtime conditions are logged only when configured
- when `debug=true`, profiling errors are rethrown to make integration problems visible

Use `debug=true` only while integrating or diagnosing package behavior.

## Worker Mode

The package is designed to be safe under FrankenPHP worker mode:

- request and command state is kept only in the active lifecycle registry
- successful, failed, and exceptional flows all clear active lifecycle state
- no request-specific data is cached in the client provider, error handler, or exception service

If you run CodeIgniter under FrankenPHP workers:

- restart workers after changing Perfbase config
- restart workers after changing deployment artifacts
- keep any custom user resolver stateless or request-scoped

## Troubleshooting

### `perfbase:doctor` says the extension is unavailable

The Perfbase PHP extension is not loaded in the PHP runtime executing CodeIgniter. Verify the extension is installed, enabled, and visible to both web and CLI SAPIs.

### `perfbase:doctor` says the filter alias or globals are missing

Run:

```bash
php spark perfbase:install
```

If you manage filters manually, verify that:

- the `perfbase` alias exists in `app/Config/Filters.php`
- `perfbase` is present in both global `before` and `after` filters

### No traces are being sent

Check, in order:

1. `enabled` is true
2. `apiKey` is configured
3. the extension is available
4. your request or command is not excluded by filters
5. `sampleRate` is not effectively disabling the context

### Profiling errors should fail loudly during integration

Set:

```php
public bool $debug = true;
```

That makes profiling errors rethrow instead of being swallowed.

## Development

```bash
composer install
composer run test
composer run phpstan
composer run lint
```

Current local verification for this package:

- PHPUnit passing
- PHPStan passing
- coverage at or above the framework guide thresholds

## Documentation

Full documentation is available at [perfbase.com/docs](https://perfbase.com/docs).

- **Docs**: [perfbase.com/docs](https://perfbase.com/docs)
- **Issues**: [github.com/perfbaseorg/codeigniter4/issues](https://github.com/perfbaseorg/codeigniter4/issues)
- **Support**: [support@perfbase.com](mailto:support@perfbase.com)

## License

Apache-2.0. See [LICENSE.txt](LICENSE.txt).
