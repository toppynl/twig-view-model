<?php

declare(strict_types=1);

namespace Toppy\TwigViewModel\Twig\Node;

use Twig\Attribute\YieldReady;
use Twig\Compiler;
use Twig\Node\Node;

/**
 * Generates a doPreload() method that returns all discovered view model classes.
 *
 * This node is injected into ModuleNode's 'class_end' slot by ViewDiscoveryVisitor.
 *
 * The generated method:
 * 1. Returns ViewModels discovered in this template
 * 2. Chains to parent template's doPreload() if it exists
 * 3. Deduplicates the merged results
 *
 * This enables full template hierarchy preloading: if child.html.twig extends
 * base.html.twig, calling child's doPreload() returns ViewModels from both.
 */
#[YieldReady]
final class DoPreloadMethodNode extends Node
{
    /**
     * @param list<string> $viewModelClasses
     */
    public function __construct(array $viewModelClasses, int $lineno = 0)
    {
        parent::__construct([], ['classes' => $viewModelClasses], $lineno);
    }

    /**
     * @throws \LogicException When the 'classes' attribute is not set
     */
    #[\Override]
    public function compile(Compiler $compiler): void
    {
        /** @var list<string> $classes */
        $classes = $this->getAttribute('classes');

        $compiler
            ->write("\n")
            ->write("/**\n")
            ->write(" * Returns all view model classes discovered at compile-time.\n")
            ->write(" * Chains to parent template's doPreload() if it exists.\n")
            ->write(" *\n")
            ->write(" * @return list<class-string>\n")
            ->write(" */\n")
            ->write("public function doPreload(): array\n")
            ->write("{\n")
            ->indent()
            ->write('$classes = ')
            ->repr($classes)
            ->raw(";\n\n")
            ->write("// Chain to parent template if exists\n")
            ->write("\$parentName = \$this->doGetParent([]);\n")
            ->write("if (\$parentName !== false) {\n")
            ->indent()
            ->write("\$parent = \$this->load(\$parentName, 0)->unwrap();\n")
            ->write("if (method_exists(\$parent, 'doPreload')) {\n")
            ->indent()
            ->write("\$classes = array_merge(\$parent->doPreload(), \$classes);\n")
            ->outdent()
            ->write("}\n")
            ->outdent()
            ->write("}\n\n")
            ->write("return array_values(array_unique(\$classes));\n")
            ->outdent()
            ->write("}\n");
    }
}
