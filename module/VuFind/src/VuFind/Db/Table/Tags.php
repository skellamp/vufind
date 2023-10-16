<?php

/**
 * Table Definition for tags
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;

use function count;

/**
 * Table Definition for tags
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Tags extends Gateway implements \VuFind\Db\Service\ServiceAwareInterface
{
    use \VuFind\Db\Service\ServiceAwareTrait;

    /**
     * Are tags case sensitive?
     *
     * @var bool
     */
    protected $caseSensitive;

    /**
     * Constructor
     *
     * @param Adapter       $adapter       Database adapter
     * @param PluginManager $tm            Table manager
     * @param array         $cfg           Laminas configuration
     * @param RowGateway    $rowObj        Row prototype object (null for default)
     * @param bool          $caseSensitive Are tags case sensitive?
     * @param string        $table         Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $caseSensitive = false,
        $table = 'tags'
    ) {
        $this->caseSensitive = $caseSensitive;
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get the row associated with a specific tag string.
     *
     * @param string $tag       Tag to look up.
     * @param bool   $create    Should we create the row if it does not exist?
     * @param bool   $firstOnly Should we return the first matching row (true)
     * or the entire result set (in case of multiple matches)?
     *
     * @return mixed Matching row/result set if found or created, null otherwise.
     */
    public function getByText($tag, $create = true, $firstOnly = true)
    {
        $cs = $this->caseSensitive;
        $callback = function ($select) use ($tag, $cs) {
            if ($cs) {
                $select->where->equalTo('tag', $tag);
            } else {
                $select->where->literal('lower(tag) = lower(?)', [$tag]);
            }
        };
        $result = $this->select($callback);
        if (count($result) == 0 && $create) {
            $row = $this->createRow();
            $row->tag = $cs ? $tag : mb_strtolower($tag, 'UTF8');
            $row->save();
            return $firstOnly ? $row : [$row];
        }
        return $firstOnly ? $result->current() : $result;
    }
}
