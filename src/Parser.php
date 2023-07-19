<?php

declare(strict_types=1);

namespace TypedJson;

use ReflectionClass;
use Stringable;
use UnexpectedValueException;

use function class_exists;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * @phpstan-type JsonNode JsonObject | string | bool | int | float | null | list<mixed>
 * @phpstan-type ValueToken string | float | int
 * @phpstan-type AnyToken Token | ValueToken
 * @phpstan-type PeekableTokens Peekable<AnyToken>
 */
final class Parser
{
    /**
     * @template T of object
     * @param class-string<T> | array{class-string<T>} $class
     * @return ($class is string ? T : list<T>)
     */
    public static function parse(string|Stringable $json, string|array $class): object|array
    {
        $tokens = Lexer::lex(new Peekable(str_split((string)$json)));
        $bareClass = is_string($class) ? $class : $class[0];
        $node = self::parseNode(new Peekable(self::skipWhitespace($tokens)), $bareClass);
        if (is_string($class)) {
            if (!$node instanceof $class) {
                throw new UnexpectedValueException(sprintf('Expected %s, got %s', $class, get_debug_type($node)));
            }
            return $node;
        }
        if (!is_array($node)) {
            throw new UnexpectedValueException(sprintf('Expected array, got %s', get_debug_type($node)));
        }
        return $node;
    }

    /**
     * @template T of object
     * @param PeekableTokens $tokens
     * @param class-string<T> | null $class
     * @return ($class is string ? (T | list<T>) : (string | bool | int | float | null))
     */
    private static function parseNode(Peekable $tokens, string|null $class): object|string|bool|int|float|null|array
    {
        $token = $tokens->current();
        $value = match ($token) {
            Token::OpenCurly => self::parseObject($tokens, $class),
            Token::OpenSquare => self::parseArray($tokens, $class),
            Token::True => true,
            Token::False => false,
            Token::Null => null,
            default => match (true) {
                is_string($token) || is_int($token) || is_float($token) => $token,
                default => throw new UnexpectedValueException(
                    sprintf('Unexpected token %s', $token instanceof Token ? $token->name : get_debug_type($token)),
                ),
            },
        };
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            $tokens->next();
        }
        return $value;
    }

    /**
     * @template T of object
     * @param PeekableTokens $tokens
     * @param class-string<T> $class
     * @return T
     */
    private static function parseObject(Peekable $tokens, string $class): object
    {
        $tokens->next();
        $members = self::parseMembers($tokens, $class);
        self::expect(Token::CloseCurly, $tokens);
        return ObjectFactory::create($class, $members);
    }

    /**
     * @template T
     * @param iterable<mixed, T> $tokens
     * @return iterable<int, T>
     */
    private static function skipWhitespace(iterable $tokens): iterable
    {
        foreach ($tokens as $token) {
            if ($token === Token::WhiteSpace) {
                continue;
            }
            yield $token;
        }
    }

    /**
     * @param PeekableTokens $tokens
     * @param class-string $class
     * @return array<string, JsonNode>
     */
    private static function parseMembers(Peekable $tokens, string $class): array
    {
        $token = $tokens->current();
        if ($token === Token::CloseCurly) {
            return [];
        }
        $members = [];
        $first = true;
        while (true) {
            if (!$first) {
                self::expect(Token::Comma, $tokens);
            }
            $first = false;
            [$key, $value] = self::parseMember($tokens, $class);
            $members[$key] = $value;
            if ($tokens->current() === Token::CloseCurly) {
                break;
            }
        }
        return $members;
    }

    /**
     * @template T of object
     * @param PeekableTokens $tokens
     * @param class-string<T> | null $class
     * @return ($class is string ? array{string, T} : array{string, JsonNode})
     */
    private static function parseMember(Peekable $tokens, string|null $class): array
    {
        $key = $tokens->current();
        if (!is_string($key)) {
            throw new UnexpectedValueException('Expected string');
        }
        $tokens->next();
        self::expect(Token::Colon, $tokens);
        $fieldClass = $class === null ? null : self::getClassFieldType($class, $key);
        $value = self::parseNode($tokens, $fieldClass);
        return [$key, $value];
    }

    /**
     * @param Token $expected
     * @param PeekableTokens $tokens
     */
    private static function expect(Token $expected, Peekable $tokens): void
    {
        $actual = $tokens->current();
        if ($actual === $expected) {
            $tokens->next();
            return;
        }
        throw new UnexpectedValueException(
            sprintf(
                'Expected %s, got %s',
                $expected->name,
                $actual instanceof Token ? $actual->name : get_debug_type($actual),
            ),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> | null $class
     * @param PeekableTokens $tokens
     * @return ($class is string ? list<T> : list<JsonNode>)
     */
    private static function parseArray(Peekable $tokens, string|null $class): array
    {
        $tokens->next();
        $token = $tokens->current();
        if ($token === Token::CloseSquare) {
            $tokens->next();
            return [];
        }
        $values = self::parseArrayValues($tokens, $class);
        self::expect(Token::CloseSquare, $tokens);
        return $values;
    }

    /**
     * @template T of object
     * @param PeekableTokens $tokens
     * @param class-string<T> | null $class
     * @return ($class is string ? list<T> : list<JsonNode>)
     */
    private static function parseArrayValues(Peekable $tokens, string|null $class): array
    {
        $values = [];
        $first = true;
        while (true) {
            if (!$first) {
                self::expect(Token::Comma, $tokens);
            }
            $first = false;
            $values[] = self::parseNode($tokens, $class);
            $token = $tokens->current();
            if ($token === Token::CloseSquare) {
                break;
            }
        }
        return $values;
    }

    /**
     * @param class-string $class
     * @return class-string | null
     */
    private static function getClassFieldType(string $class, string $field): string|null
    {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->name !== $field) {
                    continue;
                }
                $type = $parameter->getType();
                if ($type === null) {
                    return null;
                }
                $parameterClassName = $type->getName();
                if (!class_exists($parameterClassName)) {
                    return null;
                }
                return $parameterClassName;
            }
        }
        $property = $reflection->getProperty($field);
        $type = $property->getType();
        if ($type === null) {
            return null;
        }
        $propertyClassName = $type->getName();
        if (!class_exists($propertyClassName)) {
            return null;
        }
        return $propertyClassName;
    }
}
