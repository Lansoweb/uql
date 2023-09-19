<?php

declare(strict_types=1);

namespace Los\Uql;

use Laminas\Db\Sql\Predicate\IsNotNull;
use Laminas\Db\Sql\Predicate\IsNull;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Select;
use Psr\Http\Message\ServerRequestInterface;

use function assert;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function key;
use function reset;

final class LaminasDbBuilder implements BuilderInterface
{
    private Select $select;

    public function __construct(Select $select, private string $queryName = 'q', private string $hintName = 'h')
    {
        $this->select = clone $select;
    }

    public function fromRequest(ServerRequestInterface $request): Select
    {
        $queryParams = $request->getQueryParams();
        $query       = json_decode($queryParams[$this->queryName] ?? '{}', true);
        $hint        = json_decode($queryParams[$this->hintName] ?? '{}', true);

        if (! is_array($query) || ! is_array($hint)) {
            throw new Exception\MalformedException('Invalid query or hint');
        }

        return $this->fromParams($query, $hint);
    }

    /**
     * @param array $query
     * @param array $hint
     */
    public function fromParams(array $query, array $hint = []): Select
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
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     *
     * @param mixed $value
     */
    private function parseQuery(string $key, $value, Predicate $where): void
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
                $key2   = key($query);
                $this->parseQuery($key2, $value2, $nested);
                $nested->or;
            }

            return;
        }

        if ($key === BuilderInterface::OP_AND) {
            $nested = $where->nest();
            foreach ($value as $query) {
                $value2 = reset($query);
                $key2   = key($query);
                $this->parseQuery($key2, $value2, $nested);
                $nested->and;
            }

            return;
        }

        $opValue = reset($value);
        $op      = key($value);

        assert(is_string($op));

        if (in_array($op, BuilderInterface::OP_LOGIC)) {
            $this->parseLogic($key, $op, $opValue, $where);

            return;
        }

        if (in_array($op, BuilderInterface::OP_CONDITIONAL)) {
            $this->parseConditional($key, $op, $opValue, $where);

            return;
        }
    }

    private function parseLogic(string $key, string $op, mixed $value, Predicate $where): void
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

    private function parseConditional(string $key, string $op, mixed $value, Predicate $where): void
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

    private function parseHint(string $key, mixed $value, Select $select): void
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
                $value = $sort . ' ' . $order;
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
