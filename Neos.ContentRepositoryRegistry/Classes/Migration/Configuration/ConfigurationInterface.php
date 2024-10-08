<?php

declare(strict_types=1);

namespace Neos\ContentRepositoryRegistry\Migration\Configuration;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */


/**
 * Interface for Migration Configurations to allow different configuration sources.
 */
interface ConfigurationInterface
{
    /**
     * Returns all available versions.
     *
     * @return array<string, mixed>
     */
    public function getAvailableVersions();

    /**
     * Is the given version available?
     *
     * @param string $version
     * @return boolean
     */
    public function isVersionAvailable($version);

    /**
     * Returns the migration configuration with the given version.
     *
     * @param string|int $version
     * @return array<string, mixed>
     */
    public function getMigrationVersion($version);
}
