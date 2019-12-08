<?php

namespace Nevadskiy\Tokens\Generator;

use Illuminate\Support\Str;

class RandomHashGenerator implements Generator
{
    /**
     * @var string
     */
    private $key;

    /**
     * RandomHashGenerator constructor.
     *
     * @param string $key
     */
    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    public function generate(): string
    {
        return hash_hmac('sha256', Str::random(40), $this->key);
    }
}
