<?php

declare(strict_types=1);

namespace TypedJson;

use Stringable;
use UnexpectedValueException;

use function get_debug_type;
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
     * @return JsonNode
     */
    public static function parse(string|Stringable $json): JsonObject|string|bool|int|float|null|array
    {
        $tokens = Lexer::lex(new Peekable(str_split((string)$json)));
        return self::parseNode(new Peekable(self::skipWhitespace($tokens)));
    }

    /**
     * @param PeekableTokens $tokens
     * @return JsonNode
     */
    private static function parseNode(Peekable $tokens): JsonObject|string|bool|int|float|null|array
    {
        $token = $tokens->current();
        $value = match ($token) {
            Token::OpenCurly => self::parseObject($tokens),
            Token::OpenSquare => self::parseArray($tokens),
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
     * @param PeekableTokens $tokens
     */
    private static function parseObject(Peekable $tokens): JsonObject
    {
        $tokens->next();
        $members = self::parseMembers($tokens);
        self::expect(Token::CloseCurly, $tokens);
        return new JsonObject($members);
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
     * @return array<string, JsonNode>
     */
    private static function parseMembers(Peekable $tokens): array
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
            [$key, $value] = self::parseMember($tokens);
            $members[$key] = $value;
            if ($tokens->current() === Token::CloseCurly) {
                break;
            }
        }
        return $members;
    }

    /**
     * @param PeekableTokens $tokens
     * @return array{string, JsonNode}
     */
    private static function parseMember(Peekable $tokens): array
    {
        $key = $tokens->current();
        if (!is_string($key)) {
            throw new UnexpectedValueException('Expected string');
        }
        $tokens->next();
        self::expect(Token::Colon, $tokens);
        $value = self::parseNode($tokens);
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
     * @param PeekableTokens $tokens
     * @return list<JsonNode>
     */
    private static function parseArray(Peekable $tokens): array
    {
        $tokens->next();
        $token = $tokens->current();
        if ($token === Token::CloseSquare) {
            $tokens->next();
            return [];
        }
        $values = self::parseArrayValues($tokens);
        self::expect(Token::CloseSquare, $tokens);
        return $values;
    }

    /**
     * @param PeekableTokens $tokens
     * @return list<JsonNode>
     */
    private static function parseArrayValues(Peekable $tokens): array
    {
        $values = [];
        $first = true;
        while (true) {
            if (!$first) {
                self::expect(Token::Comma, $tokens);
            }
            $first = false;
            $values[] = self::parseNode($tokens);
            $token = $tokens->current();
            if ($token === Token::CloseSquare) {
                break;
            }
        }
        return $values;
    }
}
