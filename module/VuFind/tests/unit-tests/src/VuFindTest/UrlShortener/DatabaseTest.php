<?php
/**
 * "Database" URL shortener test.
 *
 * PHP version 7
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
namespace VuFindTest\UrlShortener;

use Exception;
use PHPUnit\Framework\TestCase;
use VuFind\Db\Entity\Shortlinks;
use VuFind\UrlShortener\Database;

/**
 * "Database" URL shortener test.
 *
 * @category VuFind
 * @package  Tests
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
class DatabaseTest extends TestCase
{
    /**
     * Database object to test.
     *
     * @param MockObject $entityManager Mock entity manager object
     * @param MockObject $pluginManager Mock plugin manager object
     * @param string     $hashAlgorithm Hash Algorithm to be used
     *
     * @return Database
     */
    protected function getShortner(
        $entityManager,
        $pluginManager,
        $hashAlgorithm = 'md5'
    ) {
        $entityManager= $entityManager;
        $pluginManager = $pluginManager;

        $database = new Database(
            'http://foo',
            $entityManager,
            $pluginManager,
            'RAnD0mVuFindSa!t',
            $hashAlgorithm
        );

        return $database;
    }

    /**
     * Mock entity plugin manager.
     *
     * @return MockObject
     */
    protected function getPluginManager()
    {
        $pluginManager= $this->getMockBuilder(
            \VuFind\Db\Entity\PluginManager::class
        )->disableOriginalConstructor()
            ->getMock();
        return $pluginManager;
    }

    /**
     * Mock entity manager.
     *
     * @param string $parameter Input query parameter
     * @param string $count     Exepectation count
     *
     * @return MockObject
     */
    protected function getEntityManager($shortlink = null, $count = 0)
    {
        $entityManager= $this->getMockBuilder(\Doctrine\ORM\EntityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($shortlink) {
            $entityManager->expects($this->exactly($count))->method('persist');
            $entityManager->expects($this->exactly($count))->method('flush');
        }
        return $entityManager;
    }

    /**
     * Mock queryBuilder
     *
     * @param string $parameter Input query parameter
     * @param array  $result    Expected return value of getResult method.
     *
     * @return MockObject
     */
    protected function getQueryBuilder($parameter, $result)
    {
        $queryBuilder = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $queryBuilder->expects($this->once())->method('select')
            ->with($this->equalTo('s'))
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('from')
            ->with($this->equalTo(Shortlinks::class), $this->equalTo('s'))
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('where')
            ->with($this->equalTo('s.hash = :hash'))
            ->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('setParameter')
            ->with($this->equalTo('hash'), $this->equalTo($parameter))
            ->willReturn($queryBuilder);
        $query = $this->getMockBuilder(\Doctrine\ORM\AbstractQuery::class)
            ->disableOriginalConstructor()
            ->setMethods(['getResult'])
            ->getMockForAbstractClass();
        $query->expects($this->once())->method('getResult')
            ->willReturn($result);
        $queryBuilder->expects($this->once())->method('getQuery')
            ->willReturn($query);
        return $queryBuilder;
    }

    /**
     * Test that the shortener works correctly under "happy path."
     *
     * @return void
     *
     * @throws Exception
     */
    public function testsaveAndShortenHash()
    {
        $shortlink = $this->getMockBuilder(\VuFind\Db\Entity\Shortlinks::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager = $this->getEntityManager($shortlink, 1);
        $pluginManager = $this->getPluginManager();
        $queryBuilder = $this->getQueryBuilder('a1e7812e2', []);

        $entityManager->expects($this->once())->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $pluginManager->expects($this->once())->method('get')
            ->willReturn($shortlink);

        $shortlink->expects($this->once())->method('setHash')
            ->with($this->equalTo('a1e7812e2'))
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('setPath')
            ->with($this->equalTo('/bar'))
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('setCreated')
            ->with($this->anything())
            ->willReturn($shortlink);
        $connection = $this->getMockBuilder(\Doctrine\DBAL\Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager->expects($this->exactly(2))->method('getConnection')
            ->willReturn($connection);
        $connection->expects($this->once())->method('beginTransaction')
            ->willReturn($this->equalTo(true));
        $connection->expects($this->once())->method('commit')
            ->willReturn($this->equalTo(true));
        $db = $this->getShortner($entityManager, $pluginManager);
        $this->assertEquals('http://foo/short/a1e7812e2', $db->shorten('http://foo/bar'));
    }

    /**
     * Test that the shortener works correctly under base62 hashing
     *
     * @return void
     *
     * @throws Exception
     */
    public function testGetBase62Hash()
    {
        $shortlink = $this->getMockBuilder(\VuFind\Db\Entity\Shortlinks::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager = $this->getEntityManager($shortlink, 2);
        $pluginManager = $this->getPluginManager();
        $pluginManager->expects($this->once())->method('get')
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('setPath')
            ->with($this->equalTo('/bar'))
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('setCreated')
            ->with($this->anything())
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('getId')
            ->willReturn(2);
        $shortlink->expects($this->once())->method('setHash')
            ->with($this->equalTo('2'))
            ->willReturn($shortlink);
        $shortlink->expects($this->once())->method('getHash')
            ->willReturn('2');
        $db = $this->getShortner($entityManager, $pluginManager, 'base62');
        $this->assertEquals('http://foo/short/2', $db->shorten('http://foo/bar'));
    }

    /**
     * Test that resolve is supported.
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolution()
    {
        $shortlink = $this->getMockBuilder(\VuFind\Db\Entity\Shortlinks::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager();
        $queryBuilder = $this->getQueryBuilder('8ef580184', [$shortlink]);

        $entityManager->expects($this->once())->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $shortlink->expects($this->once())->method('getPath')
            ->willReturn('/bar');
        $db = $this->getShortner($entityManager, $pluginManager);
        $this->assertEquals('http://foo/bar', $db->resolve('8ef580184'));
    }

    /**
     * Test that resolve errors correctly when given bad input
     *
     * @return void
     *
     * @throws Exception
     */
    public function testResolutionOfBadInput()
    {
        $this->expectExceptionMessage('Shortlink could not be resolved: abcd12?');

        $entityManager = $this->getEntityManager();
        $pluginManager = $this->getPluginManager();
        $queryBuilder = $this->getQueryBuilder('abcd12?', []);

        $entityManager->expects($this->once())->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        $db = $this->getShortner($entityManager, $pluginManager);
        $db->resolve('abcd12?');
    }
}
