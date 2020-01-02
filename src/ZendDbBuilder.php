<?php
declare(strict_types=1);

namespace Los\Uql;

use Psr\Http\Message\ServerRequestInterface;
use Laminas\Db\Sql\Predicate\IsNotNull;
use Laminas\Db\Sql\Predicate\IsNull;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Select;

final class ZendDbBuilder implements BuilderInterface
{
    private $queryName;
    private $hintName;
    private $select;

    /**
     * ZendDbBuilder constructor.
     * @param Select $select
     * @param string $queryName
     * @param string $hintName
     */
    public function __construct(Select $select, string $queryName = 'q', string $hintName = 'h')
    {
        $this->queryName = $queryName;
        $this->hintName = $hintName;
        $this->select = clone $select;
    }

    /**
     * @param ServerRequestInterface $request
     * @return Select
     */
    public function fromRequest(ServerRequestInterface $request) : Select
    {
        $queryParams = $request->getQueryParams();
        $query = json_decode($queryParams['q'] ?? '{}', true);
        $hint = json_decode($queryParams['h'] ?? '{}', true);

        if (! is_array($query) || ! is_array($hint)) {
            throw new Exception\MalformedException('Invalid query or hint');
        }

        return $this->fromParams($query, $hint);
    }

    /**
     * @param array $query
     * @param array $hint
     * @return Select
     */
    public function fromParams(array $query, array $hint = []) : Select
    {
        foreach ($query as $key => $value) {
            $this->parseQuery($key, $value, $this->select->where);
        }

        foreach ($hint as $key => $value) {
            $this->parseHint($key, $value, $this->select);
        }

        return $this->select;
    }

    /**
     * @param mixed $key
     * @param mixed $value
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
     * @param mixed $key
     * @param string $op
     * @param mixed $value
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
     * @param mixed $key
     * @param string $op
     * @param mixed $value
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

    /**
     * @param mixed $key
     * @param mixed $value
     * @param Select $select
     */
    private function parseHint($key, $value, Select $select) : void
    {
        if ($key === BuilderInterface::HINT_SORT) {
            if (! is_array($value)) {
                $select->order($value);
                return;
            }
            foreach ($value as $sort => $order) {
                $order = in_array($order, BuilderInterface::HINT_ORDER_ASC) ?
                    Select::ORDER_ASCENDING :
                    Select::ORDER_DESCENDING;
                $value = "$sort $order";
                $select->order($value);
            }
            return;
        }

        if ($key === BuilderInterface::HINT_LIMIT) {
            $select->limit($value);
            return;
        }

        // At this point, should only be BuilderInterface::HINT_SKIP . No if to keep PHPUnit happy
        $select->offset($value);
    }
}
