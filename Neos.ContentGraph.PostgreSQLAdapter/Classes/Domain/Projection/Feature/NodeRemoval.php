<?php
declare(strict_types=1);

namespace Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Feature;

/*
 * This file is part of the Neos.ContentGraph.PostgreSQLAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\NodeRelationAnchorPoint;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\ProjectionHypergraph;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWasRemoved;
use Neos\Flow\Annotations as Flow;

/**
 * The node removal feature set for the hypergraph projector
 */
trait NodeRemoval
{
    protected ProjectionHypergraph $projectionHypergraph;

    /**
     * @throws \Throwable
     */
    public function whenNodeAggregateWasRemoved(NodeAggregateWasRemoved $event): void
    {
        $this->transactional(function() use($event) {
            $nodeRecordsToBeRemoved = [];
            foreach ($event->getAffectedCoveredDimensionSpacePoints() as $dimensionSpacePoint) {
                $nodeAddress = new NodeAddress(
                    $event->getContentStreamIdentifier(),
                    $dimensionSpacePoint,
                    $event->getNodeAggregateIdentifier(),
                    null
                );
                $nodeRecord = $this->projectionHypergraph->findNodeRecordByAddress($nodeAddress);

                $ingoingHierarchyRelation = $this->projectionHypergraph->findHierarchyHyperrelationRecordByChildNodeAnchor(
                    $event->getContentStreamIdentifier(),
                    $dimensionSpacePoint,
                    $nodeRecord->relationAnchorPoint
                );
                $ingoingHierarchyRelation->removeChildNodeAnchor($nodeRecord->relationAnchorPoint, $this->getDatabaseConnection());

                if ($event->getAffectedOccupiedDimensionSpacePoints()->contains($nodeRecord->originDimensionSpacePoint)) {
                    $nodeRecordsToBeRemoved[$nodeRecord->originDimensionSpacePoint->getHash()] = $nodeRecord;
                }

                $this->cascadeHierarchy($event->getContentStreamIdentifier(), $dimensionSpacePoint, $nodeRecord->relationAnchorPoint);
            }
            foreach ($nodeRecordsToBeRemoved as $nodeRecord) {
                $nodeRecord->removeFromDatabase($this->getDatabaseConnection());
            }
        });
    }

    private function cascadeHierarchy(
        ContentStreamIdentifier $contentStreamIdentifier,
        DimensionSpacePoint $dimensionSpacePoint,
        NodeRelationAnchorPoint $nodeRelationAnchorPoint
    ): void {
        $childHierarchyRelation = $this->projectionHypergraph->findHierarchyHyperrelationRecordByParentNodeAnchor(
            $contentStreamIdentifier,
            $dimensionSpacePoint,
            $nodeRelationAnchorPoint
        );
        $childHierarchyRelation->removeFromDatabase($this->getDatabaseConnection());

        foreach ($childHierarchyRelation->childNodeAnchors as $childNodeAnchor) {
            $nodeRecord = $this->projectionHypergraph->findNodeRecordByRelationAnchorPoint($childNodeAnchor);
            $ingoingHierarchyRelations = $this->projectionHypergraph->findHierarchyHyperrelationRecordsByChildNodeAnchor($childNodeAnchor);
            if (empty($ingoingHierarchyRelations)) {
                $nodeRecord->removeFromDatabase($this->getDatabaseConnection());
            }
            $this->cascadeHierarchy($contentStreamIdentifier, $dimensionSpacePoint, $nodeRecord->relationAnchorPoint);
        }
    }

    /**
     * @throws \Throwable
     */
    abstract protected function transactional(callable $operations): void;

    abstract protected function getDatabaseConnection(): Connection;
}
