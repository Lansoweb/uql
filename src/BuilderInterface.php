<?php
declare(strict_types=1);

namespace Los\UrlQueryDb;

use Psr\Http\Message\ServerRequestInterface;

interface BuilderInterface
{
    public function fromRequest(ServerRequestInterface $request);
    public function fromParams(array $query, array $hint = []);
}
