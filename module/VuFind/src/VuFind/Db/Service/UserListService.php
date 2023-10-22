<?php

/**
 * Database service for UserList.
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
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserList;
use VuFind\Db\Entity\UserResource;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Tags;

/**
 * Database service for UserList.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserListService extends AbstractService implements LoggerAwareInterface, ServiceAwareInterface
{
    use LoggerAwareTrait;
    use ServiceAwareTrait;

    /**
     * Tag parser.
     *
     * @var Tags
     */
    protected $tagParser;

    /**
     * Session container for last list information.
     *
     * @var Container
     */
    protected $session = null;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     * @param Tags                $tagParser           Tag parser
     * @param Container           $session             Session container
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        Tags $tagParser,
        Container $session = null
    ) {
        parent::__construct($entityManager, $entityPluginManager);
        $this->tagParser = $tagParser;
        $this->session = $session;
    }

    /**
     * Get an array of resource tags associated with the list.
     *
     * @param UserList $list UserList object.
     *
     * @return array
     */
    public function getResourceTags($list)
    {
        $user = $list->getUser();
        if (empty($user)) {
            return [];
        }
        $tags = $this->getDbService(\VuFind\Db\Service\TagService::class)
            ->getListTagsForUser($user, null, $list);
        return $tags;
    }

    /**
     * Create a userlist entity object.
     *
     * @return UserList
     */
    public function createUserList(): UserList
    {
        $class = $this->getEntityClass(ResourceTags::class);
        return new $class();
    }

    /**
     * Create a new list object.
     *
     * @param User|bool $user User object representing owner of
     * new list (or false if not logged in)
     *
     * @return UserList
     * @throws LoginRequiredException
     */
    public function getNew($user)
    {
        if (!$user) {
            throw new LoginRequiredException('Log in to create lists.');
        }

        $row = $this->createUserList()
            ->setCreated(new \DateTime())
            ->setUser($user);
        return $row;
    }

    /**
     * Retrieve a list object.
     *
     * @param int $id Numeric ID for existing list.
     *
     * @return UserList
     * @throws RecordMissingException
     */
    public function getExisting($id)
    {
        $result = $this->getEntityById(\VuFind\Db\Entity\UserList::class, $id);
        if (empty($result)) {
            throw new RecordMissingException('Cannot load list ' . $id);
        }
        return $result;
    }

    /**
     * Update and save the list object using a request object -- useful for
     * sharing form processing between multiple actions.
     *
     * @param User|int|bool              $user    Logged-in user (false if none)
     * @param UserList                   $list    User list that is being modified
     * @param \Laminas\Stdlib\Parameters $request Request to process
     *
     * @return int ID of newly created list
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function updateFromRequest($user, $list, $request)
    {
        $list->setTitle($request->get('title'))
            ->setDescription($request->get('desc'))
            ->setPublic($request->get('public'));

        if (!($user && $user == $list->getId())) {
            throw new ListPermissionException('list_access_denied');
        }
        if (empty($list->getTitle())) {
            throw new MissingFieldException('list_edit_name_required');
        }

        try {
            $this->persistEntity($list);
        } catch (\Exception $e) {
            $this->logError('Could not save list: ' . $e->getMessage());
            return false;
        }

        $this->rememberLastUsed($list);

        if (null !== ($tags = $request->get('tags'))) {
            $linker = $this->getDbService(\VuFind\Db\Service\TagService::class);
            $linker->destroyListLinks($list, $user);
            foreach ($this->tagParser->parse($tags) as $tag) {
                $this->addListTag($tag, $user, $list);
            }
        }

        return $list->getId();
    }

    /**
     * Add a tag to the list.
     *
     * @param string   $tagText The tag to save.
     * @param User|int $user    The user posting the tag.
     * @param UserList $list    The userlist to tag.
     *
     * @return void
     */
    public function addListTag($tagText, $user, $list)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tagService = $this->getDbService(\VuFind\Db\Service\TagService::class);
            $tag = $tagService->getByText($tagText);
            $tagService->createLink(
                $tag,
                null,
                $user,
                $list
            );
        }
    }

    /**
     * Remember that this list was used so that it can become the default in
     * dialog boxes.
     *
     * @param UserList $list User list to be set as default
     *
     * @return void
     */
    public function rememberLastUsed($list)
    {
        if (null !== $this->session) {
            $this->session->lastUsed = $list->getId();
        }
    }

    /**
     * Get lists containing a specific user_resource
     *
     * @param string $resourceId ID of record being checked.
     * @param string $source     Source of record to look up
     * @param int    $userId     Optional user ID (to limit results to a particular
     * user).
     *
     * @return array
     */
    public function getListsContainingResource(
        $resourceId,
        $source = DEFAULT_SEARCH_BACKEND,
        $userId = null
    ) {
        $dql = 'SELECT DISTINCT(ul.id), ul FROM ' . $this->getEntityClass(UserList::class) . ' ul '
            . 'JOIN ' . $this->getEntityClass(UserResource::class) . ' ur WITH ur.list = ul.id '
            . 'JOIN ' . $this->getEntityClass(Resource::class) . ' r WITH r.id = ul.resource '
            . 'WHERE r.recordId = :resourceId AND r.source = :source ';

        $parameters = compact('resourceId', 'source');
        if (null !== $userId) {
            $dql .= 'AND ur.user = :userId ';
            $parameters['userId'] = $userId;
        }

        $dql .= 'ORDER BY ul.title';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }
}
