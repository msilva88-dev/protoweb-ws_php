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

namespace ProtoWeb\DynamicChat\DataBase;

use ProtoWeb\DynamicChat\Library\JsonCoder;

/**
 * Trait ChatDataBaseTrait
 *
 * Provides shared functionality for chat database backends,
 * including JSON message output handling.
 *
 * This trait defines common methods that can be reused
 * across different database implementations
 * (example: file-based, CI3),
 * without duplicating logic or requiring inheritance.
 *
 * Intended to be used within classes implementing
 * `ChatDataBaseInterface`, typically to provide consistent
 * JSON output via `printJsonMsgFromMsgEntry()`.
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\DataBase
 * @since 2025
 * @version 1.0
 */
trait ChatDataBaseTrait
{
    /**
     * Outputs the current message entry as a JSON string to STDOUT.
     *
     * Attempts to encode the internal message entry
     * (`$this->getMsgEntry()`) as JSON and writes it to standard output,
     * appending a newline.
     * Also flushes the output buffer to ensure immediate delivery.
     *
     * If encoding fails
     * (example: due to invalid UTF-8 or recursive data),
     * the error is logged to STDERR and the method returns false.
     *
     * @return bool
     *     True on successful output, false if encoding failed.
     */
    final protected function printJsonMsgFromMsgEntry(): bool
    {
        $msgEntry = $this->getMsgEntry();

        /*
         * Validate ID fields as non-negative values,
         * normalize to int or numeric string
         * within PHP int range,
         * then encode data to JSON.
         */
        $json = JsonCoder::encode($msgEntry);

        if ($json !== false) {
            echo $json . PHP_EOL;

            flush();
            fflush(STDOUT);

            return true;
        } else {
            fwrite(
                STDERR,
                'Failed to encode message entry as JSON for output: '
                . json_last_error_msg() . PHP_EOL
            );

            return false;
        }
    }
}
