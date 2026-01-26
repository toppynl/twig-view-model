<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Twig\NodeVisitor;

use Toppy\TwigViewModel\Twig\Node\DoPreloadMethodNode;
use Twig\Environment;
use Twig\Loader\LoaderInterface;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\IncludeNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\Node\Nodes;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Discovers all view() function calls at compile-time and injects a doPreload() method.
 *
 * The visitor:
 * 1. Scans for view('ClassName') calls in the AST
 * 2. Validates that classes exist and implement AsyncViewModel
 * 3. Recursively scans static includes
 * 4. Injects a DoPreloadMethodNode into ModuleNode's class_end slot
 */
final class ViewDiscoveryVisitor implements NodeVisitorInterface
{
    /** @var array<string, true> */
    private array $visitedTemplates = [];

    /** @var list<string> */
    private array $discoveredViewModels = [];

    public function __construct(
        private readonly LoaderInterface $loader,
    ) {}

    public function enterNode(Node $node, Environment $env): Node
    {
        // Track which template we're in to prevent circular includes
        if ($node instanceof ModuleNode) {
            $templateName = $node->getSourceContext()?->getName() ?? '';
            if ($templateName !== '' && !isset($this->visitedTemplates[$templateName])) {
                $this->visitedTemplates[$templateName] = true;
            }
        }

        // Discover view() function calls
        if ($node instanceof FunctionExpression && $node->getAttribute('name') === 'view') {
            $arguments = $node->getNode('arguments');
            if ($arguments->hasNode('0')) {
                $firstArg = $arguments->getNode('0');
                if ($firstArg instanceof ConstantExpression) {
                    $className = $firstArg->getAttribute('value');

                    if (is_string($className)) {
                        // Compile-time validation: class must exist
                        if (!class_exists($className)) {
                            throw new \Twig\Error\SyntaxError(
                                sprintf('View model class "%s" does not exist.', $className),
                                $node->getTemplateLine(),
                                $node->getSourceContext(),
                            );
                        }

                        // Compile-time validation: must implement interface
                        if (!is_a($className, \Toppy\AsyncViewModel\AsyncViewModel::class, true)) {
                            throw new \Twig\Error\SyntaxError(
                                sprintf('Class "%s" must implement AsyncViewModel.', $className),
                                $node->getTemplateLine(),
                                $node->getSourceContext(),
                            );
                        }

                        if (!in_array($className, $this->discoveredViewModels, true)) {
                            $this->discoveredViewModels[] = $className;
                        }
                    }
                }
            }
        }

        // Recursively scan static includes
        if ($node instanceof IncludeNode) {
            $this->scanIncludeNode($node, $env);
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        // At the end of the module, inject the DoPreloadMethodNode
        if ($node instanceof ModuleNode) {
            // Store discovered models as attribute for testing
            $node->setAttribute('discovered_view_models', $this->discoveredViewModels);

            // Only inject if we found view models
            if (!empty($this->discoveredViewModels)) {
                // Create the method node
                $methodNode = new DoPreloadMethodNode($this->discoveredViewModels);

                // Inject into class_end slot (compiled at class level, before closing brace)
                $existingClassEnd = $node->getNode('class_end');
                $node->setNode('class_end', new Nodes([$existingClassEnd, $methodNode]));
            }

            // Reset state for next template (important for embedded templates)
            $this->visitedTemplates = [];
            $this->discoveredViewModels = [];
        }

        return $node;
    }

    public function getPriority(): int
    {
        // Run after standard visitors but before optimizer
        return 0;
    }

    private function scanIncludeNode(IncludeNode $node, Environment $env): void
    {
        $expr = $node->getNode('expr');

        // Only handle static template paths
        if (!$expr instanceof ConstantExpression) {
            return;
        }

        $templateName = $expr->getAttribute('value');
        if (!is_string($templateName)) {
            return;
        }

        // Prevent circular includes
        if (isset($this->visitedTemplates[$templateName])) {
            return;
        }

        $this->visitedTemplates[$templateName] = true;

        try {
            $source = $this->loader->getSourceContext($templateName);
            $stream = $env->tokenize($source);
            $childModule = $env->parse($stream);

            // Merge discovered view models from child
            $childViewModels = $childModule->getAttribute('discovered_view_models') ?? [];
            foreach ($childViewModels as $className) {
                if (!in_array($className, $this->discoveredViewModels, true)) {
                    $this->discoveredViewModels[] = $className;
                }
            }
        } catch (\Throwable) {
            // Template doesn't exist or has errors - skip
        }
    }
}
