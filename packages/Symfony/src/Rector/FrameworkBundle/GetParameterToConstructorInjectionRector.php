<?php declare(strict_types=1);

namespace Rector\Symfony\Rector\FrameworkBundle;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use Rector\Builder\Class_\ClassPropertyCollector;
use Rector\Naming\PropertyNaming;
use Rector\Node\PropertyFetchNodeFactory;
use Rector\NodeAnalyzer\MethodCallAnalyzer;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class GetParameterToConstructorInjectionRector extends AbstractRector
{
    /**
     * @var PropertyNaming
     */
    private $propertyNaming;

    /**
     * @var ClassPropertyCollector
     */
    private $classPropertyCollector;

    /**
     * @var PropertyFetchNodeFactory
     */
    private $propertyFetchNodeFactory;

    /**
     * @var MethodCallAnalyzer
     */
    private $methodCallAnalyzer;

    /**
     * @var string
     */
    private $controllerClass;

    public function __construct(
        PropertyNaming $propertyNaming,
        ClassPropertyCollector $classPropertyCollector,
        PropertyFetchNodeFactory $propertyFetchNodeFactory,
        MethodCallAnalyzer $methodCallAnalyzer,
        string $controllerClass = 'Symfony\Bundle\FrameworkBundle\Controller\Controller'
    ) {
        $this->propertyNaming = $propertyNaming;
        $this->classPropertyCollector = $classPropertyCollector;
        $this->propertyFetchNodeFactory = $propertyFetchNodeFactory;
        $this->methodCallAnalyzer = $methodCallAnalyzer;
        $this->controllerClass = $controllerClass;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns fetching of parameters via `getParameter()` in ContainerAware to constructor injection in Command and Controller in Symfony',
            [
                new CodeSample(
<<<'CODE_SAMPLE'
class MyCommand extends ContainerAwareCommand
{
    public function someMethod()
    {
        $this->getParameter('someParameter');
    }
}
CODE_SAMPLE
                    ,
<<<'CODE_SAMPLE'
class MyCommand extends Command
{
    private $someParameter;

    public function __construct($someParameter)
    {
        $this->someParameter = $someParameter;
    }

    public function someMethod()
    {
        $this->someParameter;
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
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $methodCallNode
     */
    public function refactor(Node $methodCallNode): ?Node
    {
        if ($this->methodCallAnalyzer->isTypeAndMethod(
            $methodCallNode,
            $this->controllerClass,
            'getParameter'
        ) === false) {
            return null;
        }
        /** @var String_ $stringArgument */
        $stringArgument = $methodCallNode->args[0]->value;
        $parameterName = $stringArgument->value;
        $propertyName = $this->propertyNaming->underscoreToName($parameterName);

        $this->classPropertyCollector->addPropertyForClass(
            (string) $methodCallNode->getAttribute(Attribute::CLASS_NAME),
            ['string'], // @todo: resolve type from container provider? see parameter autowire compiler pass
            $propertyName
        );

        return $this->propertyFetchNodeFactory->createLocalWithPropertyName($propertyName);
    }
}
