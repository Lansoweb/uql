<?php
namespace Los\UqlTests\TestAssets;

use Laminas\Db\Adapter\Platform\Sql92;

class TrustingSql92Platform extends Sql92
{
    /**
     * {@inheritDoc}
     */
    public function quoteValue($value)
    {
        return $this->quoteTrustedValue($value);
    }
}
