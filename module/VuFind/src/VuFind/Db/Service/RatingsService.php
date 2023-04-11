<?php
/**
 * Database service for Ratings.
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
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\Ratings;

/**
 * Database service for Ratings.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class RatingsService extends AbstractService
{
    /**
     * User database service
     *
     * @var \VuFind\Db\Service\UserService
     */
    protected $userService;

    /**
     * Resource database service
     *
     * @var \VuFind\Db\Service\ResourceService
     */
    protected $resourceService;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     * @param bool                $caseSensitive       Are tags case sensitive?
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        \VuFind\Db\Service\ResourceService $rs,
        \VuFind\Db\Service\UserService $us,
    ) {
        parent::__construct($entityManager, $entityPluginManager);
        $this->resourceService = $rs;
        $this->userService = $us;
    }

    /**
     * Get average rating and rating count associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param ?int   $userId User ID, or null for all users
     *
     * @return array Array with keys count and rating (between 0 and 100)
     */
    public function getForResource(string $id, string $source, ?int $userId): array
    {
        $resource = $this->resourceService->findResource($id, $source);
        
        if (empty($resource)) {
            return [
                'count' => 0,
                'rating' => 0
            ];
        }

        $dql = "SELECT COUNT(r.id) AS count, AVG(r.rating) AS rating "
            . "FROM " . $this->getEntityClass(Ratings::class) . " r ";

        $dqlWhere[] = "r.resource = :resource";
        $parameters['resource'] = $resource;
        if (null !== $userId) {
            $dqlWhere[] = "r.user = :user";
            $parameters['user'] = $this->userService->getUserById($userId);
        }
        $dql .= ' WHERE ' . implode(' AND ', $dqlWhere);
        $query = $this->entityManager->createQuery($dql);
        $result = $query->getResult();
        return [
            'count' => $result->count,
            'rating' => floor($result->rating) ?? 0
        ];
    }

    /**
     * Get rating breakdown for the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param array  $groups Group definition (key => [min, max])
     *
     * @return array Array with keys count and rating (between 0 and 100) as well as
     * an groups array with ratings from lowest to highest
     */
    public function getCountsForResource(
        string $id,
        string $source,
        array $groups
    ): array {
        $result = [
            'count' => 0,
            'rating' => 0,
            'groups' => [],
        ];
        foreach (array_keys($groups) as $key) {
            $result['groups'][$key] = 0;
        }

        $resource = $this->resourceService->findResource($id, $source);
        if (empty($resource)) {
            return $result;
        }
        $dql = "SELECT COUNT(r.*) AS count, r.rating AS rating "
            . "FROM " . $this->getEntityClass(Ratings::class) . " r "
            . "WHERE r.resource = :resource "
            . "GROUP BY rating";

        $parameters['resource'] = $resource;

        $query = $this->entityManager->createQuery($dql);

        $query->setParameters($parameters);
        $queryResult = $query->getResult();

        $ratingTotal = 0;
        $groupCount = 0;
        foreach ($queryResult as $rating) {
            $result['count'] += $rating['count'];
            $ratingTotal += $rating['rating'];
            ++$groupCount;
            if ($groups) {
                foreach ($groups as $key => $range) {
                    if ($rating['rating'] >= $range[0] && $rating['rating'] <= $range[1]
                    ) {
                        $result['groups'][$key] = ($result['groups'][$key] ?? 0)
                            + $rating['count'];
                    }
                }
            }
        }
        $result['rating'] = $groupCount ? floor($ratingTotal / $groupCount) : 0;
        return $result;
    }

    /**
     * Delete a rating if the owner is logged in.  Returns true on success.
     *
     * @param int                 $id   ID of row to delete
     * @param \VuFind\Db\Row\User $user Logged in user object
     *
     * @return bool
     */
    public function deleteIfOwnedByUser(int $id, \VuFind\Db\Row\User $user)
    {
        // User must have an ID:
        if (!isset($user->id)) {
            return false;
        }
        $userEntity = $this->userService->getUserById($user->id);

        $row = $this->entityManager->find(
            $this->getEntityClass(Ratings::class),
            $id
        );
        if (!($row->getUser() == $userEntity)) {
            return false;
        }

        // If we got this far, everything is okay:
        $this->entityManager->remove($row);
        return true;
    }

    /**
     * Deletes all ratings by a user.
     *
     * @param \VuFind\Db\Row\User $user User object
     *
     * @return void
     */
    public function deleteByUser(\VuFind\Db\Row\User $user): void
    {
        $dql = 'DELETE FROM ' . $this->getEntityClass(Ratings::class) . ' r '
            . "WHERE r.user = :user";
        $userEntity = $this->userService->getUserById($user->id);
        $parameters['user'] = $userEntity;
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get statistics on use of Ratings.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $dql = "SELECT COUNT(DISTINCT(r.user)) AS users, "
            . "COUNT(DISTINCT(r.resource)) AS resources, "
            . "COUNT(r.id) AS total "
            . "FROM " . $this->getEntityClass(Ratings::class) . " r";
        $query = $this->entityManager->createQuery($dql);
        $stats = current($query->getResult());
        return $stats;
    }
}
