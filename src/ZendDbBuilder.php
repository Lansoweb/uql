<?php
declare(strict_types=1);

namespace Los\UrlQueryDb;

use Los\UrlQueryDb\Exception\MalformedException;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Db\Sql\Predicate\IsNotNull;
use Zend\Db\Sql\Predicate\IsNull;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Where;

final class ZendDbBuilder implements BuilderInterface
{
    private $queryName;
    private $hintName;
    private $where;

    /**
     * ZendDbBuilder constructor.
     * @param string $queryName
     * @param string $hintName
     */
    public function __construct(string $queryName = 'q', string $hintName = 'h')
    {
        $this->queryName = $queryName;
        $this->hintName = $hintName;
        $this->where = new Where();
    }

    /**
     * @param ServerRequestInterface $request
     * @return Where
     */
    public function fromRequest(ServerRequestInterface $request) : Where
    {
        $queryParams = $request->getQueryParams();
        $query = json_decode($queryParams['q'] ?? '{}', true);
        $hint = json_decode($queryParams['h'] ?? '{}', true);

        if (! is_array($query) || ! is_array($hint)) {
            throw new MalformedException('Invalid query or hint');
        }

        return $this->fromParams($query, $hint);
    }

    /**
     * @param array $query
     * @param array $hint
     * @return Where
     */
    public function fromParams(array $query, array $hint = []) : Where
    {
        foreach ($query as $key => $value) {
            $this->parseQuery($key, $value, $this->where);
        }

        return $this->where;
    }

    /**
     * @param $key
     * @param $value
     * @param Predicate $where
     */
    private function parseQuery($key, $value, Predicate $where) : void
    {
        if ($key === BuilderInterface::OP_NULL) {
            $where->addPredicate(new IsNull($value));
            return;
        }

        if ($key === BuilderInterface::OP_NOT_NULL) {
            $where->addPredicate(new IsNotNull($value));
            return;
        }

        if (! is_array($value)) {
            $where->equalTo($key, $value);
            return;
        }

        if ($key === BuilderInterface::OP_OR) {
            $nested = $where->nest();
            foreach ($value as $query) {
                $value2 = reset($query);
                $key2 = key($query);
                $this->parseQuery($key2, $value2, $nested);
                $nested->or;
            }
            return;
        }

        if ($key === BuilderInterface::OP_AND) {
            $nested = $where->nest();
            foreach ($value as $query) {
                $value2 = reset($query);
                $key2 = key($query);
                $this->parseQuery($key2, $value2, $nested);
                $nested->and;
            }
            return;
        }

        $opValue = reset($value);
        $op = key($value);

        if (in_array($op, BuilderInterface::OP_LOGIC)) {
            $this->parseLogic($key, $op, $opValue, $where);
            return;
        }

        if (in_array($op, BuilderInterface::OP_CONDITIONAL)) {
            $this->parseConditional($key, $op, $opValue, $where);
            return;
        }
    }

    /**
     * @param $key
     * @param string $op
     * @param $value
     * @param Predicate $where
     */
    private function parseLogic($key, string $op, $value, Predicate $where) : void
    {
        if ($op === BuilderInterface::OP_NOT) {
            $where->notEqualTo($key, $value);
            return;
        }

        if ($op === BuilderInterface::OP_IN) {
            $where->in($key, $value);
            return;
        }

        if ($op === BuilderInterface::OP_NOT_IN) {
            $where->notIn($key, $value);
            return;
        }

        // At this point, should only be BuilderInterface::OP_LIKE . No if to keep PHPUnit happy
        $where->like($key, $value);
    }

    /**
     * @param $key
     * @param string $op
     * @param $value
     * @param Predicate $where
     */
    private function parseConditional($key, string $op, $value, Predicate $where) : void
    {
        if ($op === BuilderInterface::OP_GREATER) {
            $where->greaterThan($key, $value);
            return;
        }

        if ($op === BuilderInterface::OP_GREATER_EQUAL) {
            $where->greaterThanOrEqualTo($key, $value);
            return;
        }

        if ($op === BuilderInterface::OP_LESS) {
            $where->lessThan($key, $value);
            return;
        }

        if ($op === BuilderInterface::OP_LESS_EQUAL) {
            $where->lessThanOrEqualTo($key, $value);
            return;
        }

        // At this point, should only be BuilderInterface::OP_BETWEEN . No if to keep PHPUnit happy
        $where->between($key, $value[0], $value[1]);
    }
}
