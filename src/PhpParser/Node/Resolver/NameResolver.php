<?php

declare(strict_types=1);

namespace Rector\PhpParser\Node\Resolver;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

final class NameResolver
{
    /**
     * @param string[] $names
     */
    public function isNames(Node $node, array $names): bool
    {
        foreach ($names as $name) {
            if ($this->isName($node, $name)) {
                return true;
            }
        }

        return false;
    }

    public function isName(Node $node, string $name): bool
    {
        if ($node instanceof MethodCall) {
            $debugBacktrace = debug_backtrace();

            $previousCaller = $debugBacktrace[0];
            $fileInfo = new SmartFileInfo($previousCaller['file']);
            $location = $fileInfo->getRelativeFilePathFromDirectory(getcwd()) . ':' . $previousCaller['line'];

            throw new ShouldNotHappenException(sprintf(
                'Cannot get name on "%s" node. Use $node->name instead. Called in: %s',
                MethodCall::class,
                $location
            ));
        }

        $resolvedName = $this->getName($node);
        if ($resolvedName === null) {
            return false;
        }

        if ($name === '') {
            return false;
        }

        // is probably regex pattern
        if (($name[0] === $name[strlen($name) - 1]) && ! ctype_alpha($name[0])) {
            return (bool) Strings::match($resolvedName, $name);
        }

        // is probably fnmatch
        if (Strings::contains($name, '*')) {
            return fnmatch($name, $resolvedName, FNM_NOESCAPE);
        }

        // special case
        if ($name === 'Object') {
            return $name === $resolvedName;
        }

        return strtolower($resolvedName) === strtolower($name);
    }

    public function getName(Node $node): ?string
    {
        if ($node instanceof Empty_) {
            return 'empty';
        }

        // more complex
        if ($node instanceof ClassConst) {
            if (count($node->consts) === 0) {
                return null;
            }

            return $this->getName($node->consts[0]);
        }

        if ($node instanceof Property) {
            if (count($node->props) === 0) {
                return null;
            }

            return $this->getName($node->props[0]);
        }

        if ($node instanceof Use_) {
            if (count($node->uses) === 0) {
                return null;
            }

            return $this->getName($node->uses[0]);
        }

        if ($node instanceof Param) {
            return $this->getName($node->var);
        }

        if ($node instanceof Name) {
            $resolvedName = $node->getAttribute(AttributeKey::RESOLVED_NAME);
            if ($resolvedName instanceof FullyQualified) {
                return $resolvedName->toString();
            }

            return $node->toString();
        }

        if ($node instanceof Class_) {
            if (isset($node->namespacedName)) {
                return $node->namespacedName->toString();
            }
            if ($node->name === null) {
                return null;
            }

            return $this->getName($node->name);
        }

        if ($node instanceof Interface_ || $node instanceof Trait_) {
            return $this->resolveNamespacedNameAwareNode($node);
        }

        if ($node instanceof ClassConstFetch) {
            $class = $this->getName($node->class);
            $name = $this->getName($node->name);

            if ($class === null || $name === null) {
                return null;
            }

            return $class . '::' . $name;
        }

        if (! property_exists($node, 'name')) {
            return null;
        }

        // unable to resolve
        if ($node->name instanceof Expr) {
            return null;
        }

        if ($node instanceof Variable) {
            $parentNode = $node->getAttribute(AttributeKey::PARENT_NODE);
            // is $variable::method(), unable to resolve $variable->class name
            if ($parentNode instanceof StaticCall) {
                return null;
            }

            // skip $some->$dynamicMethodName()
            if ($parentNode instanceof MethodCall) {
                if ($node === $parentNode->name) {
                    return null;
                }
            }

            // skip $some->$dynamicPropertyName
            if ($parentNode instanceof PropertyFetch) {
                if ($node === $parentNode->name) {
                    return null;
                }
            }
        }

        return (string) $node->name;
    }

    public function areNamesEqual(Node $firstNode, Node $secondNode): bool
    {
        return $this->getName($firstNode) === $this->getName($secondNode);
    }

    /**
     * @param Interface_|Trait_ $classLike
     */
    private function resolveNamespacedNameAwareNode(ClassLike $classLike): ?string
    {
        if (isset($classLike->namespacedName)) {
            return $classLike->namespacedName->toString();
        }

        if ($classLike->name === null) {
            return null;
        }

        return $this->getName($classLike->name);
    }
}
