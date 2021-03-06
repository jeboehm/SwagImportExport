<?php
/**
 * (c) shopware AG <info@shopware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Shopware\ImportExport;

use Tests\Helper\DbAdapterTest;

class ArticlesPricesDbAdapterTest extends DbAdapterTest
{
    protected $yamlFile = "TestCases/articlePricesDbAdapter.yml";

    public function setUp()
    {
        parent::setUp();
        $this->dbAdapter = 'articlesPrices';
        $this->dbTable = 's_articles_prices';
    }

    /**
     * @param array $columns
     * @param int[] $ids
     * @param array $expected
     * @param int $expectedCount
     *
     * @dataProvider readProvider
     */
    public function testRead($columns, $ids, $expected, $expectedCount)
    {
        $this->read($columns, $ids, $expected, $expectedCount);
    }

    public function readProvider()
    {
        return $this->getDataProvider('testRead');
    }

    /**
     * @param int $start
     * @param array $limit
     * @param array $expected
     * @param int $expectedCount
     *
     * @dataProvider readRecordIdsProvider
     */
    public function testReadRecordIds($start, $limit, $expected, $expectedCount)
    {
        $this->readRecordIds($start, $limit, [], $expected, $expectedCount);
    }

    public function readRecordIdsProvider()
    {
        return $this->getDataProvider('testReadRecordIds');
    }
}
