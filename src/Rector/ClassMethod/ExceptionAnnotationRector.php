<?php

declare(strict_types=1);

namespace Rector\PHPUnit\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Core\Rector\AbstractRector;
use Rector\PHPUnit\NodeAnalyzer\TestsNodeAnalyzer;
use Rector\PHPUnit\NodeFactory\ExpectExceptionMethodCallFactory;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see https://thephp.cc/news/2016/02/questioning-phpunit-best-practices
 * @see https://github.com/sebastianbergmann/phpunit/commit/17c09b33ac5d9cad1459ace0ae7b1f942d1e9afd
 *
 * @see \Rector\PHPUnit\Tests\Rector\ClassMethod\ExceptionAnnotationRector\ExceptionAnnotationRectorTest
 */
final class ExceptionAnnotationRector extends AbstractRector
{
    /**
     * In reversed order, which they should be called in code.
     *
     * @var array<string, string>
     */
    private const ANNOTATION_TO_METHOD = [
        'expectedExceptionMessageRegExp' => 'expectExceptionMessageRegExp',
        'expectedExceptionMessage' => 'expectExceptionMessage',
        'expectedExceptionCode' => 'expectExceptionCode',
        'expectedException' => 'expectException',
    ];

    public function __construct(
        private readonly ExpectExceptionMethodCallFactory $expectExceptionMethodCallFactory,
        private readonly PhpDocTagRemover $phpDocTagRemover,
        private readonly TestsNodeAnalyzer $testsNodeAnalyzer
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Changes `@expectedException annotations to `expectException*()` methods',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
/**
 * @expectedException Exception
 * @expectedExceptionMessage Message
 */
public function test()
{
    // tested code
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
public function test()
{
    $this->expectException('Exception');
    $this->expectExceptionMessage('Message');
    // tested code
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->testsNodeAnalyzer->isInTestClass($node)) {
            return null;
        }

        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($node);

        foreach (self::ANNOTATION_TO_METHOD as $annotationName => $methodName) {
            if (! $phpDocInfo->hasByName($annotationName)) {
                continue;
            }

            $methodCallExpressions = $this->expectExceptionMethodCallFactory->createFromTagValueNodes(
                $phpDocInfo->getTagsByName($annotationName),
                $methodName,
            );
            $node->stmts = array_merge($methodCallExpressions, (array) $node->stmts);

            $this->phpDocTagRemover->removeByName($phpDocInfo, $annotationName);
        }

        return $node;
    }
}
