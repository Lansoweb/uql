<?php
declare(strict_types=1);

namespace Los\UrlQueryDb;

use Psr\Http\Message\ServerRequestInterface;

interface BuilderInterface
{
    // Logical
    const OP_NOT = '$not';
    const OP_IN = '$in';
    const OP_NOT_IN = '$nin';
    const OP_LIKE = '$like';
    const OP_OR = '$or';
    const OP_AND = '$and';
    const OP_NULL = '$null';
    const OP_NOT_NULL = '$nnull';

    const OP_LOGIC = [
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
    const OP_GREATER = '$gt';
    const OP_GREATER_EQUAL = '$gte';
    const OP_LESS = '$lt';
    const OP_LESS_EQUAL = '$lte';
    const OP_BETWEEN = '$bt';

    const OP_CONDITIONAL = [
        self::OP_GREATER,
        self::OP_GREATER_EQUAL,
        self::OP_LESS,
        self::OP_LESS_EQUAL,
        self::OP_BETWEEN
    ];

    public function fromRequest(ServerRequestInterface $request);
    public function fromParams(array $query, array $hint = []);
}
