<?php

declare(strict_types=1);

namespace TypedJson;

enum Token
{
    case OpenCurly;
    case CloseCurly;
    case OpenSquare;
    case CloseSquare;
    case Colon;
    case Comma;
    case WhiteSpace;
    case Null;
    case True;
    case False;
}
