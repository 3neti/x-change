<?php

use App\Services\OpenAI\Client as OpenAIClient;
use App\Exceptions\InstructionParseException;
use App\Services\InstructionParser;

it('parses partial AI JSON and falls back to defaults', function () {
    // stub OpenAI
    $ai = Mockery::mock(OpenAIClient::class, function($m) {
        $m->allows('chat->create')->andReturn([
            'choices'=>[[
                'message' => [
                    'content' => json_encode([
                        'cash' => [
                            'amount' => 500,
                            'currency' => 'EUR',
                            'validation' => [
                                'secret' => 's',
                                'mobile' => 'm',
                                'country' => 'DE',
                                'location' => 'Berlin',
                                'radius' => '100m'
                            ]
                        ],
                        // missing everything else
                    ])
                ]
            ]]
        ]);
    });
    app()->instance(OpenAIClient::class, $ai);

    $parser = app(InstructionParser::class);
    $data = $parser->fromText("Give me vouchers…");

    expect($data->cash->amount)->toBe(500.0)
        ->and($data->cash->currency)->toBe('EUR')
        ->and($data->count)->toBe(1)         // default
        ->and($data->ttl->totalHours)->toBe(12.0);
});

it('throws if AI returns non‐JSON', function() {
    $ai = Mockery::mock(OpenAIClient::class, fn($m)=>$m->allows('chat->create')
        ->andReturn(['choices'=>[['message'=>['content'=>'NOT JSON']]]]));
    app()->instance(OpenAIClient::class, $ai);
    $parser = app(InstructionParser::class);

    expect(fn() => $parser->fromText("…"))->toThrow(InstructionParseException::class);
});
