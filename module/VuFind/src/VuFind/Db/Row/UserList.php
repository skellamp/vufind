<?php

/**
 * Row Definition for user_list
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use Laminas\Session\Container;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\Tags;

/**
 * Row Definition for user_list
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $title
 * @property string $description
 * @property string $created
 * @property bool   $public
 */
class UserList extends RowGateway implements
    \VuFind\Db\Table\DbTableAwareInterface,
    \VuFind\Db\Service\ServiceAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use \VuFind\Db\Service\ServiceAwareTrait;

    /**
     * Session container for last list information.
     *
     * @var Container
     */
    protected $session = null;

    /**
     * Tag parser.
     *
     * @var Tags
     */
    protected $tagParser;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter   Database adapter
     * @param Tags                        $tagParser Tag parser
     * @param Container                   $session   Session container
     */
    public function __construct($adapter, Tags $tagParser, Container $session = null)
    {
        $this->tagParser = $tagParser;
        $this->session = $session;
        parent::__construct('id', 'user_list', $adapter);
    }

    /**
     * Is the current user allowed to edit this list?
     *
     * @param \VuFind\Db\Row\User|bool $user Logged-in user (false if none)
     *
     * @return bool
     */
    public function editAllowed($user)
    {
        if ($user && $user->id == $this->user_id) {
            return true;
        }
        return false;
    }

    /**
     * Get an array of resource tags associated with this list.
     *
     * @return array
     */
    public function getResourceTags()
    {
        $table = $this->getDbTable('User');
        $user = $table->select(['id' => $this->user_id])->current();
        if (empty($user)) {
            return [];
        }
        return $user->getTags(null, $this->id);
    }

    /**
     * Get an array of tags assigned to this list.
     *
     * @return array
     */
    public function getListTags()
    {
        $table = $this->getDbTable('User');
        $user = $table->select(['id' => $this->user_id])->current();
        if (empty($user)) {
            return [];
        }
        return $user->getListTags($this->id, $this->user_id);
    }

    /**
     * Add a tag to the list.
     *
     * @param string              $tagText The tag to save.
     * @param \VuFind\Db\Row\User $user    The user posting the tag.
     *
     * @return void
     */
    public function addListTag($tagText, $user)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tagService = $this->getDbService(\VuFind\Db\Service\TagService::class);
            $tag = $tagService->getByText($tagText);
            $tagService->createLink(
                $tag,
                null,
                $user->id,
                $this->id
            );
        }
    }

    /**
     * Set session container
     *
     * @param Container $session Session container
     *
     * @return void
     */
    public function setSession(Container $session)
    {
        $this->session = $session;
    }
}
