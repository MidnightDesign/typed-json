<?php

declare(strict_types=1);

namespace TypedJson;

use UnexpectedValueException;
use function ctype_space;
use function is_numeric;
use function sprintf;
use function str_split;

final class Lexer
{
    /**
     * @param Peekable<string> $chars
     * @return iterable<int, Token | string | float | int | bool>
     */
    public static function lex(Peekable $chars): iterable
    {
        while (true) {
            $char = $chars->current();
            $chars->next();
            if ($char === null) {
                break;
            }
            $token = match ($char) {
                '{' => Token::OpenCurly,
                '}' => Token::CloseCurly,
                '[' => Token::OpenSquare,
                ']' => Token::CloseSquare,
                ':' => Token::Colon,
                ',' => Token::Comma,
                't' => self::lexTrue($chars),
                'f' => self::lexFalse($chars),
                'n' => self::lexNull($chars),
                '"' => self::lexString($chars),
                default => (static function () use ($chars, $char) {
                    if (is_numeric($char)) {
                        return self::lexNumber($char, $chars);
                    }
                    if (ctype_space($char)) {
                        return self::whiteSpace($chars);
                    }
                    throw new UnexpectedValueException(sprintf('Unexpected character "%s" at position %d', $char, $chars->index));
                })(),
            };
            yield $token;
        }
    }

    /**
     * @param Peekable<string> $chars
     */
    private static function lexTrue(Peekable $chars): Token
    {
        self::expect('rue', $chars);
        return Token::True;
    }

    /**
     * @param Peekable<string> $chars
     */
    private static function lexFalse(Peekable $chars): Token
    {
        self::expect('alse', $chars);
        return Token::False;
    }

    /**
     * @param Peekable<string> $chars
     */
    private static function lexNull(Peekable $chars): Token
    {
        self::expect('ull', $chars);
        return Token::Null;
    }

    /**
     * @param Peekable<string> $chars
     */
    private static function lexString(Peekable $chars): string
    {
        $string = '';
        $escape = false;
        while (true) {
            $char = $chars->current();
            if ($char === null) {
                throw new UnexpectedValueException('Unexpected end of input');
            }
            if ($char === '"') {
                if ($escape) {
                    $string .= '"';
                    $escape = false;
                    $chars->next();
                    continue;
                }
                break;
            }
            if ($char === '\\') {
                $escape = true;
                $chars->next();
                continue;
            }
            $string .= $char;
            $chars->next();
        }
        $chars->next();
        return $string;
    }

    /**
     * @param Peekable<string> $chars
     */
    private static function expect(string $string, Peekable $chars): void
    {
        foreach (str_split($string) as $char) {
            if ($chars->current() === $char) {
                $chars->next();
                continue;
            }
            throw new UnexpectedValueException(sprintf('Unexpected character "%s"', $char));
        }
    }

    /**
     * @param Peekable<string> $chars
     */
    private static function lexNumber(string $firstChar, Peekable $chars): float|int
    {
        $intPart = self::parseInt($firstChar, $chars);
        if ($chars->peek() !== '.') {
            return $intPart;
        }
        $chars->next();
        $firstFractionDigit = $chars->current();
        if (is_numeric($firstFractionDigit)) {
            $chars->next();
            $fractionalPart = self::parseInt($firstFractionDigit, $chars);
        } else {
            $fractionalPart = 0;
        }
        return (float)($intPart . '.' . $fractionalPart);
    }

    /**
     * @param Peekable<string> $chars
     */
    private static function parseInt(string $firstChar, Peekable $chars): int
    {
        $int = $firstChar;
        while (true) {
            $char = $chars->current();
            if (!is_numeric($char)) {
                break;
            }
            $int .= $char;
            $chars->next();
        }
        return (int)$int;
    }

    /**
     * @param Peekable<string> $chars
     */
    private static function whiteSpace(Peekable $chars): Token
    {
        while (true) {
            $char = $chars->current();
            if ($char === null) {
                break;
            }
            if (!ctype_space($char)) {
                break;
            }
            $chars->next();
        }
        return Token::WhiteSpace;
    }
}
