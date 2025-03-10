<?php

declare(strict_types=1);

namespace Frosh\Rector\Rule\v65;

use function array_map;
use function in_array;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPStan\Type\ObjectType;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class AddBanAllToReverseProxyRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Adds banAll method to reverse proxy', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class Test extends \Shopware\Core\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway {

}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class Test extends \Shopware\Core\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway {
    public function banAll(): void
    {
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    public function getNodeTypes(): array
    {
        return [
            Node\Stmt\Class_::class,
        ];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node)
    {
        if (!$this->isObjectType($node, new ObjectType('Shopware\\Storefront\\Framework\\Cache\\ReverseProxy\\AbstractReverseProxyGateway'))) {
            return null;
        }

        $nodeFinder = new NodeFinder();
        $methodNames = array_map(static function (Node\Stmt\ClassMethod $method) {
            return (string) $method->name;
        }, $nodeFinder->findInstanceOf([$node], Node\Stmt\ClassMethod::class));

        if (in_array('banAll', $methodNames, true)) {
            return null;
        }

        $builderFactory = new BuilderFactory();
        $node->stmts[] = ($builderFactory)
            ->method('banAll')
            ->makePublic()
            ->addStmt($builderFactory->methodCall($builderFactory->var('this'), 'ban', $builderFactory->args([new Node\Expr\Array_([new Node\Scalar\String_('/')])])))
            ->getNode();

        return $node;
    }
}
