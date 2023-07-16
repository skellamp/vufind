<?php

/**
 * Database service for Records.
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

use VuFind\Db\Entity\Record;

/**
 * Database service for Records.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class RatingsService extends AbstractService implements \VuFind\Db\Service\ServiceAwareInterface
{
    use \VuFind\Db\Service\ServiceAwareTrait;

    /**
     * Find a record by id
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @return false|Record
     */
    public function findRecord($id, $source)
    {
        $dql = "SELECT r "
            . "FROM " . $this->getEntityClass(Record::class) . " r "
            . "WHERE r.recordId = :id AND r.source = :source";
        $parameters = compact('id', 'source');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $records = $query->getResult();
        return count($records) > 0 ? current($records) : false;
    }

    /**
     * Find records by ids
     *
     * @param array  $ids    Record IDs
     * @param string $source Record source
     *
     * @return array Array of record objects found
     */
    public function findRecords($ids, $source)
    {
        if (empty($ids)) {
            return [];
        }

        $dql = "SELECT r "
            . "FROM " . $this->getEntityClass(Record::class) . " r "
            . "WHERE r.recordId IN (:ids) AND r.source = :source";
        $parameters = compact('ids', 'source');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $records = $query->getResult();
        return $records;
    }

    /**
     * Update an existing entry in the record table or create a new one
     *
     * @param string $id      Record ID
     * @param string $source  Data source
     * @param string $rawData Raw data from source
     *
     * @return Record
     */
    public function updateRecord($id, $source, $rawData)
    {
        $record = $this->findRecord($id, $source);
        if (!$record) {
            $record = $session = $this->createEntity()
                ->setRecordId($id)
                ->setSource($source)
                ->setData($rawData)
                ->setVersion(\VuFind\Config\Version::getBuildVersion())
                ->setUpdated(new \DateTime());
        }

        $record->record_id = $id;
        $record->source = $source;
        $record->data = serialize($rawData);
        $record->version = \VuFind\Config\Version::getBuildVersion();
        $record->updated = date('Y-m-d H:i:s');

        // Create or update record.
        $record->save();

        return $record;
    }

     /**
      * Create a record entity object.
      *
      * @return Record
      */
    public function createEntity(): Record
    {
        $class = $this->getEntityClass(Record::class);
        return new $class();
    }
}
