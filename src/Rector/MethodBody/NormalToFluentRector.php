<?php declare(strict_types=1);

namespace Rector\Rector\MethodBody;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Rector\NodeAnalyzer\MethodCallAnalyzer;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\ConfiguredCodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class NormalToFluentRector extends AbstractRector
{
    /**
     * @var string[]
     */
    private $fluentMethodsByType = [];

    /**
     * @var MethodCallAnalyzer
     */
    private $methodCallAnalyzer;

    /**
     * @var MethodCall[]
     */
    private $collectedMethodCalls = [];

    /**
     * @param string[] $fluentMethodsByType
     */
    public function __construct(array $fluentMethodsByType, MethodCallAnalyzer $methodCallAnalyzer)
    {
        $this->fluentMethodsByType = $fluentMethodsByType;
        $this->methodCallAnalyzer = $methodCallAnalyzer;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Turns fluent interface calls to classic ones.', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
$someObject = new SomeClass();
$someObject->someFunction();
$someObject->otherFunction();
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
$someObject = new SomeClass();
$someObject->someFunction()
    ->otherFunction();
CODE_SAMPLE
                ,
                [
                    '$fluentMethodsByType' => [
                        'SomeClass' => ['someFunction', 'otherFunction'],
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
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $classMethodNode
     */
    public function refactor(Node $classMethodNode): ?Node
    {
        // process only existing statements
        if ($classMethodNode->stmts === null) {
            return $classMethodNode;
        }

        $classMethodStatementCount = count($classMethodNode->stmts);

        // iterate from bottom to up, so we can merge
        for ($i = $classMethodStatementCount - 1; $i >= 0; --$i) {
            $stmt = $classMethodNode->stmts[$i];

            // we look only for 2+ stmts
            if (! isset($classMethodNode->stmts[$i - 1])) {
                continue;
            }

            // we look for 2 methods calls in a row
            if (! $stmt instanceof Expression) {
                continue;
            }

            $prevStmt = $classMethodNode->stmts[$i - 1];
            if (! $prevStmt instanceof Expression) {
                continue;
            }

            // here are 2 method calls statements in a row, while current one is first one
            if (! $this->isBothMethodCallMatch($stmt, $prevStmt)) {
                if (count($this->collectedMethodCalls) >= 2) {
                    $this->fluentizeCollectedMethodCalls($classMethodNode);
                }

                // reset for new type
                $this->collectedMethodCalls = [];

                continue;
            }

            // add all matching fluent calls
            $this->collectedMethodCalls[$i] = $stmt->expr;
            $this->collectedMethodCalls[$i - 1] = $prevStmt->expr;
        }

        // recount keys from 0, for needs of printer
        $classMethodNode->stmts = array_values($classMethodNode->stmts);

        return $classMethodNode;
    }

    private function matchMethodCall(MethodCall $methodCallNode): ?string
    {
        foreach ($this->fluentMethodsByType as $type => $methodNames) {
            if ($this->methodCallAnalyzer->isTypeAndMethods($methodCallNode, $type, $methodNames)) {
                return $type;
            }
        }

        return null;
    }

    private function isBothMethodCallMatch(Expression $firstStmt, Expression $secondStmt): bool
    {
        if (! $firstStmt->expr instanceof MethodCall) {
            return false;
        }

        if (! $secondStmt->expr instanceof MethodCall) {
            return false;
        }

        $firstMethodCallMatch = $this->matchMethodCall($firstStmt->expr);
        if ($firstMethodCallMatch === null) {
            return false;
        }

        $secondMethodCallMatch = $this->matchMethodCall($secondStmt->expr);
        if ($secondMethodCallMatch === null) {
            return false;
        }

        // is the same type
        return $firstMethodCallMatch === $secondMethodCallMatch;
    }

    private function fluentizeCollectedMethodCalls(ClassMethod $classMethodNode): void
    {
        $i = 0;
        $fluentMethodCallIndex = null;
        $methodCallsToAdd = [];
        foreach ($this->collectedMethodCalls as $statementIndex => $methodCall) {
            if ($i === 0) {
                // first method call, add it
                $fluentMethodCallIndex = $statementIndex;
            } else {
                $methodCallsToAdd[] = $methodCall;
                // next method calls, unset them
                unset($classMethodNode->stmts[$statementIndex]);
            }

            ++$i;
        }

        /** @var MethodCall $fluentMethodCall */
        $fluentMethodCall = $classMethodNode->stmts[$fluentMethodCallIndex]->expr;

        // they are added in reversed direction
        $methodCallsToAdd = array_reverse($methodCallsToAdd);

        foreach ($methodCallsToAdd as $methodCallToAdd) {
            $fluentMethodCall->var = new MethodCall( // make var a parent method call
                $fluentMethodCall->var,
                $methodCallToAdd->name,
                $methodCallToAdd->args
            );
        }
    }
}
