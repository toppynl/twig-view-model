# Twig View Model

> **Read-Only Repository**
> This is a read-only subtree split from the main repository.
> Please submit issues and pull requests to [toppynl/symfony-astro](https://github.com/toppynl/symfony-astro).

Twig integration for async view models - provides the `view()` function for accessing resolved data in templates. This package bridges the `toppy/async-view-model` core with Twig's template engine through compile-time AST scanning and runtime data access.

## Installation

```bash
composer require toppy/twig-view-model
```

## Requirements

- PHP 8.4+
- Twig 3.23+
- [toppy/async-view-model](https://github.com/toppynl/symfony-astro) (automatically installed as dependency)

## Quick Start

```twig
{# Template declares which data it needs, then accesses resolved results #}
{% set [product, error] = view('App\\ViewModel\\ProductViewModel') %}

{% if error %}
    <div class="error">{{ error.message }}</div>
{% elseif product %}
    <h1>{{ product.name }}</h1>
    <p>{{ product.description }}</p>
{% endif %}
```

Register the extension with Twig:

```php
use Toppy\TwigViewModel\Twig\ViewExtension;
use Toppy\TwigViewModel\Twig\Runtime\ViewModelRuntime;

$twig->addExtension(new ViewExtension($twig->getLoader()));
$twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
    public function load(string $class): ?object {
        if ($class === ViewModelRuntime::class) {
            return new ViewModelRuntime($viewModelManager);
        }
        return null;
    }
});
```

## Architecture

### Key Classes

| Class | Purpose |
|-------|---------|
| `ViewExtension` | Twig extension that registers the `view()` function and AST visitor |
| `ViewDiscoveryVisitor` | Node visitor that scans templates at compile-time to discover all `view()` calls |
| `ViewModelRuntime` | Runtime handler for the `view()` function, interfaces with `ViewModelManagerInterface` |
| `DoPreloadMethodNode` | Twig node that generates a `doPreload()` method in compiled templates |
| `ViewModelError` | Template-facing error representation with structured codes for error handling |

### Directory Structure

```
TwigViewModel/
├── Twig/
│   ├── ViewExtension.php           # Twig extension registration
│   ├── Runtime/
│   │   └── ViewModelRuntime.php    # view() function implementation
│   ├── NodeVisitor/
│   │   └── ViewDiscoveryVisitor.php # Compile-time AST scanning
│   └── Node/
│       └── DoPreloadMethodNode.php  # Generates doPreload() method
├── ViewModelError.php               # Error representation for templates
├── Tests/
│   └── Unit/                        # PHPUnit test suite
├── composer.json
└── phpunit.xml
```

## Usage

### The view() Function

The `view()` function retrieves resolved data from a preloaded view model. It returns an indexed array for sequence destructuring:

```twig
{% set [data, error] = view('App\\ViewModel\\StockViewModel') %}
```

The return value is always a 2-element array:
- `data` - The resolved data object (or `null` if unavailable)
- `error` - A `ViewModelError` object (or `null` if successful)

#### Handling Different States

```twig
{% set [stock, error] = view('App\\ViewModel\\StockViewModel') %}

{# Error state - resolution failed #}
{% if error %}
    {% if error.code == 'TIMEOUT' %}
        <span>Stock information temporarily unavailable</span>
    {% elseif error.code == 'NOT_FOUND' %}
        <span>Product not found</span>
    {% else %}
        <span>Error: {{ error.message }}</span>
    {% endif %}

{# Success state - data available #}
{% elseif stock %}
    <span>{{ stock.quantity }} in stock</span>

{# No data state - view model returned nothing #}
{% else %}
    <span>Stock information not available</span>
{% endif %}
```

#### Error Codes

The `ViewModelError` maps exceptions to semantic codes:

| Code | Exception Type | Description |
|------|---------------|-------------|
| `NOT_FOUND` | `NotFoundHttpException` | Resource doesn't exist |
| `FORBIDDEN` | `AccessDeniedHttpException` | Access denied |
| `UNAUTHORIZED` | `UnauthorizedHttpException` | Authentication required |
| `SERVICE_UNAVAILABLE` | `ServiceUnavailableHttpException` | Backend service down |
| `RATE_LIMITED` | `TooManyRequestsHttpException` | Rate limit exceeded |
| `TIMEOUT` | `TimeoutException` | Request timed out |
| `RESOLUTION_FAILED` | `ViewModelResolutionException` | Generic resolution failure |
| `UNKNOWN` | Any other exception | Unexpected error |

### AST Discovery

The `ViewDiscoveryVisitor` performs compile-time scanning of Twig templates to discover all view model dependencies. This enables the preloading system to know which view models a template needs before rendering begins.

#### How It Works

1. **Template Compilation**: When Twig compiles a template, the visitor scans the AST
2. **view() Detection**: Finds all `view('ClassName')` function calls
3. **Validation**: Verifies each class exists and implements `AsyncViewModel`
4. **Include Scanning**: Recursively scans static `{% include %}` directives
5. **Method Injection**: Generates a `doPreload()` method in the compiled template class

#### Generated doPreload() Method

Each compiled template with `view()` calls gets a `doPreload()` method:

```php
// Auto-generated in compiled template
public function doPreload(): array
{
    $classes = [
        'App\\ViewModel\\ProductViewModel',
        'App\\ViewModel\\StockViewModel',
    ];

    // Chain to parent template if exists
    $parentName = $this->doGetParent([]);
    if ($parentName !== false) {
        $parent = $this->load($parentName, 0)->unwrap();
        if (method_exists($parent, 'doPreload')) {
            $classes = array_merge($parent->doPreload(), $classes);
        }
    }

    return array_values(array_unique($classes));
}
```

This method:
- Returns all view model classes discovered in the template
- Chains to parent templates (for `{% extends %}` hierarchies)
- Deduplicates results across the inheritance chain

#### Compile-Time Validation

The visitor validates at compile time:

```twig
{# Error: Class does not exist #}
{% set [data, error] = view('App\\NonExistent\\ViewModel') %}
{# Throws: View model class "App\NonExistent\ViewModel" does not exist. #}

{# Error: Class doesn't implement AsyncViewModel #}
{% set [data, error] = view('App\\Entity\\Product') %}
{# Throws: Class "App\Entity\Product" must implement AsyncViewModel. #}
```

#### Include Scanning

Static includes are recursively scanned:

```twig
{# main.html.twig #}
{% set [product, error] = view('App\\ViewModel\\ProductViewModel') %}
{% include 'partials/stock.html.twig' %}

{# partials/stock.html.twig #}
{% set [stock, error] = view('App\\ViewModel\\StockViewModel') %}
```

The `doPreload()` method for `main.html.twig` will include both `ProductViewModel` and `StockViewModel`.

**Note**: Dynamic includes (`{% include variable %}`) cannot be scanned at compile-time.

## Integration

This package is Layer 1 in the Toppy Stack architecture:

```
┌─────────────────────────────────────┐
│  symfony-async-twig-bundle (L3)     │  Symfony integration
└──────────────────┬──────────────────┘
                   │
      ┌────────────┴────────────┐
      ▼                         ▼
┌─────────────┐      ┌──────────────────┐
│ twig-prerender (L2)│  twig-streaming   │
└─────────────┘      └──────────────────┘
                   │
         ┌─────────┴─────────┐
         ▼                   ▼
┌─────────────────┐  ┌────────────────────┐
│ twig-view-model │  │                    │
│      (L1)       │  │                    │
└────────┬────────┘  │                    │
         │           │                    │
         └─────┬─────┘                    │
               ▼                          │
┌─────────────────────────────────────────┘
│        async-view-model (L0)
│     Framework-agnostic core
└─────────────────────────────────────────
```

### Dependency on async-view-model

This package depends on `toppy/async-view-model` for:

- `AsyncViewModel` interface - Contract for async data fetching
- `ViewModelManagerInterface` - Orchestrates view model resolution
- `NoDataException` - Signals no data available (not an error)
- `ViewModelNotPreloadedException` - Developer error: view model wasn't preloaded
- `ViewModelResolutionException` - Resolution failed with error details

## Testing

Run the test suite:

```bash
cd src/Toppy/Component/TwigViewModel
./vendor/bin/phpunit
```

Or from the monorepo root:

```bash
make demo-shell
cd /app/src/Toppy/Component/TwigViewModel && ./vendor/bin/phpunit
```

### Test Coverage

- `ViewModelRuntimeTest` - Tests the `view()` function behavior
- `ViewModelErrorTest` - Tests error code mapping and serialization

## License

Proprietary - See [LICENSE](LICENSE) file for details.
