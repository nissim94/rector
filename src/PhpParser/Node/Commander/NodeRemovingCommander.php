<?php

declare(strict_types=1);

namespace Rector\PhpParser\Node\Commander;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeTraverser;
use Rector\Contract\PhpParser\Node\CommanderInterface;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\NodeVisitorFactory\NodeRemovingNodeVisitorFactory;
use Rector\Rector\AffectedFilesCollector;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

final class NodeRemovingCommander implements CommanderInterface
{
    /**
     * @var Stmt[]
     */
    private $nodesToRemove = [];

    /**
     * @var NodeRemovingNodeVisitorFactory
     */
    private $nodeRemovingNodeVisitorFactory;

    /**
     * @var AffectedFilesCollector
     */
    private $affectedFilesCollector;

    public function __construct(
        NodeRemovingNodeVisitorFactory $nodeRemovingNodeVisitorFactory,
        AffectedFilesCollector $affectedFilesCollector
    ) {
        $this->nodeRemovingNodeVisitorFactory = $nodeRemovingNodeVisitorFactory;
        $this->affectedFilesCollector = $affectedFilesCollector;
    }

    public function addNode(Node $node): void
    {
        // chain call: "->method()->another()"
        $this->ensureIsNotPartOfChainMethodCall($node);

        $parentNode = $node->getAttribute(AttributeKey::PARENT_NODE);
        if (! $node instanceof Expression && $parentNode instanceof Expression) {
            // only expressions can be removed
            $node = $parentNode;
        }

        /** @var SmartFileInfo|null $fileInfo */
        $fileInfo = $node->getAttribute(AttributeKey::FILE_INFO);
        if ($fileInfo !== null) {
            $this->affectedFilesCollector->addFile($fileInfo);
        }

        /** @var Stmt $node */
        $this->nodesToRemove[] = $node;
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function traverseNodes(array $nodes): array
    {
        $nodeTraverser = new NodeTraverser();

        $nodeRemovingNodeVisitor = $this->nodeRemovingNodeVisitorFactory->createFromNodesToRemove($this->nodesToRemove);
        $nodeTraverser->addVisitor($nodeRemovingNodeVisitor);

        return $nodeTraverser->traverse($nodes);
    }

    public function isNodeRemoved(Node $node): bool
    {
        return in_array($node, $this->nodesToRemove, true);
    }

    public function isActive(): bool
    {
        return $this->getCount() > 0;
    }

    public function getCount(): int
    {
        return count($this->nodesToRemove);
    }

    /**
     * @return Node[]
     */
    public function getNodesToRemove(): array
    {
        return $this->nodesToRemove;
    }

    public function getPriority(): int
    {
        return 800;
    }

    private function ensureIsNotPartOfChainMethodCall(Node $node): void
    {
        if (! $node instanceof MethodCall) {
            return;
        }

        if (! $node->var instanceof MethodCall) {
            return;
        }

        throw new ShouldNotHappenException(
            'Chain method calls cannot be removed this way. It would remove the whole tree of calls. Remove them manually by creating new parent node with no following method.'
        );
    }
}
