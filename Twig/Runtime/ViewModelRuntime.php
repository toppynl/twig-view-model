<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Twig\Runtime;

use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Exception\NoDataException;
use Toppy\AsyncViewModel\Exception\ViewModelNotPreloadedException;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\TwigViewModel\ViewModelError;
use Toppy\TwigViewModel\ViewModelResult;
use Twig\Extension\RuntimeExtensionInterface;

final class ViewModelRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ViewModelManagerInterface $manager,
    ) {}

    /**
     * Get view model data with error handling.
     *
     * Returns ViewModelResult for object destructuring with renaming:
     *   {% do {data: product, error: productError} = view('App\\ViewModel\\Product') %}
     *
     * @param array<string, mixed> $context Twig context (unused)
     * @param class-string<AsyncViewModel<object>> $class
     *
     * @throws ViewModelNotPreloadedException When view model was not pre-loaded (developer bug)
     */
    // @mago-ignore analysis:possibly-invalid-argument - Generic type variance issue; $class is validated at runtime
    public function view(array $context, string $class): ViewModelResult
    {
        try {
            $data = $this->manager->get($class);

            // The non-decorated manager returns an UNINITIALIZED lazy proxy:
            // truthy, with resolution failures only thrown on first property
            // access inside the template — escaping these catches and breaking
            // the {data, error} contract. Initialize it here so failures take
            // the documented path.
            $reflector = new \ReflectionClass($data);
            if ($reflector->isUninitializedLazyObject($data)) {
                $reflector->initializeLazyObject($data);
            }

            return new ViewModelResult($data, null);
        } catch (NoDataException) {
            return new ViewModelResult(null, null);
        } catch (ViewModelNotPreloadedException $e) {
            // Developer bug - rethrow to surface the error
            throw $e;
        } catch (\Throwable $e) {
            // The lazy proxy initializer wraps every failure (including
            // NoDataException) in a ViewModelResolutionException; unwrap so
            // "no data" still degrades gracefully instead of reporting an error.
            if ($this->causedByNoData($e)) {
                return new ViewModelResult(null, null);
            }

            return new ViewModelResult(null, ViewModelError::fromException($e));
        }
    }

    private function causedByNoData(\Throwable $e): bool
    {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof NoDataException) {
                return true;
            }
        }

        return false;
    }
}
