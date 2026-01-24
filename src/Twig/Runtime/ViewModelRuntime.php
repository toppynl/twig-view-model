<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Twig\Runtime;

use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\Exception\NoDataException;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class ViewModelRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ViewModelManagerInterface $manager,
    ) {}

    /**
     * Get view model data, returning null if no data exists.
     *
     * Force-resolves the Future immediately since Twig templates
     * use the data right away. Catches NoDataException and returns
     * null for template-level handling.
     *
     * @template T of object
     * @param array<string, mixed> $context Twig context (unused)
     * @param class-string<AsyncViewModel<T>> $class
     * @return T|null
     */
    public function view(array $context, string $class): ?object
    {
        try {
            $future = $this->manager->preloadWithFuture($class);
            return $future->await();
        } catch (NoDataException) {
            return null;
        }
        // ViewModelNotPreloadedException bubbles up (developer bug)
        // ViewModelResolutionException bubbles up (runtime error)
    }
}
