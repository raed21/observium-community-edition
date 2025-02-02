<?php

declare(strict_types=1);

namespace Doctrine\SqlFormatter;

interface Highlighter
{
    const TOKEN_TYPE_TO_HIGHLIGHT = [
        Token::TOKEN_TYPE_BOUNDARY => self::HIGHLIGHT_BOUNDARY,
        Token::TOKEN_TYPE_WORD => self::HIGHLIGHT_WORD,
        Token::TOKEN_TYPE_BACKTICK_QUOTE => self::HIGHLIGHT_BACKTICK_QUOTE,
        Token::TOKEN_TYPE_QUOTE => self::HIGHLIGHT_QUOTE,
        Token::TOKEN_TYPE_RESERVED => self::HIGHLIGHT_RESERVED,
        Token::TOKEN_TYPE_RESERVED_TOPLEVEL => self::HIGHLIGHT_RESERVED,
        Token::TOKEN_TYPE_RESERVED_NEWLINE => self::HIGHLIGHT_RESERVED,
        Token::TOKEN_TYPE_NUMBER => self::HIGHLIGHT_NUMBER,
        Token::TOKEN_TYPE_VARIABLE => self::HIGHLIGHT_VARIABLE,
        Token::TOKEN_TYPE_COMMENT => self::HIGHLIGHT_COMMENT,
        Token::TOKEN_TYPE_BLOCK_COMMENT => self::HIGHLIGHT_COMMENT,
    ];

    const HIGHLIGHT_BOUNDARY       = 'boundary';
    const HIGHLIGHT_WORD           = 'word';
    const HIGHLIGHT_BACKTICK_QUOTE = 'backtickQuote';
    const HIGHLIGHT_QUOTE          = 'quote';
    const HIGHLIGHT_RESERVED       = 'reserved';
    const HIGHLIGHT_NUMBER         = 'number';
    const HIGHLIGHT_VARIABLE       = 'variable';
    const HIGHLIGHT_COMMENT        = 'comment';
    const HIGHLIGHT_ERROR          = 'error';

    /**
     * Highlights a token depending on its type.
     */
    public function highlightToken(int $type, string $value) : string;

    /**
     * Highlights a token which causes an issue
     */
    public function highlightError(string $value) : string;

    /**
     * Highlights an error message
     */
    public function highlightErrorMessage(string $value) : string;

    /**
     * Helper function for building string output
     *
     * @param string $string The string to be quoted
     *
     * @return string The quoted string
     */
    public function output(string $string) : string;
}
