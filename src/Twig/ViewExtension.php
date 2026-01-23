<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Twig;

use Toppy\TwigViewModel\Twig\NodeVisitor\ViewDiscoveryVisitor;
use Toppy\TwigViewModel\Twig\Runtime\ViewModelRuntime;
use Twig\Extension\AbstractExtension;
use Twig\Loader\LoaderInterface;
use Twig\TwigFunction;

final class ViewExtension extends AbstractExtension
{
    public function __construct(
        private readonly LoaderInterface $loader,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('view', [ViewModelRuntime::class, 'view'], ['needs_context' => true]),
        ];
    }

    public function getNodeVisitors(): array
    {
        return [
            new ViewDiscoveryVisitor($this->loader),
        ];
    }
}
