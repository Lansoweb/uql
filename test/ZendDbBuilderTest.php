<?php
declare(strict_types=1);

namespace Los\UqlTests;

use Los\Uql\Exception\MalformedException;
use Los\Uql\ZendDbBuilder;
use Los\UqlTests\TestAssets\TrustingSql92Platform;
use PHPUnit\Framework\TestCase;
use Laminas\Db\Sql\Select;
use Laminas\Diactoros\ServerRequest;

class ZendDbBuilderTest extends TestCase
{
    /** @var ZendDbBuilder */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new ZendDbBuilder(new Select('test'));
    }

    public function testFromRequestWithInvalidQuery()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => 'a']);

        $this->expectException(MalformedException::class);
        $this->builder->fromRequest($request);
    }

    public function testFromRequestWithInvalidHint()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{}', 'h' => 'a']);

        $this->expectException(MalformedException::class);
        $this->builder->fromRequest($request);
    }

    public function testEmptyQuery()
    {
        $select = $this->builder->fromParams([], []);
        $this->assertInstanceOf(Select::class, $select);
        $this->assertSame(0, $select->where->count());
    }

    public function testUnknownOperator()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$abc":{"$a":1}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(0, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test"', $this->createString($select));
    }

    public function testSimpleQuery()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":1}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" = \'1\'', $this->createString($select));
    }

    public function testNot()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$not":1}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" != \'1\'', $this->createString($select));
    }

    public function testNull()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$null":"id"}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" IS NULL', $this->createString($select));
    }

    public function testNotNull()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$nnull":"id"}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" IS NOT NULL', $this->createString($select));
    }

    public function testIn()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$in":[1,2]}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" IN (\'1\', \'2\')', $this->createString($select));
    }

    public function testNotIn()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$nin":[1,2]}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" NOT IN (\'1\', \'2\')', $this->createString($select));
    }

    public function testLike()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"name":{"$like":"test%"}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "name" LIKE \'test%\'', $this->createString($select));
    }

    public function testNotAndLike()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$not":1},"name":{"$like":"test%"}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(2, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" != \'1\' AND "name" LIKE \'test%\'', $this->createString($select));
    }

    public function testOr()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$or":[{"id":1},{"id":"2"}]}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE ("id" = \'1\' OR "id" = \'2\')', $this->createString($select));
    }

    public function testAnd()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$and":[{"id":1},{"name":"test"}]}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE ("id" = \'1\' AND "name" = \'test\')', $this->createString($select));
    }

    public function testNotAndOr()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$not":1},"$or":[{"id":2},{"id":"3"}],"$and":[{"id":2},{"name":"test"}]}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(3, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" != \'1\' AND ("id" = \'2\' OR "id" = \'3\') AND ("id" = \'2\' AND "name" = \'test\')', $this->createString($select));
    }

    public function testNestedAnd()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$and":[{"id":{"$not":1}},{"name":"test"}]}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE ("id" != \'1\' AND "name" = \'test\')', $this->createString($select));
    }

    public function testDoubleNestedAndOr()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$or":[{"$and":[{"id":1},{"name":"test"}]},{"id":{"$not":1}},{"name":"test"}]}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE (("id" = \'1\' AND "name" = \'test\') OR "id" != \'1\' OR "name" = \'test\')', $this->createString($select));
    }

    public function testGreater()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$gt":100}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" > \'100\'', $this->createString($select));
    }

    public function testGreaterEqual()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$gte":100}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" >= \'100\'', $this->createString($select));
    }

    public function testLess()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$lt":100}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" < \'100\'', $this->createString($select));
    }

    public function testLessEqual()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$lte":100}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" <= \'100\'', $this->createString($select));
    }

    public function testBetween()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$bt":[100,200]}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" BETWEEN \'100\' AND \'200\'', $this->createString($select));
    }

    public function testSimpleSort()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['h' => '{"$sort":"id"}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame('SELECT "test".* FROM "test" ORDER BY "id" ASC', $this->createString($select));
    }

    public function testSort()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['h' => '{"$sort":{"id":"asc","name":-1}}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame('SELECT "test".* FROM "test" ORDER BY "id" ASC, "name" DESC', $this->createString($select));
    }

    public function testLimit()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['h' => '{"$limit":100}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame('SELECT "test".* FROM "test" LIMIT \'100\'', $this->createString($select));
    }

    public function testSkip()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['h' => '{"$skip":100}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame('SELECT "test".* FROM "test" OFFSET \'100\'', $this->createString($select));
    }

    public function testAndWithNestedIn()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$and": [{"name":"john"},{"likes": {"$in": ["facebook", "twitter", "instagram"]}}]}']);
        $select = $this->builder->fromRequest($request);
        $this->assertSame(1, $select->where->count());
        $this->assertSame('SELECT "test".* FROM "test" WHERE ("name" = \'john\' AND "likes" IN (\'facebook\', \'twitter\', \'instagram\'))', $this->createString($select));
    }

    private function createString(Select $select) : string
    {
        return $select->getSqlString(new TrustingSql92Platform());
    }
}
