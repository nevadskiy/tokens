<?php

namespace Nevadskiy\Tokens\Generator;

interface Generator
{
    /**
     * Generate the token string.
     *
     * @return string
     */
    public function generate(): string;
}
