<?php

namespace Nevadskiy\Tokens\Generator;

class ShortCodeGenerator implements Generator
{
    /**
     * @var int
     */
    protected $length;

    /**
     * @var string
     */
    private $pool;

    /**
     * The default pool which excludes ambiguous characters [0, O, 1, I].
     *
     * @param int $length
     * @param string $pool
     */
    public function __construct(int $length = 8, string $pool = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ')
    {
        $this->length = $length;
        $this->pool = $pool;
    }

    /**
     * Generate the token string.
     *
     * @return string
     */
    public function generate(): string
    {
        return substr(str_shuffle(str_repeat($this->pool, $this->length)), 0, $this->length);
    }
}
