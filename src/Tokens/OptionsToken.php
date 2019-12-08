<?php

namespace Nevadskiy\Tokens\Tokens;

use DateInterval;
use DateTimeInterface;
use Illuminate\Http\Request;
use Nevadskiy\Tokens\Generator\Generator;
use UnexpectedValueException;

class OptionsToken implements Token, GenerationLimit, UsageLimit
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var Request
     */
    protected $request;

    /**
     * OptionsToken constructor.
     *
     * @param string $name
     * @param array $options
     * @param array $defaults
     * @param Request $request
     */
    public function __construct(string $name, array $options, array $defaults, Request $request)
    {
        $this->name = $name;
        $this->request = $request;
        $this->options = $this->mergeOptions($options, $defaults);
    }

    /**
     * Merge token options with defaults.
     *
     * @param array $options
     * @param array $defaults
     * @return array
     */
    protected function mergeOptions(array $options, array $defaults): array
    {
        return array_merge($defaults, $options);
    }

    /**
     * Get the token name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Generate a token string.
     *
     * @return string
     */
    public function generate(): string
    {
        return $this->resolveGenerator()->generate();
    }

    /**
     * Get the token expiration date.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getExpirationDate()
    {
        return $this->options['ttl'];
    }

    /**
     * Get the token generation strategy name.
     * Can be one of ['remove', 'keep', 'reuse'].
     *
     * @return string
     */
    public function getGenerationStrategy(): string
    {
        return $this->options['previous'];
    }

    /**
     * Determine if the token generation throttling is enabled.
     *
     * @return bool
     */
    public function isGenerationThrottlingEnabled(): bool
    {
        return $this->options['generation_throttling'];
    }

    /**
     * Get the key for identifying attempts for throttling limiter on generation process.
     *
     * @return string
     */
    public function getGenerationLimiterKey(): string
    {
        return implode(':', ['_tok', 'gen', $this->getName(), $this->request->ip()]);
    }

    /**
     * Get maximum token generation attempts amount for throttling limiter.
     *
     * @return int
     */
    public function getGenerationAttempts(): int
    {
        return $this->options['generation_attempts'];
    }

    /**
     * Get the time interval limited generation attempts can be exhausted within.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getGenerationAttemptsInterval()
    {
        return $this->options['generation_attempts_interval'];
    }

    /**
     * Determine if the token usage throttling is enabled.
     *
     * @return bool
     */
    public function isUsageThrottlingEnabled(): bool
    {
        return $this->options['usage_throttling'];
    }

    /**
     * Get the key for identifying attempts for throttling limiter on usage process.
     *
     * @return string
     */
    public function getUsageLimiterKey(): string
    {
        return implode(':', ['_tok', 'use', $this->getName(), $this->request->ip()]);
    }

    /**
     * Get maximum token usage attempts amount for throttling limiter.
     *
     * @return int
     */
    public function getUsageAttempts(): int
    {
        return $this->options['usage_attempts'];
    }

    /**
     * Get the time interval limited usage attempts can be exhausted within.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getUsageAttemptsInterval()
    {
        return $this->options['usage_attempts_interval'];
    }

    /**
     * Resolve a token generator.
     *
     * @return Generator
     */
    protected function resolveGenerator(): Generator
    {
        $generator = $this->options['generator'];

        if (is_string($generator) && class_exists($generator)) {
            $generator = app($generator);
        }

        if ($generator instanceof Generator) {
            return $generator;
        }

        throw new UnexpectedValueException("Provide a valid generator option for {$this->name} token.");
    }
}
