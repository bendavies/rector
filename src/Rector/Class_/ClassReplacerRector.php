<?php declare(strict_types=1);

namespace Rector\Rector\Class_;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Use_;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\ConfiguredCodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * @todo fix
 */
final class ClassReplacerRector extends AbstractRector
{
    /**
     * @var string[]
     */
    private $oldToNewClasses = [];

    /**
     * @param string[] $oldToNewClasses
     */
    public function __construct(array $oldToNewClasses)
    {
        $this->oldToNewClasses = $oldToNewClasses;
    }

    /**
     * @todo complete list with all possibilities
     */
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Replaces defined classes by new ones.', [
            new ConfiguredCodeSample(
                '$value = new SomeOldClass;',
                '$value = new SomeNewClass;',
                [
                    '$oldToNewClasses' => [
                        'SomeOldClass' => 'SomeNewClass',
                    ],
                ]
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Name::class];
    }

    /**
     * @param Name $nameNode
     */
    public function refactor(Node $nameNode): ?Node
    {
        $resolvedNameNode = $this->resolveNameNodeFromNode($nameNode);
        if ($resolvedNameNode === null) {
            return null;
        }

        $newName = $this->oldToNewClasses[$resolvedNameNode->toString()] ?? null;
        if ($newName) {
            return new FullyQualified($newName);
        }

        return null;
    }

    private function resolveNameNodeFromNode(Node $node): ?Name
    {
        // @todo use NodeTypeResolver?
        if ($node instanceof Name) {
            // resolved name has priority, as it is FQN
            $resolvedName = $node->getAttribute(Attribute::RESOLVED_NAME);
            if ($resolvedName instanceof FullyQualified) {
                return $resolvedName;
            }

            return $node;
        }

        if ($node instanceof Use_) {
            return $node->uses[0]->name;
        }

        return null;
    }
}
