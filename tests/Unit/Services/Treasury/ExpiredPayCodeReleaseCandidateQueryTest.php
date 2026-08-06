<?php

declare(strict_types=1);

use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Services\Treasury\ExpiredPayCodeReleaseCandidateQuery;

it('casts text voucher metadata to jsonb for PostgreSQL expiry selection', function () {
    $connection = DB::connection();
    $originalGrammar = $connection->getQueryGrammar();

    try {
        $connection->setQueryGrammar(new PostgresGrammar($connection));

        $query = app(ExpiredPayCodeReleaseCandidateQuery::class)->build(25);

        expect($query->toSql())
            ->toContain(
                "metadata::jsonb #>> '{treasury,pay_code_reservation,status}'",
                "metadata::jsonb #>> '{treasury,pay_code_reservation,source_position_purpose}'",
                'limit 25',
            )
            ->not->toContain('"metadata"->');
    } finally {
        $connection->setQueryGrammar($originalGrammar);
    }
});
