<?php

declare(strict_types=1);

namespace Frosh\Rector\Rule\v65;

use PhpParser\Builder\Class_;
use PhpParser\Node;
use Rector\BetterPhpDocParser\PhpDoc\ArrayItemNode;
use Rector\BetterPhpDocParser\PhpDoc\SpacelessPhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\BetterPhpDocParser\ValueObject\PhpDoc\DoctrineAnnotation\CurlyListNode;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

class MigrateCaptchaAnnotationToRouteRector extends AbstractRector
{
    protected PhpDocTagRemover $phpDocTagRemover;

    public function __construct(PhpDocTagRemover $phpDocTagRemover)
    {
        $this->phpDocTagRemover = $phpDocTagRemover;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('NAME', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class Foo
{
    /**
     * @Route("/form/contact", name="frontend.form.contact.send", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     * @Captcha
     */
    public function sendContactForm()
    {
    }
}
CODE_SAMPLE
                ,
                <<<'PHP'
class Foo
{
    /**
     * @Route("/form/contact", name="frontend.form.contact.send", methods={"POST"}, defaults={"XmlHttpRequest"=true, "_captcha"=true})
     */
    public function sendContactForm(): Response
    {
    }
}
PHP
            ),
        ]);
    }

    public function getNodeTypes(): array
    {
        return [
            Node\Stmt\ClassMethod::class,
            Node\Stmt\Class_::class,
        ];
    }

    /**
     * @param Node\Stmt\ClassMethod|Class_ $node
     * @return null
     */
    public function refactor(Node $node)
    {
        /** @var PhpDocInfo $phpDocInfo */
        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);

        $captchaAnnotation = $phpDocInfo->getByName('Captcha');

        if ($captchaAnnotation === null) {
            return null;
        }

        /** @var SpacelessPhpDocTagNode|null $route */
        $route = $phpDocInfo->getByName('Route');

        if ($route === null) {
            return null;
        }

        if (!$route->value->getValue('defaults')) {
            $route->value->values[] = new ArrayItemNode(new CurlyListNode([]), 'defaults');
        }

        /** @var CurlyListNode $list */
        $list = $route->value->getValue('defaults')->value;

        /** @var ArrayItemNode $item */
        foreach ($list->values as $item) {
            if ($item->key === '_captcha') {
                return null;
            }
        }

        $list->values[] = new ArrayItemNode('true', '_captcha', null, Node\Scalar\String_::KIND_DOUBLE_QUOTED);
        $list->markAsChanged();

        $this->phpDocTagRemover->removeByName($phpDocInfo, 'Captcha');

        return $node;
    }
}
