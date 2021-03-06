<?php declare(strict_types=1);

namespace Rector\Twig\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Rector\Utils\NodeTraverser\CallableNodeTraverser;

/**
 * Covers https://twig.symfony.com/doc/1.x/deprecated.html#function
 */
final class SimpleFunctionAndFilterRector extends AbstractRector
{
    /**
     * @var string
     */
    private $twigExtensionClass;

    /**
     * @var NodeTypeResolver
     */
    private $nodeTypeResolver;

    /**
     * @var CallableNodeTraverser
     */
    private $callableNodeTraverser;

    /**
     * @var string[]
     */
    private $oldToNewClasses = [];

    /**
     * @param string[] $oldToNewClasses
     */
    public function __construct(
        NodeTypeResolver $nodeTypeResolver,
        CallableNodeTraverser $callableNodeTraverser,
        string $twigExtensionClass = 'Twig_Extension',
        array $oldToNewClasses = [
            'Twig_Function_Method' => 'Twig_SimpleFunction',
            'Twig_Filter_Method' => 'Twig_SimpleFilter',
        ]
    ) {
        $this->twigExtensionClass = $twigExtensionClass;
        $this->nodeTypeResolver = $nodeTypeResolver;
        $this->callableNodeTraverser = $callableNodeTraverser;
        $this->oldToNewClasses = $oldToNewClasses;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Changes Twig_Function_Method to Twig_SimpleFunction calls in TwigExtension.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class SomeExtension extends Twig_Extension
{
    public function getFunctions()
    {
        return [
            'is_mobile' => new Twig_Function_Method($this, 'isMobile'),
        ];
    }

    public function getFilters()
    {
        return [
            'is_mobile' => new Twig_Filter_Method($this, 'isMobile'),
        ];
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
class SomeExtension extends Twig_Extension
{
    public function getFunctions()
    {
        return [
             new Twig_SimpleFunction('is_mobile', [$this, 'isMobile']),
        ];
    }

    public function getFilteres()
    {
        return [
             new Twig_SimpleFilter('is_mobile', [$this, 'isMobile']),
        ];
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Return_::class];
    }

    /**
     * @param Return_ $returnNode
     */
    public function refactor(Node $returnNode): ?Node
    {
        $classNode = $returnNode->getAttribute(Attribute::CLASS_NODE);
        $classNodeTypes = $this->nodeTypeResolver->resolve($classNode);

        if (! in_array($this->twigExtensionClass, $classNodeTypes, true)) {
            return null;
        }

        $methodName = $returnNode->getAttribute(Attribute::METHOD_NAME);

        if (! in_array($methodName, ['getFunctions', 'getFilters'], true)) {
            return null;
        }

        $this->callableNodeTraverser->traverseNodesWithCallable([$returnNode->expr], function (Node $node) {
            if (! $node instanceof ArrayItem) {
                return null;
            }

            if (! $node->value instanceof New_) {
                return null;
            }

            $newNodeTypes = $this->nodeTypeResolver->resolve($node->value);

            return $this->processArrayItem($node, $newNodeTypes);
        });

        return $returnNode;
    }

    /**
     * @param string[] $newNodeTypes
     */
    private function processArrayItem(ArrayItem $node, array $newNodeTypes): ?Node
    {
        $matchedOldClasses = array_intersect(array_keys($this->oldToNewClasses), $newNodeTypes);
        if (! $matchedOldClasses) {
            return null;
        }

        $matchedOldClass = array_pop($matchedOldClasses);
        $matchedNewClass = $this->oldToNewClasses[$matchedOldClass];

        if (! $node->key instanceof String_) {
            return null;
        }

        if (! $node->value instanceof New_) {
            return null;
        }

        // match!
        $filterName = $node->key->value;

        $node->key = null;
        $node->value->class = new FullyQualified($matchedNewClass);

        $oldArguments = $node->value->args;

        if ($oldArguments[0]->value instanceof Array_) {
            // already array, just shift it
            $node->value->args = array_merge([new Arg(new String_($filterName))], $oldArguments);
        } else {
            // not array yet, wrap to one
            $arrayItems = [];
            foreach ($oldArguments as $oldArgument) {
                $arrayItems[] = new ArrayItem($oldArgument->value);
            }

            $node->value->args[0] = new Arg(new String_($filterName));
            $node->value->args[1] = new Arg(new Array_($arrayItems, [
                'kind' => Array_::KIND_SHORT,
            ]));
        }

        return $node;
    }
}
