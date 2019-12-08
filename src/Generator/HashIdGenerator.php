<?php

namespace Nevadskiy\Tokens\Generator;

use Hashids\Hashids;

class HashIdGenerator implements Generator
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $salt;

    /**
     * @var int
     */
    private $minLength;

    /**
     * HashIdGenerator constructor.
     *
     * @param int $id
     * @param string $salt
     * @param int $minLength
     */
    public function __construct(int $id, string $salt, int $minLength = 6)
    {
        $this->id = $id;
        $this->salt = $salt;
        $this->minLength = $minLength;
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    public function generate(): string
    {
        return (new Hashids($this->salt, $this->minLength))->encode($this->id);
    }
}
