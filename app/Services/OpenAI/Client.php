<?php

namespace App\Services\OpenAI;

use OpenAI\Laravel\Facades\OpenAI;

class Client
{
    public function chat()
    {
        return OpenAI::chat();
    }
}
