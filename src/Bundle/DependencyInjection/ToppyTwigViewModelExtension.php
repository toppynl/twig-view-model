<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Bundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Toppy\TwigViewModel\Twig\Runtime\ViewModelRuntime;
use Toppy\TwigViewModel\Twig\ViewExtension;

/**
 * Extension for ToppyTwigViewModelBundle.
 *
 * Registers only view-related services:
 * - ViewExtension (provides view() function and ViewDiscoveryVisitor)
 * - ViewModelRuntime (Twig runtime for view() function)
 *
 * Core services (ViewModelManager, Context, Profilers) are registered
 * by ToppyAsyncViewModelBundle and ToppySymfonyAsyncTwigBundle.
 */
class ToppyTwigViewModelExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Register ViewExtension with twig.extension tag
        $container->setDefinition(ViewExtension::class, new Definition(ViewExtension::class))
            ->setAutowired(true)
            ->setArgument('$loader', new Reference('twig.loader.native_filesystem'))
            ->addTag('twig.extension');

        // Register ViewModelRuntime with twig.runtime tag
        $container->setDefinition(ViewModelRuntime::class, new Definition(ViewModelRuntime::class))
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('twig.runtime');
    }
}
