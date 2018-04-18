# UrlQueryDb

[![Build Status](https://travis-ci.org/Lansoweb/urlquery-db.svg?branch=master)](https://travis-ci.org/Lansoweb/urlquery-db)
[![Coverage Status](https://coveralls.io/repos/github/Lansoweb/urlquery-db/badge.svg?branch=master)](https://coveralls.io/github/Lansoweb/urlquery-db?branch=master)
[![Latest Stable Version](https://poser.pugx.org/los/urlquery-db/v/stable)](https://packagist.org/packages/los/urlquery-db)
[![Total Downloads](https://poser.pugx.org/los/urlquery-db/downloads)](https://packagist.org/packages/los/urlquery-db)
[![License](https://poser.pugx.org/los/urlquery-db/license)](https://packagist.org/packages/los/urlquery-db)

This library utilizes url query parameters and generates db queries.

At this moment, it provides integration with:

- [zend-db](https://github.com/zendframework/zend-db/)

Planned:

- [mongodb](https://docs.mongodb.com/php-library/current/)


## Installing

```sh
 composer require los/urlquery-db
```

## Usage

The builder uses the query parameters 'q' for the queries and 'h' for hint (sort, order, limits, etc).
You can change these in the constructor:
```php
$builder = new ZendDbBuilder($select, 'query', 'hint');
```

The Select instance returned by the builder methods is a clone from the one passed in the constructor.

### Zend DB

Passing the request directly:

```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    $select = new \Zend\Db\Select('table');
    $select = (new ZendDbBuilder($select))->fromRequest($request);
    $statement = $sql->prepareStatementForSqlObject($select);
    $results = $statement->execute();
}
```

or manually passing the parameters:
```php
public function handle(ServerRequestInterface $request): ResponseInterface
{
    $queryParams = $request->getQueryParams();
    $query = $queryParams['q'] ?? [];
    $hint = $queryParams['h'] ?? [];

    $select = new \Zend\Db\Select('table');
    $select = (new ZendDbBuilder($select))->fromParams($query, $hint);
    $statement = $sql->prepareStatementForSqlObject($select);
    $results = $statement->execute();
}
```

#### Examples:

| operation | url query | select |
|-----------|-----------|--------|
| equal | ?q={"id":1} | WHERE id = 1 |
| not | ?q={"id":{"$not":1}} | WHERE id != 1 |
| in | ?q={"id":{"$in":[1,2]}} | WHERE id IN (1, 2) |
| nin | ?q={"id":{"$nin":[1,2]}} | WHERE id NOT IN (1, 2) |
| like | ?q={"name":{"$like":"John%"}} | WHERE name LIKE 'John%' |
| null | ?q={"$null":"name"} | WHERE name IS NULL |
| not null | ?q={"$nnull":"name"} | WHERE name IS NOT NULL |
| and | ?q={"$and":[{"id":1},{"name":"John"}]} | WHERE id = 1 AND name = 'John' |
| or | ?q={"$or":[{"id":1},{"name":"John"}]} | WHERE id = 1 OR name = 'John' |
| greater | ?q={"price":{"$gt":100}} | WHERE price > 100 |
| greater or equal | ?q={"price":{"$gte":100}} | WHERE price >= 100 |
| less | ?q={"price":{"$lt":100}} | WHERE price < 100 |
| less or equal | ?q={"price":{"$lte":100}} | WHERE price <= 100 |
| between | ?q={"price":{"$bt":[100,200]}} | WHERE price >= 100 AND price <= 200 |

You can mix and nest queries:

| url query | select |
|-----------|-----------|
| ?q={"id":{"$not":1},"$or":[{"id":2},{"id":"3"}],"$and":[{"id":2},{"name":"test"}]} | WHERE "id" != '1' AND ("id" = '2' OR "id" = '3') AND ("id" = '2' AND "name" = 'test') |
| ?q={"$or":[{"$and":[{"id":1},{"name":"test"}]},{"id":{"$not":1}},{"name":"test"}]} | WHERE (("id" = '1' AND "name" = 'test') OR "id" != '1' OR "name" = 'test') |

#### Hint examples:

| operation | url query | select |
|-----------|-----------|--------|
| sort | ?q={"id":1}&h={"$sort":"name"} | WHERE id = 1 ORDER BY name asc, price DESC |
| sort | ?q={"id":1}&h={"$sort":{"name":"asc","price":-1}} | WHERE id = 1 ORDER BY name asc, price DESC |
| limit | ?q={}&h={"$limit":10} | SELECT * FROM table LIMIT 10 |
| limit + skip | ?q={}&h={"$limit":10,"$skip":20} | SELECT * FROM table LIMIT 10 SKIP 10 |
