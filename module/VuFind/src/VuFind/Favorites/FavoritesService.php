<?php

/**
 * Favorites service
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Favorites;

use VuFind\Db\Service\ServiceAwareInterface;
use VuFind\Db\Service\ServiceAwareTrait;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Record\Cache as RecordCache;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

/**
 * Favorites service
 *
 * @category VuFind
 * @package  Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FavoritesService implements \VuFind\I18n\Translator\TranslatorAwareInterface, ServiceAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use ServiceAwareTrait;

    /**
     * Record cache
     *
     * @var RecordCache
     */
    protected $recordCache = null;

    /**
     * Constructor
     *
     * @param RecordCache   $cache    Record cache
     */
    public function __construct(
        RecordCache $cache = null
    ) {
        $this->recordCache = $cache;
    }

    /**
     * Get a list object for the specified ID (or 'NEW' to create a new list).
     *
     * @param string              $listId List ID (or 'NEW')
     * @param \VuFind\Db\Row\User $user   The user saving the record
     *
     * @return \VuFind\Db\Entity\UserList
     *
     * @throws \VuFind\Exception\ListPermission
     */
    public function getListObject($listId, \VuFind\Db\Row\User $user)
    {
        $listService = $this->getDbService(\VuFind\Db\Service\UserListService::class);
        if (empty($listId) || $listId == 'NEW') {
            $list = $listService->getNew($user->id);
            $list->setTitle($this->translate('My Favorites'));
            $listService->save($list, $user->id);
        } else {
            $list = $listService->getExisting($listId);
            // Validate incoming list ID:
            if (!$list->editAllowed($user->id)) {
                throw new \VuFind\Exception\ListPermission('Access denied.');
            }
            $listService->rememberLastUsed($list); // handled by save() in other case
        }
        return $list;
    }

    /**
     * Persist a resource to the record cache (if applicable).
     *
     * @param RecordDriver               $driver   Record driver to persist
     * @param \VuFind\Db\Entity\Resource $resource Resource object
     *
     * @return void
     */
    protected function persistToCache(
        RecordDriver $driver,
        \VuFind\Db\Entity\Resource $resource
    ) {
        if ($this->recordCache) {
            $this->recordCache->setContext(RecordCache::CONTEXT_FAVORITE);
            $this->recordCache->createOrUpdate(
                $resource->getRecordId(),
                $resource->getSource(),
                $driver->getRawData()
            );
        }
    }

    /**
     * Save this record to the user's favorites.
     *
     * @param array               $params Array with some or all of these keys:
     *  <ul>
     *    <li>mytags - Tag array to associate with record (optional)</li>
     *    <li>notes - Notes to associate with record (optional)</li>
     *    <li>list - ID of list to save record into (omit to create new list)</li>
     *  </ul>
     * @param \VuFind\Db\Row\User $user   The user saving the record
     * @param RecordDriver        $driver Record driver for record being saved
     *
     * @return array list information
     */
    public function save(
        array $params,
        \VuFind\Db\Row\User $user,
        RecordDriver $driver
    ) {
        // Validate incoming parameters:
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        // Get or create a list object as needed:
        $list = $this->getListObject(
            $params['list'] ?? '',
            $user
        );

        // Get or create a resource object as needed:
        $resource = $this->getDbService(\VuFind\Db\Service\ResourceService::class)->findResource(
            $driver->getUniqueId(),
            $driver->getSourceIdentifier(),
            true,
            $driver
        );

        // Persist record in the database for "offline" use
        $this->persistToCache($driver, $resource);

        // Add the information to the user's account:
        $userService = $this->getDbService(\VuFind\Db\Service\UserService::class);
        $userService->saveResource(
            $resource,
            $user->id,
            $list,
            $params['mytags'] ?? [],
            $params['notes'] ?? ''
        );
        return ['listId' => $list->getId()];
    }
}
