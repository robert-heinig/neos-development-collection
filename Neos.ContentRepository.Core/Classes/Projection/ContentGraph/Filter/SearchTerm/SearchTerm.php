<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Projection\ContentGraph\Filter\SearchTerm;

/**
 * A search term for use in Filters for the {@see ContentSubgraphInterface} API.
 *
 * The search is defined the following:
 * - test all properties if one contains the term
 * - the term is checked case-insensitive
 * - an empty term will lead to no filtering
 * - FIXME: define the search behaviour across non-string-typed properties
 *
 * @api DTO for {@see ContentSubgraphInterface}
 */
final readonly class SearchTerm
{
    private function __construct(public string $term)
    {
    }

    /**
     * Create a new Fulltext search term (i.e. search across all properties)
     */
    public static function fulltext(string $term): self
    {
        return new self($term);
    }
}
