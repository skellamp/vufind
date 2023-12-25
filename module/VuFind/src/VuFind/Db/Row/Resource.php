<?php

/**
 * Row Definition for resource
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

use VuFind\Date\DateException;

use function intval;
use function strlen;

/**
 * Row Definition for resource
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 *
 * @property int     $id
 * @property string  $record_id
 * @property string  $title
 * @property ?string $author
 * @property ?int    $year
 * @property string  $source
 * @property ?string $extra_metadata
 */
class Resource extends RowGateway implements
    \VuFind\Db\Table\DbTableAwareInterface,
    \VuFind\Db\Service\ServiceAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;
    use \VuFind\Db\Service\ServiceAwareTrait;

    /**
     * Constructor
     *
     * @param \Laminas\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'resource', $adapter);
    }

    /**
     * Use a record driver to assign metadata to the current row. Return the
     * current object to allow fluent interface.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    The record driver
     * @param \VuFind\Date\Converter            $converter Date converter
     *
     * @return \VuFind\Db\Row\Resource
     */
    public function assignMetadata($driver, \VuFind\Date\Converter $converter)
    {
        // Grab title -- we have to have something in this field!
        $this->title = mb_substr(
            $driver->tryMethod('getSortTitle'),
            0,
            255,
            'UTF-8'
        );
        if (empty($this->title)) {
            $this->title = $driver->getBreadcrumb();
        }

        // Try to find an author; if not available, just leave the default null:
        $author = mb_substr(
            $driver->tryMethod('getPrimaryAuthor'),
            0,
            255,
            'UTF-8'
        );
        if (!empty($author)) {
            $this->author = $author;
        }

        // Try to find a year; if not available, just leave the default null:
        $dates = $driver->tryMethod('getPublicationDates');
        if (isset($dates[0]) && strlen($dates[0]) > 4) {
            try {
                $year = $converter->convertFromDisplayDate('Y', $dates[0]);
            } catch (DateException $e) {
                // If conversion fails, don't store a date:
                $year = '';
            }
        } else {
            $year = $dates[0] ?? '';
        }
        if (!empty($year)) {
            $this->year = intval($year);
        }

        if ($extra = $driver->tryMethod('getExtraResourceMetadata')) {
            $this->extra_metadata = json_encode($extra);
        }
        return $this;
    }
}
