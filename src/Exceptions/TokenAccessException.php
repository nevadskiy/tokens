<?php

namespace Nevadskiy\Tokens\Exceptions;

use Illuminate\Database\Eloquent\Model;

class TokenAccessException extends TokenException
{
    /**
     * @var Model
     */
    private $owner;

    /**
     * Static constructor.
     *
     * @param Model $owner
     * @return TokenAccessException
     */
    public static function fromOwner(Model $owner): TokenAccessException
    {
        return (new static('The token belongs to another owner.'))->setOwner($owner);
    }

    /**
     * Set the token owner.
     *
     * @param Model $owner
     * @return $this
     */
    public function setOwner(Model $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * Get the token owner.
     *
     * @return Model
     */
    public function getOwner(): Model
    {
        return $this->owner;
    }
}
