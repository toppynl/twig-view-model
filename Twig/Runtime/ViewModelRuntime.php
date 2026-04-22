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

            return new ViewModelResult($data, null);
        } catch (NoDataException) {
            return new ViewModelResult(null, null);
        } catch (ViewModelNotPreloadedException $e) {
            // Developer bug - rethrow to surface the error
            throw $e;
        } catch (\Throwable $e) {
            return new ViewModelResult(null, ViewModelError::fromException($e));
        }
    }
}
