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
     * @template T of object
     * @param array<string, mixed> $context Twig context (unused)
     * @param class-string<AsyncViewModel<T>> $class
     * @return array{0: T|null, 1: ViewModelError|null}
     */
    public function view(array $context, string $class): array
    {
        try {
            $future = $this->manager->preloadWithFuture($class);
            $data = $future->await();

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
