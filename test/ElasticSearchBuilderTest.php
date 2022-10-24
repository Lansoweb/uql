<?php
declare(strict_types=1);

namespace Los\UqlTests;

use Los\Uql\ElasticSearchBuilder;
use PHPUnit\Framework\TestCase;
use Laminas\Diactoros\ServerRequest;

class ElasticSearchBuilderTest extends TestCase
{
    private ElasticSearchBuilder $builder;

    public function testSimpleIn()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"names": {"$in":["john", "doe"]}}']);
        $query = $this->builder->fromRequest($request);
        $this->assertSame([
            'body' => [
                'query' => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'filter' => [
                                    'terms' => [
                                        'names' => [
                                            'john',
                                            'doe',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query);
    }

    public function testAndWithNestedIn()
    {
        $request = new ServerRequest();
        $request = $request->withQueryParams(['q' => '{"$and": [{"name":"john"},{"likes": {"$in": ["facebook", "twitter", "instagram"]}}]}']);
        $query = $this->builder->fromRequest($request);
        $this->assertSame([
            'body' => [
                'query' => [
                    'constant_score' => [
                        'filter' => [
                            'bool' => [
                                'must' => [
                                    ['term' =>[ 'name' => 'john']],
                                    [
                                        'terms' => [
                                            'likes' => [
                                                'facebook',
                                                'twitter',
                                                'instagram',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $query);
    }

    protected function setUp() : void
    {
        $this->builder = new ElasticSearchBuilder();
    }
}
