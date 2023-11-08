<?php

/**
 * Db service helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use Laminas\View\Helper\AbstractHelper;
use VuFind\Db\Service\PluginManager as PluginManager;

/**
 * Db service helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DbService extends AbstractHelper
{
    /**
     * Database service plugin manager
     *
     * @var \VuFind\Db\Service\PluginManager
     */
    protected $serviceManager;

    /**
     * Constructor
     *
     * @param PluginManager $pm Db service plugin manager
     */
    public function __construct(PluginManager $pm)
    {
        $this->serviceManager = $pm;
    }

    /**
     * Get a database service object.
     *
     * @param string $name Name of service to retrieve
     *
     * @return \VuFind\Db\Service\AbstractService
     */
    public function getDbService(string $name)
    {
        return $this->serviceManager->get($name);
    }
}
