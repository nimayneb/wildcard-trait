<?php namespace JayBeeR\Wildcard {

    /*
     * This file belongs to the package "nimayneb.wildcard-trait".
     * See LICENSE.txt that was shipped with this package.
     */

    use Generator;
    use JayBeeR\Wildcard\Failures\InvalidCharacterForWildcardPattern;
    use JayBeeR\Wildcard\Failures\InvalidEscapedCharacterForWildcardPattern;

    trait WildcardMatcher
    {
        /**
         * @param string $subject
         * @param string $pattern
         *
         * @return bool
         * @throws InvalidCharacterForWildcardPattern
         * @throws InvalidEscapedCharacterForWildcardPattern
         */
        public function hasWildcardMatch(string $subject, string $pattern): bool
        {
            $found = true;
            $canBeZero = true;
            $neededLength = 0;
            $maxLength = strlen($subject);

            foreach ($this->getWildcardToken($pattern) as $token) {
                if (0 === $maxLength) {
                    $found = (
                        (Token::ZERO_OR_ONE_CHARACTER === $token)
                        || (Token::ZERO_OR_MANY_CHARACTERS === $token)
                    );

                    break;
                }

                if (Token::ONE_CHARACTER === $token) {
                    $subject = substr($subject, 1);
                    $maxLength -= 1;

                    $neededLength = 0;
                    $canBeZero = true;
                } elseif (Token::ZERO_OR_ONE_CHARACTER === $token) {
                    $neededLength = 1;
                    $canBeZero = true;
                } elseif (Token::ZERO_OR_MANY_CHARACTERS === $token) {
                    $neededLength = $maxLength;
                    $canBeZero = true;
                } elseif (Token::MANY_OF_CHARACTERS === $token) {
                    $neededLength = $maxLength;
                    $canBeZero = false;
                } else {
                    if (chr(0) === $token[0]) {
                        $token = $token[1];
                    }

                    if (
                        (false === ($position = strpos($subject, $token)))
                        || ((false === $canBeZero) && (0 === $position))
                        || ((true === $canBeZero) && (1 === $neededLength) && (1 < $position))
                        || ((0 === $neededLength) && (0 !== $position))
                    ) {
                        $subject = '';
                        $maxLength = 0;
                        $found = false;

                        break;
                    }

                    $start = $position + strlen($token);
                    $subject = substr($subject, $start);
                    $maxLength -= $start;

                    $neededLength = 0;
                    $canBeZero = true;
                }
            }

            if (('' !== $subject) && (0 !== $maxLength)) {
                $found = ($maxLength <= $neededLength);
            }

            return $found;
        }

        /**
         * @param string $pattern
         *
         * @return Generator|string[]
         * @throws InvalidCharacterForWildcardPattern
         * @throws InvalidEscapedCharacterForWildcardPattern
         */
        protected function getWildcardToken(string $pattern): Generator
        {
            $previousToken = null;

            while (null !== ($position = $this->findNextToken($pattern))) {
                $token = $pattern[$position];
                $nextToken = (isset($pattern[$position + 1]) ? $pattern[$position + 1] : null);

                // search phrase

                if (0 < $position) {
                    $previousToken = null;

                    yield substr($pattern, 0, $position);
                }

                $pattern = substr($pattern, $position + 1);

                // 1. no combination of token (***)
                // 2. no combination of token (?**)
                // 3. no combination of token (?*?)
                // 4. no combination of token (*?)

                if (
                    ((Token::MANY_OF_CHARACTERS === $previousToken) && (Token::ZERO_OR_MANY_CHARACTERS === $token))
                    || ((Token::ZERO_OR_MANY_CHARACTERS === $previousToken) && (Token::ONE_CHARACTER === $token))
                    || ((Token::ZERO_OR_ONE_CHARACTER === $previousToken) && (Token::ONE_CHARACTER === $token))
                    || ((Token::ZERO_OR_ONE_CHARACTER === $previousToken) && (Token::ZERO_OR_MANY_CHARACTERS === $token))
                ) {
                    throw new InvalidCharacterForWildcardPattern($pattern, $position);
                }

                // 1. combine two tokens (**) 1-x
                // 2. combine two tokens (?*) 0-1

                if ((Token::ZERO_OR_MANY_CHARACTERS === $token) && (Token::ZERO_OR_MANY_CHARACTERS === $nextToken)) {
                    $token = Token::MANY_OF_CHARACTERS;
                    $pattern = substr($pattern, 1);
                } elseif ((Token::ONE_CHARACTER === $token) && (Token::ZERO_OR_MANY_CHARACTERS === $nextToken)) {
                    $token = Token::ZERO_OR_ONE_CHARACTER;
                    $pattern = substr($pattern, 1);
                }

                $previousToken = $token;

                //  escaped characters: \? \*
                // backslash character: \

                if (Token::ESCAPE_CHAR === $token) {
                    if ((!isset($pattern[0])) || (!$this->hasNextToken($escapeChar = $pattern[0]))) {
                        throw new InvalidEscapedCharacterForWildcardPattern($pattern, $position);
                    } else {
                        yield chr(0) . $escapeChar;

                        $pattern = substr($pattern, 1);
                    }

                    continue;
                }

                yield $token;
            }

            // search phrase

            if (0 < strlen($pattern)) {
                yield $pattern;
            }
        }

        /**
         * @param string $character
         *
         * @return bool
         */
        protected function hasNextToken(string $character): bool
        {
            return (
                (Token::ZERO_OR_MANY_CHARACTERS === $character[0])
                || (Token::ONE_CHARACTER === $character[0])
                || (Token::ESCAPE_CHAR === $character[0])
            );
        }

        /**
         * @param string $pattern
         *
         * @return int|null
         */
        protected function findNextToken(string $pattern): ?int
        {
            $positions = array_filter(
                [
                    strpos($pattern, Token::ZERO_OR_MANY_CHARACTERS),
                    strpos($pattern, Token::ONE_CHARACTER),
                    strpos($pattern, Token::ESCAPE_CHAR)
                ],
                fn ($value) => false !== $value
            );

            return (!empty($positions) ? min($positions) : null);
        }
    }
}
