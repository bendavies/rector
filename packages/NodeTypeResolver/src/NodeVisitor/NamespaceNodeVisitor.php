<?php declare(strict_types=1);

namespace Rector\NodeTypeResolver\NodeVisitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\Utils\BetterNodeFinder;

final class NamespaceNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var string|null
     */
    private $namespaceName;

    /**
     * @var Namespace_|null
     */
    private $namespaceNode;

    /**
     * @var BetterNodeFinder
     */
    private $betterNodeFinder;

    /**
     * @var Use_[]
     */
    private $useNodes = [];

    public function __construct(BetterNodeFinder $betterNodeFinder)
    {
        $this->betterNodeFinder = $betterNodeFinder;
    }

    /**
     * @param Node[] $nodes
     * @return Node[]|null
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->namespaceName = null;
        $this->namespaceNode = null;
        $this->useNodes = [];

        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            $this->namespaceName = $node->name ? $node->name->toString() : null;
            $this->namespaceNode = $node;
            $this->useNodes = $this->betterNodeFinder->findInstanceOf($node, Use_::class);
        }

        $node->setAttribute(Attribute::NAMESPACE_NAME, $this->namespaceName);
        $node->setAttribute(Attribute::NAMESPACE_NODE, $this->namespaceNode);
        $node->setAttribute(Attribute::USE_NODES, $this->useNodes);

        return $node;
    }
}
