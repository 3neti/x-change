<?php

declare(strict_types=1);

it('serves pay code estimate under the configured versioned api prefix', function () {
    $response = $this->postJson(xchangeApi('pay-codes/estimate'), []);

    expect($response->status())->not->toBe(404);
});

it('serves pay code generation under the configured versioned api prefix', function () {
    $response = $this->postJson(xchangeApi('pay-codes'), []);

    expect($response->status())->not->toBe(404);
});
