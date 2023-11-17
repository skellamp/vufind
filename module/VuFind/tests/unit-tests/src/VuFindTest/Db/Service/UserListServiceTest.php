<?php

/**
 * UserListService Test Class
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
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

namespace VuFindTest\Db\Service;

use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserList;

/**
 * UserListService Test Class
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class UserListServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Get test list objects
     *
     * @return array
     */
    protected function getTestLists()
    {
        $list1 = new UserList();
        $list1->setTitle('Title1');
        $list2 = new UserList();
        $list2->setTitle('Title2');
        $list3 = new UserList();
        $list3->setTitle('Title3');
        return [$list1, $list2, $list3];
    }

    /**
     * Test that a new list contains the appropriate user ID.
     *
     * @return void
     */
    public function testNewListContainsCreatorUserId()
    {
        $user = new User();
        $user->setUsername('sud');
        $listService = $this->getMockListService();
        $list = $listService->getNew($user);

        $this->assertEquals($user->getUsername(), $list->getUser()->getUsername());
    }

    /**
     * Test that an exception is thrown if a non-logged-in user tries to create a new list.
     *
     * @return void
     */
    public function testLoginRequiredToCreateList()
    {
        $this->expectException(\VuFind\Exception\LoginRequired::class);

        $listService = $this->getMockListService();
        $listService->getNew(false);
    }

    /**
     * Test that new lists are distinct (not references to same object).
     *
     * @return void
     */
    public function testNewListsAreDistinct()
    {
        $listService = $this->getMockListService();
        $list1 = $listService->getNew(new User());
        $list2 = $listService->getNew(new User());
        $this->assertEquals('Title1', $list1->getTitle());
        $this->assertEquals('Title2', $list2->getTitle());
    }

    /**
     * Get a mock userList service.
     *
     * @return MockObject
     */
    public function getMockListService()
    {
        $entityManager = $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pluginManager = $this->getMockBuilder(\VuFind\Db\Entity\PluginManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $tags = $this->getMockBuilder(\VuFind\Tags::class)
            ->disableOriginalConstructor()
            ->getMock();
        $session = $this->getMockBuilder(\Laminas\Session\Container::class)
            ->disableOriginalConstructor()
            ->getMock();

        $listService = $this->getMockBuilder(\VuFind\Db\Service\UserListService::class)
            ->setConstructorArgs([$entityManager, $pluginManager, $tags, $session])
            ->onlyMethods(['createUserList'])
            ->getMock();
        $listService->expects($this->atMost(3))->method('createUserList')
            ->willReturn(...$this->getTestLists());
        return $listService;
    }
}
