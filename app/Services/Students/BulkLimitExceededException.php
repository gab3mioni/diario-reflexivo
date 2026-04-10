<?php

namespace App\Services\Students;

class BulkLimitExceededException extends \RuntimeException
{
    public function __construct(public readonly int $limit = 45)
    {
        parent::__construct("Máximo de {$limit} linhas por lote.");
    }
}
