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
 * Class Extension
 *
 * Utility class for validating required PHP extensions.
 *
 * Provides static methods to check if extensions are loaded,
 * and to retrieve a list of missing extensions.
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
final class Extension
{
    /**
     * Prevents instantiation of this static utility class.
     */
    private function __construct()
    {
        // Static-only class
    }

    /**
     * Detects and returns a list of missing or invalid PHP extensions.
     *
     * Iterates over the provided array of extension names,
     * checks if each one is a valid string and whether it is loaded
     * in the current PHP environment.
     * Non-string or missing extensions are added to the result array.
     *
     * @param array<int, string> $extensions
     *     List of PHP extension names to verify.
     *
     * @return array<int, string>
     *     List of extension names that are either missing
     *     or not valid strings.
     */
    final public static function detectMissing(array $extensions): array
    {
        $missing = [];

        foreach ($extensions as $ext) {
            if (!is_string($ext) || !extension_loaded($ext)) {
                $missing[] = (string)$ext;
            }
        }

        return $missing;
    }

    /**
     * Validates that all given PHP extensions are loaded.
     *
     * Prints an error to STDERR and returns false on failure.
     * Stops at the first invalid or missing extension.
     *
     * @param string[] $extensions List of required extension names.
     *
     * @return bool True if all extensions are loaded; false otherwise.
     */
    final public static function validate(array $extensions): bool
    {
        foreach ($extensions as $ext) {
            if (!is_string($ext)) {
                fwrite(
                    STDERR,
                    'Invalid extension name: '
                    . var_export($ext, true)
                    . PHP_EOL
                );

                return false;
            }

            if (!extension_loaded($ext)) {
                fwrite(
                    STDERR,
                    ucfirst($ext) . ' extension is not loaded' . PHP_EOL
                );

                return false;
            }
        }

        return true;
    }
}
