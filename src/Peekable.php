<?php

declare(strict_types=1);

namespace TypedJson;

use Iterator;
use LogicException;

/**
 * @template T
 */
final class Peekable
{
    /** @var Iterator<mixed, T> */
    private Iterator $stream;
    /** @var T | null */
    private mixed $current;
    /** @var T | null */
    private mixed $next;
    public int $index = 0;
    /** @var list<T | null> */
    private array $history = [];

    /**
     * @param iterable<mixed, T> $stream
     */
    public function __construct(iterable $stream)
    {
        $this->stream = $stream instanceof Iterator ? $stream : self::toIterator($stream);
        $this->current = $this->currentStreamValue();
        $this->history[] = $this->current;
        $this->stream->next();
        $this->next = $this->currentStreamValue();
    }

    /**
     * @template K
     * @template V
     * @param iterable<K, V> $stream
     * @return Iterator<K, V>
     */
    private static function toIterator(iterable $stream): Iterator
    {
        yield from $stream;
    }

    public function next(): void
    {
        $this->current = $this->next;
        $this->history[] = $this->current;
        $this->stream->next();
        $this->next = $this->currentStreamValue();
        $this->index++;
    }

    /**
     * @return T | null
     */
    public function peek(): mixed
    {
        return $this->next;
    }

    /**
     * @return T | null
     */
    public function current(): mixed
    {
        return $this->current;
    }

    /**
     * @return T | null
     */
    public function currentStreamValue(): mixed
    {
        if (!$this->stream->valid()) {
            return null;
        }
        $value = $this->stream->current();
        if ($value === null) {
            throw new LogicException('Stream value cannot be null');
        }
        return $value;
    }
}
