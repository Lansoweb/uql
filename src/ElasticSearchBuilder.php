<?php

declare(strict_types=1);

namespace Los\Uql;

use Psr\Http\Message\ServerRequestInterface;

use function array_merge;
use function assert;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function key;
use function reset;
use function str_replace;

final class ElasticSearchBuilder implements BuilderInterface
{
    private array $params = [];

    public function __construct(private string $queryName = 'q', private string $hintName = 'h')
    {
    }

    public function fromRequest(ServerRequestInterface $request): array
    {
        $queryParams = $request->getQueryParams();
        $query       = json_decode($queryParams[$this->queryName] ?? '{}', true);
        $hint        = json_decode($queryParams[$this->hintName] ?? '{}', true);

        if (! is_array($query) || ! is_array($hint)) {
            throw new Exception\MalformedException('Invalid query or hint');
        }

        return $this->fromParams($query, $hint);
    }

    public function fromParams(array $query, array $hint = []): array
    {
        foreach ($query as $key => $value) {
            $this->params = array_merge($this->params, $this->parseQuery($key, $value));
        }

        $hintParams = [];
        foreach ($hint as $key => $value) {
            $hintParams = array_merge($hintParams, $this->parseHint($key, $value));
        }

        $result = ['body' => ['query' => ['constant_score' => ['filter' => ['bool' => $this->params]]]]];

        if (! empty($hintParams)) {
            $result = array_merge($result, $hintParams);
        }

        return $result;
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     *
     * @param mixed $value
     */
    private function parseQuery(string $key, $value, bool $withoutOperator = false): array
    {
        if ($key === BuilderInterface::OP_NULL) {
            $params = ['exists' => ['field' => $value]];

            return $withoutOperator ? $params : ['must_not' => $params];
        }

        if ($key === BuilderInterface::OP_NOT_NULL) {
            $params = ['field' => $value];

            return $withoutOperator ? $params : ['exists' => $params];
        }

        if (! is_array($value)) {
            $params = ['term' => [$key => $value]];

            return $withoutOperator ? $params : ['must' => $params];
        }

        if ($key === BuilderInterface::OP_OR) {
            $params = [];
            foreach ($value as $query) {
                $value2   = reset($query);
                $key2     = key($query);
                $params[] = $this->parseQuery($key2, $value2, true);
            }

            return $withoutOperator ? $params : ['should' => $params];
        }

        if ($key === BuilderInterface::OP_AND) {
            $params = [];
            foreach ($value as $query) {
                $value2   = reset($query);
                $key2     = key($query);
                $params[] = $this->parseQuery($key2, $value2, true);
            }

            return $withoutOperator ? $params : ['must' => $params];
        }

        $opValue = reset($value);
        $op      = key($value);

        assert(is_string($op));

        if (in_array($op, BuilderInterface::OP_LOGIC)) {
            return $this->parseLogic($key, $op, $opValue);
        }

        if (in_array($op, BuilderInterface::OP_CONDITIONAL)) {
            return $this->parseConditional($key, $op, $opValue);
        }

        return [];
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     *
     * @param mixed $value
     */
    private function parseLogic(string $key, string $op, $value): array
    {
        if ($op === BuilderInterface::OP_NOT) {
            return ['must_not' => ['term' => [$key => $value]]];
        }

        if ($op === BuilderInterface::OP_IN) {
            return ['filter' => ['terms' => [$key => $value]]];
        }

        if ($op === BuilderInterface::OP_NOT_IN) {
            return ['must_not' => ['terms' => [$key => $value]]];
        }

        // At this point, should only be BuilderInterface::OP_LIKE . No if to keep PHPUnit happy
        return ['wildcard' => [$key => str_replace('%', '*', $value)]];
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     *
     * @param mixed $value
     */
    private function parseConditional(string $key, string $op, $value): array
    {
        if ($op === BuilderInterface::OP_GREATER) {
            return ['filter' => ['range' => [$key => ['gt' => $value]]]];
        }

        if ($op === BuilderInterface::OP_GREATER_EQUAL) {
            return ['filter' => ['range' => [$key => ['gte' => $value]]]];
        }

        if ($op === BuilderInterface::OP_LESS) {
            return ['filter' => ['range' => [$key => ['lt' => $value]]]];
        }

        if ($op === BuilderInterface::OP_LESS_EQUAL) {
            return ['filter' => ['range' => [$key => ['lte' => $value]]]];
        }

        // At this point, should only be BuilderInterface::OP_BETWEEN . No if to keep PHPUnit happy
        return ['filter' => ['range' => [$key => ['gt' => $value[0], 'lt' => $value[1]]]]];
    }

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     *
     * @param mixed $value
     */
    private function parseHint(string $key, $value): array
    {
        if ($key === BuilderInterface::HINT_SORT) {
            if (! is_array($value)) {
                return ['sort' => [$value]];
            }

            $params = [];
            foreach ($value as $field => $order) {
                $order    = in_array($order, BuilderInterface::HINT_ORDER_ASC) ? 'asc' : 'desc';
                $params[] = [$field => ['order' => $order]];
            }

            return ['sort' => $params];
        }

        if ($key === BuilderInterface::HINT_LIMIT) {
            return ['size' => $value];
        }

        // At this point, should only be BuilderInterface::HINT_SKIP . No if to keep PHPUnit happy
        return ['from' => $value];
    }
}
