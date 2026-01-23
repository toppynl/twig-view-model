<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Bundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Toppy\TwigViewModel\Bundle\DependencyInjection\ToppyTwigViewModelExtension;

/**
 * Bundle providing Twig view() and pre_load_view() functions.
 *
 * This is a lightweight bundle that only registers view-related services.
 * For full async Twig rendering stack, use ToppySymfonyAsyncTwigBundle instead.
 */
class ToppyTwigViewModelBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new ToppyTwigViewModelExtension();
    }
}
