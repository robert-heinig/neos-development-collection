<?php

declare(strict_types=1);

namespace Neos\ContentRepository\NodeAccess\FlowQueryOperations;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindPrecedingSiblingNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\Nodes;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Eel\FlowQuery\Operations\AbstractOperation;
use Neos\Flow\Annotations as Flow;

/**
 * "prevUntil" operation working on ContentRepository nodes. It iterates over all context elements
 * and returns each preceding sibling until the matching sibling is found.
 * If an optional filter expression is provided as a second argument,
 * it only returns the nodes matching the given expression.
 */
class PrevUntilOperation extends AbstractOperation
{
    /**
     * {@inheritdoc}
     *
     * @var string
     */
    protected static $shortName = 'prevUntil';

    /**
     * {@inheritdoc}
     *
     * @var integer
     */
    protected static $priority = 0;

    /**
     * @Flow\Inject
     * @var ContentRepositoryRegistry
     */
    protected $contentRepositoryRegistry;

    /**
     * {@inheritdoc}
     *
     * @param array<int,mixed> $context (or array-like object) onto which this operation should be applied
     * @return boolean true if the operation can be applied onto the $context, false otherwise
     */
    public function canEvaluate($context)
    {
        return count($context) === 0 || (isset($context[0]) && ($context[0] instanceof Node));
    }

    /**
     * {@inheritdoc}
     *
     * @param FlowQuery<int,mixed> $flowQuery the FlowQuery object
     * @param array<int,mixed> $arguments the arguments for this operation
     * @throws \Neos\Eel\Exception
     */
    public function evaluate(FlowQuery $flowQuery, array $arguments): void
    {
        $output = [];
        $outputNodeIdentifiers = [];

        /** @var Node $contextNode */
        foreach ($flowQuery->getContext() as $contextNode) {
            $prevNodes = $this->contentRepositoryRegistry->subgraphForNode($contextNode)
                ->findPrecedingSiblingNodes(
                    $contextNode->aggregateId,
                    FindPrecedingSiblingNodesFilter::create()
                );
            if (isset($arguments[0]) && !empty($arguments[0])) {
                $untilQuery = new FlowQuery($prevNodes);
                $untilQuery->pushOperation('filter', [$arguments[0]]);
                $untilNodes = Nodes::fromArray(iterator_to_array($untilQuery));
            }
            if (isset($untilNodes) && !$untilNodes->isEmpty()) {
                $prevNodes = $prevNodes->previousAll($untilNodes->first())->reverse();
            }
            foreach ($prevNodes as $prevNode) {
                if ($prevNode !== null &&
                    !isset($outputNodeIdentifiers[$prevNode->aggregateId->value])) {
                    $outputNodeIdentifiers[$prevNode->aggregateId->value] = true;
                    $output[] = $prevNode;
                }
            }
        }

        $flowQuery->setContext($output);

        if (isset($arguments[1]) && !empty($arguments[1])) {
            $flowQuery->pushOperation('filter', [$arguments[1]]);
        }
    }
}
