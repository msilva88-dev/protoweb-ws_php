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
 * Class DataNormalizer
 *
 * Provides common normalization helpers for mixed input values.
 *
 * This static utility class exposes methods to convert raw
 * or untrusted input data into safe and predictable scalar types.
 * It standardizes values such as strings, and integers,
 * improving data consistency for validation, storage, or display.
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
final class DataNormalizer
{
    /**
     * Prevents instantiation of this static utility class.
     */
    private function __construct()
    {
        // Static-only class
    }

    /**
     * Normalizes a value to a trimmed string.
     *
     * If the input is a string, it will be trimmed in place.
     * Otherwise, the reference will be set to an empty string.
     *
     * @param mixed $value
     *     The input value to normalize (passed by reference).
     *
     * @return void
     */
    final public static function string(&$value): void
    {
        $value = is_string($value) ? trim($value) : '';
    }

    /**
     * Converts a numeric string to an int
     * if it's within PHP's integer limits.
     *
     * This method normalizes a valid string integer
     * (with optional minus sign)
     * and converts it to an actual PHP int only
     * if its value falls within the range
     * of PHP_INT_MIN and PHP_INT_MAX.
     *
     * Otherwise, it leaves the value as a string,
     * suitable for SQL BIGINT.
     *
     * @param mixed $value
     *     The input value to normalize, passed by reference.
     *     Will be cast to int if within native int range.
     *
     * @return bool
     *     True if value was successfully cast to int,
     *     false otherwise (example: out of range or invalid format).
     */
    final public static function stringToInt(&$value): bool
    {
        // Validate integer string
        if (!is_string($value) || !preg_match('/^-?\d+$/', $value)) {
            return false;
        }

        $isNegative = ($value[0] === '-');
        $limit = $isNegative ? (string)PHP_INT_MIN : (string)PHP_INT_MAX;

        // Step 1/3 of normalize: Convert to absolute value
        $abs = ltrim($isNegative ? substr($value, 1) : $value, '0');

        /*
         * Step 2/3 of normalize:
         *     Convert to 0 if the absolute value is empty ('').
         */
        if ($abs === '') {
            $abs = '0';
        }

        // Step 3/3 of normalize: Normalize the absolute value
        $normalized = $isNegative ? '-' . $abs : $abs;

        // Compare magnitudes (taking sign into account)
        if (strlen($normalized) < strlen($limit)) {
            $value = (int)$normalized;

            return true;
        } elseif (strlen($normalized) === strlen($limit)) {
            if (
                ($isNegative && strcmp($normalized, $limit) >= 0)
                || (!$isNegative && strcmp($normalized, $limit) <= 0)
            ) {
                $value = (int)$normalized;

                return true;
            }
        }

        // Else: leave as integer string (SQL BIGINT)
        return false;
    }
}
