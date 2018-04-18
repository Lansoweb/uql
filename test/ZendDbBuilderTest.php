<?php
declare(strict_types=1);

namespace Los\UrlQueryDb\Tests;

use Los\UrlQueryDb\Exception\MalformedException;
use Los\UrlQueryDb\ZendDbBuilder;
use Los\UrlQueryDbTests\TestAssets\TrustingSql92Platform;
use PHPUnit\Framework\TestCase;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Zend\Diactoros\ServerRequest;

class ZendDbBuilderTest extends TestCase
{
    /** @var ZendDbBuilder */
    private $builder;

    protected function setUp()
    {
        $this->builder = new ZendDbBuilder();
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
        $where = $this->builder->fromParams([], []);
        $this->assertInstanceOf(Where::class, $where);
        $this->assertSame(0, $where->count());
    }

    public function testUnknownOperator()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$abc":{"$a":1}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(0, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test"', $select);
    }

    public function testSimpleQuery()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":1}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" = \'1\'', $select);
    }

    public function testNot()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$not":1}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" != \'1\'', $select);
    }

    public function testNull()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$null":"id"}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" IS NULL', $select);
    }

    public function testNotNull()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$nnull":"id"}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" IS NOT NULL', $select);
    }

    public function testIn()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$in":[1,2]}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" IN (\'1\', \'2\')', $select);
    }

    public function testNotIn()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$nin":[1,2]}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" NOT IN (\'1\', \'2\')', $select);
    }

    public function testLike()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"name":{"$like":"test%"}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "name" LIKE \'test%\'', $select);
    }

    public function testNotAndLike()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$not":1},"name":{"$like":"test%"}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(2, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" != \'1\' AND "name" LIKE \'test%\'', $select);
    }

    public function testOr()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$or":[{"id":1},{"id":"2"}]}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE ("id" = \'1\' OR "id" = \'2\')', $select);
    }

    public function testAnd()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$and":[{"id":1},{"name":"test"}]}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE ("id" = \'1\' AND "name" = \'test\')', $select);
    }

    public function testNotAndOr()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"id":{"$not":1},"$or":[{"id":2},{"id":"3"}],"$and":[{"id":2},{"name":"test"}]}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(3, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "id" != \'1\' AND ("id" = \'2\' OR "id" = \'3\') AND ("id" = \'2\' AND "name" = \'test\')', $select);
    }

    public function testNestedAnd()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$and":[{"id":{"$not":1}},{"name":"test"}]}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE ("id" != \'1\' AND "name" = \'test\')', $select);
    }

    public function testDoubleNestedAndOr()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$or":[{"$and":[{"id":1},{"name":"test"}]},{"id":{"$not":1}},{"name":"test"}]}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE (("id" = \'1\' AND "name" = \'test\') OR "id" != \'1\' OR "name" = \'test\')', $select);
    }

    public function testGreater()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$gt":100}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" > \'100\'', $select);
    }

    public function testGreaterEqual()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$gte":100}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" >= \'100\'', $select);
    }

    public function testLess()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$lt":100}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" < \'100\'', $select);
    }

    public function testLessEqual()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$lte":100}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" <= \'100\'', $select);
    }

    public function testBetween()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"price":{"$bt":[100,200]}}']);
        $where = $this->builder->fromRequest($request);
        $this->assertSame(1, $where->count());
        $select = $this->createSelect($where);
        $this->assertSame('SELECT "test".* FROM "test" WHERE "price" BETWEEN \'100\' AND \'200\'', $select);
    }

    private function createSelect(Where $where) : string
    {
        $select = new Select('test');
        $select->where($where);
        return $select->getSqlString(new TrustingSql92Platform());
    }
}
