<?php

declare(strict_types=1);

namespace Ssch\TYPO3Rector\TYPO312\v0;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;
use PHPStan\Type\ObjectType;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use Ssch\TYPO3Rector\ComposerExtensionKeyResolver;
use Ssch\TYPO3Rector\Contract\FilesystemInterface;
use Ssch\TYPO3Rector\Filesystem\FilesFinder;
use Ssch\TYPO3Rector\Helper\ExtensionKeyResolverTrait;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @changelog https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Feature-96733-NewBackendModuleRegistrationAPI.html
 * @see \Ssch\TYPO3Rector\Tests\Rector\v12\v0\MigrateBackendModuleRegistrationRector\MigrateBackendModuleRegistrationRectorTest
 */
final class MigrateBackendModuleRegistrationRector extends AbstractRector
{
    use ExtensionKeyResolverTrait;

    /**
     * @readonly
     */
    private FilesFinder $filesFinder;

    /**
     * @readonly
     */
    private FilesystemInterface $filesystem;

    /**
     * @readonly
     */
    private ValueResolver $valueResolver;

    public function __construct(
        FilesFinder $filesFinder,
        FilesystemInterface $filesystem,
        ValueResolver $valueResolver,
        ComposerExtensionKeyResolver $composerExtensionKeyResolver
    ) {
        $this->filesFinder = $filesFinder;
        $this->filesystem = $filesystem;
        $this->valueResolver = $valueResolver;
        $this->composerExtensionKeyResolver = $composerExtensionKeyResolver;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Migrate Backend Module Registration', [new CodeSample(
            <<<'CODE_SAMPLE'
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
    'web',
    'example',
    'top',
    '',
    [
        'routeTarget' => MyExampleModuleController::class . '::handleRequest',
        'name' => 'web_example',
        'access' => 'admin',
        'workspaces' => 'online',
        'iconIdentifier' => 'module-example',
        'labels' => 'LLL:EXT:example/Resources/Private/Language/locallang_mod.xlf',
        'navigationComponentId' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
    ]
);
CODE_SAMPLE
            ,
            <<<'CODE_SAMPLE'
// Configuration/Backend/Modules.php
return [
    'web_module' => [
        'parent' => 'web',
        'position' => ['before' => '*'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/web/example',
        'iconIdentifier' => 'module-example',
        'navigationComponent' => 'TYPO3/CMS/Backend/PageTree/PageTreeElement',
        'labels' => 'LLL:EXT:example/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => MyExampleModuleController::class . '::handleRequest',
            ],
        ],
    ],
];
CODE_SAMPLE
        )]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    /**
     * @param Expression $node
     */
    public function refactor(Node $node): ?int
    {
        $staticMethodCall = $node->expr;

        if (! $staticMethodCall instanceof StaticCall) {
            return null;
        }

        if ($this->shouldSkip($staticMethodCall)) {
            return null;
        }

        $directoryName = dirname($this->file->getFilePath());

        $content = 'TODO';

        $newConfigurationFile = $directoryName . '/Configuration/Backend/Modules.php';
        if ($this->filesystem->fileExists($newConfigurationFile)) {
            $this->filesystem->appendToFile($newConfigurationFile, $content . PHP_EOL);
        } else {
            $this->filesystem->write($newConfigurationFile, <<<CODE
{$content}

CODE
            );
        }

        return NodeTraverser::REMOVE_NODE;
    }

    private function shouldSkip(StaticCall $staticMethodCall): bool
    {
        if (! $this->nodeTypeResolver->isMethodStaticCallOrClassMethodObjectType(
            $staticMethodCall,
            new ObjectType('TYPO3\CMS\Core\Utility\ExtensionManagementUtility')
        )) {
            return true;
        }

        if (! $this->isName($staticMethodCall->name, 'addModule')) {
            return true;
        }

        return ! $this->filesFinder->isExtTables($this->file->getFilePath());
    }
}
