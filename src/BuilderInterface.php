<?php

declare(strict_types=1);

namespace Los\Uql;

use Laminas\Db\Sql\Select;
use Psr\Http\Message\ServerRequestInterface;

interface BuilderInterface
{
    // Logical
    public const OP_NOT      = '$not';
    public const OP_IN       = '$in';
    public const OP_NOT_IN   = '$nin';
    public const OP_LIKE     = '$like';
    public const OP_OR       = '$or';
    public const OP_AND      = '$and';
    public const OP_NULL     = '$null';
    public const OP_NOT_NULL = '$nnull';

    public const OP_LOGIC = [
        self::OP_NOT,
        self::OP_IN,
        self::OP_NOT_IN,
        self::OP_LIKE,
        self::OP_OR,
        self::OP_AND,
        self::OP_NULL,
        self::OP_NOT_NULL,
    ];

    // Conditional
    public const OP_GREATER       = '$gt';
    public const OP_GREATER_EQUAL = '$gte';
    public const OP_LESS          = '$lt';
    public const OP_LESS_EQUAL    = '$lte';
    public const OP_BETWEEN       = '$bt';

    public const OP_CONDITIONAL = [
        self::OP_GREATER,
        self::OP_GREATER_EQUAL,
        self::OP_LESS,
        self::OP_LESS_EQUAL,
        self::OP_BETWEEN,
    ];

    // Hints
    public const HINT_SORT  = '$sort';
    public const HINT_LIMIT = '$limit';
    public const HINT_SKIP  = '$skip';

    public const HINT_ORDER_ASC  = ['asc', 'ASC', 1, '1'];
    public const HINT_ORDER_DESC = ['desc', 'DESC', -1, '-1'];

    /**
     * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     *
     * @return Select|array
     */
    public function fromRequest(ServerRequestInterface $request);

    /** @return Select|array */
    public function fromParams(array $query, array $hint = []);
}
