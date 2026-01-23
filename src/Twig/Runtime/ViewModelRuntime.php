<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Twig\Runtime;

use Toppy\AsyncViewModel\AsyncViewModel;
use Toppy\AsyncViewModel\ViewModelManagerInterface;
use Twig\Extension\RuntimeExtensionInterface;

final class ViewModelRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ViewModelManagerInterface $manager,
    ) {}

    /**
     * Get view model data (blocks if not yet resolved).
     *
     * Returns the typed Data class for the given ViewModel.
     * IDE autocomplete works via PHPDoc generics.
     *
     * @template T of object
     * @param array<string, mixed> $context Twig context (unused)
     * @param class-string<AsyncViewModel<T>> $class
     * @return T
     */
    public function view(array $context, string $class): object
    {
        return $this->manager->get($class);
    }
}
