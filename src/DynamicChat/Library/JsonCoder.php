<?php

/*
 * Copyright (c) 2025, Marcio Delgado <marcio@libreware.info>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in
 *    the documentation and/or other materials provided with the
 *    distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS
 * OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE
 * USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * This code is optimized for PHP 7.4+ and follows PSR-12 (coding style)
 * and PSR-5 (PHPDoc) standards. It ensures high traffic handling,
 * high performance, low memory usage, and clean code.
 */

declare(strict_types=1);

namespace ProtoWeb\DynamicChat\Library;

/**
 * Class JsonCoder
 *
 * Provides static methods for encoding and decoding JSON with
 * strict validation and normalization of identifier fields.
 *
 * Identifier fields such as 'id' or keys ending in '_id'
 * are validated to ensure they contain only non-negative integers
 * or digit-only strings.
 * This ensures compatibility with databases, BCMath, and large-ID safe
 * environments (example: 64-bit precision).
 *
 * If any of the required PHP extensions ('json', 'ctype')
 * are not loaded, or if the JSON input/output is malformed,
 * the corresponding error code is set
 * via the reference `$error` parameter, and the function returns
 * an empty string or null depending on context.
 *
 * This class is static-only and cannot be instantiated.
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\Library
 * @since 2025
 * @version 1.0
 */
final class JsonCoder
{
    /**
     * Error code indicating no error occurred.
     *
     * Used as the default state when encoding or decoding succeeds.
     *
     * @var int
     */
    private const ERROR_NONE = 0;

    /**
     * Error code indicating an unknown or unspecified failure.
     *
     * This constant is typically used as a fallback when no specific
     * error condition is matched or identified during execution.
     *
     * @var int
     */
    private const ERROR_UNKNOWN = 1;

    /**
     * Error code indicating invalid JSON input or output.
     *
     * This is set when json_decode fails to produce a valid array,
     * or json_encode fails to generate a valid string.
     *
     * @var int
     */
    private const ERROR_INVIO = 10;

    /**
     * Error code indicating invalid ID field(s).
     *
     * This occurs when one or more 'id' or '*_id' values are negative,
     * not strictly integer-formatted, or contain invalid characters.
     *
     * @var int
     */
    private const ERROR_INVID = 11;

    /**
     * Error code indicating a required PHP extension is missing.
     *
     * Set when 'ctype' or 'json' extensions are not loaded in PHP.
     *
     * @var int
     */
    private const ERROR_EXT = 12;

    /**
     * Prevents instantiation of this utility class.
     */
    private function __construct()
    {
        // Static-only class
    }

    /**
     * Decodes a JSON string and validates ID fields.
     *
     * ID fields (example: 'id', 'user_id') must be
     * non-negative integers or digit-only strings.
     * Native integers are cast to string to preserve precision
     * in high-value identifiers.
     * Invalid values are replaced with null.
     *
     * @param string $json
     *     The JSON string to decode.
     * @param int|null &$error
     *     Error code passed by reference.
     *     Will be set to one of the ERROR_* constants
     *     if validation fails.
     *
     * @return array<int|string, mixed>|null
     *     The decoded and validated associative array,
     *     or null if decoding or validation failed.
     */
    final public static function decode(
        string $json,
        ?int &$error = null
    ): ?array {
        $error = self::ERROR_NONE;

        // Validate extensions
        if (!Extension::validate(['ctype', 'json'])) {
            $error = self::ERROR_EXT;

            return null;
        }

        // Trim the JSON string
        DataNormalizer::string($json);

        $jsonDecoded = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);

        if (!is_array($jsonDecoded)) {
            fwrite(STDERR, "Invalid JSON input" . PHP_EOL);

            $error = self::ERROR_INVIO;

            return null;
        }

        foreach ($jsonDecoded as $key => $value) {
            // Normalize string: remove leading/trailing whitespace
            if (is_string($value)) {
                DataNormalizer::string($jsonDecoded[$key]);
            }

            $isIdKey = ($key === 'id' || substr((string)$key, -3) === '_id');

            if (!$isIdKey) {
                continue;
            }

            if (is_int($value)) {
                if ($value < 0) {
                    fwrite(
                        STDERR,
                        "Negative integer not allowed for $key: $value"
                        . PHP_EOL
                    );

                    $jsonDecoded[$key] = null;
                    $error = self::ERROR_INVID;

                    continue;
                }

                // Safe native int: cast to string for consistency
                $jsonDecoded[$key] = (string)$value;
            } elseif (is_string($value) && ctype_digit($value)) {
                // Valid non-negative integer string
            } else {
                fwrite(
                    STDERR,
                    "Invalid ID value for $key: "
                    . var_export($value, true)
                    . PHP_EOL
                );

                $jsonDecoded[$key] = null;
                $error = self::ERROR_INVID;
            }
        }

        return $jsonDecoded;
    }

    /**
     * Encodes an associative array into a JSON string
     * with validated ID fields.
     *
     * ID fields (example: 'id', 'product_id') must be
     * non-negative integers or digit-only strings.
     * Invalid values are replaced with null.
     * Normalization is applied before encoding
     * to ensure numeric compatibility.
     *
     * @param array<int|string, mixed> $arr
     *     The array to encode.
     * @param int|null &$error
     *     Error code passed by reference.
     *     Will be set to one of the ERROR_* constants
     *     if validation or encoding fails.
     *
     * @return string
     *     The resulting JSON string on success,
     *     or an empty string on failure.
     */
    final public static function encode(
        array $arr,
        int $flags = 0,
        int $depth = 512,
        ?int &$error = null
    ): string {
        $error = self::ERROR_NONE;

        // Validate extensions
        if (!Extension::validate(['ctype', 'json'])) {
            $error = self::ERROR_EXT;

            return '';
        }

        // Proceed to process $arr with guaranteed string keys
        foreach ($arr as $key => $value) {
            // Normalize string: remove leading/trailing whitespace
            if (is_string($value)) {
                DataNormalizer::string($jsonDecoded[$key]);
            }

            $isIdKey = ($key === 'id' || substr($key, -3) === '_id');

            if (!$isIdKey) {
                continue;
            }

            if (is_int($value)) {
                if ($value < 0) {
                    fwrite(
                        STDERR,
                        "Negative integer not allowed for $key: $value"
                        . PHP_EOL
                    );

                    $arr[$key] = null;
                    $error = self::ERROR_INVID;

                    continue;
                }
            } elseif (is_string($value) && ctype_digit($value)) {
                // Valid non-negative integer string
            } else {
                fwrite(
                    STDERR,
                    "Invalid ID value for $key: "
                    . var_export($value, true)
                    . PHP_EOL
                );

                $arr[$key] = null;
                $error = self::ERROR_INVID;
            }

            // Normalize and convert ID if safely possible
            DataNormalizer::stringToInt($arr[$key]);
        }

        // All array keys are always converted to strings in JSON
        $json = json_encode($arr, $flags, $depth);

        if (!is_string($json) || !$json) {
            fwrite(STDERR, 'Invalid JSON output' . PHP_EOL);

            $error = self::ERROR_INVIO;

            return '';
        }

        return $json;
    }
}
