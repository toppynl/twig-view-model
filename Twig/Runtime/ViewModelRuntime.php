<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Twig\Runtime;

use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Exception\NoDataException;
use Toppy\AsyncViewModel\Exception\ViewModelNotPreloadedException;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Toppy\TwigViewModel\ViewModelError;
use Twig\Extension\RuntimeExtensionInterface;

final class ViewModelRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ViewModelManagerInterface $manager,
    ) {}

    /**
     * Get view model data with error handling.
     *
     * Returns indexed array [data, error] for template sequence destructuring:
     *   {% do [product, error] = view('App\\ViewModel\\Product') %}
     *
     * @param array<string, mixed> $context Twig context (unused)
     * @param class-string<AsyncViewModel<object>> $class
     * @return array{0: object|null, 1: ViewModelError|null}
     *
     * @throws ViewModelNotPreloadedException When view model was not pre-loaded (developer bug)
     */
    // @mago-ignore analysis:possibly-invalid-argument - Generic type variance issue; $class is validated at runtime
    public function view(array $context, string $class): array
    {
        try {
            $data = $this->manager->get($class);

            return [$data, null];
        } catch (NoDataException) {
            return [null, null];
        } catch (ViewModelNotPreloadedException $e) {
            // Developer bug - rethrow to surface the error
            throw $e;
        } catch (\Throwable $e) {
            return [null, ViewModelError::fromException($e)];
        }
    }
}
