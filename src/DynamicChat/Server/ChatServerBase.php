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

namespace ProtoWeb\DynamicChat\Server;

use ProtoWeb\DynamicChat\Library\Extension;
use ProtoWeb\DynamicChat\Library\JsonCoder;

/**
 * Class ChatServerBase
 *
 * Abstract base class for chat servers
 * using a stream-based event loop.
 * Designed to work with WebSocketd,
 * it processes JSON messages received
 * via STDIN and emits JSON responses via STDOUT.
 *
 * Implements core logic for detecting input availability
 * with `stream_select`, parsing incoming JSON,
 * dispatching actions (`insert`, `update`), and
 * managing timed polling for new data to broadcast.
 *
 * Concrete subclasses must define `$dataBaseObj`
 * with a compatible implementation of `ChatDataBaseInterface`
 * to handle message persistence.
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @implements ChatServerInterface
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\Server
 * @since 2025
 * @version 1.0
 */
abstract class ChatServerBase implements ChatServerInterface
{
    /**
     * Microseconds to block on `stream_select` (200ms).
     *
     * @var int
     */
    private const MICRO_SECONDS = 200000;

    /**
     * List of PHP extensions required for this server
     * to operate correctly.
     *
     * This constant defines the minimal set of extensions
     * that must be loaded at runtime.
     * These are validated in checkExtensions()
     * before starting the main event loop.
     * If any are missing, the server enters a passive wait state.
     *
     * Note:
     *     'json' is bundled in PHP 8+,
     *     but still validated for compatibility.
     *
     * @var string[]
     */
    private const REQUIRED_EXTENSIONS = ['bcmath', 'ctype', 'json'];

    /**
     * Seconds to block on `stream_select` (set to 0 = non-blocking).
     *
     * @var int
     */
    private const SECONDS = 0;

    /**
     * Maximum number of bytes allowed per STDIN input line.
     *
     * This constant defines the safe read limit from STDIN
     * when processing one line of WebSocket input using fgets().
     *
     * Value: 2^13 = 8192 bytes.
     * Used to prevent oversized messages from overloading the server.
     *
     * @var int
     */
    private const STD_IN_BYTE_LENGTH = 2 ** 13;

    /**
     * Maximum allowed number of bytes to read from STDIN.
     *
     * Prevents excessive memory usage or abuse via oversized input.
     * Equal to 2,147,483,647 bytes (2^31 - 1),
     * which is the 32-bit signed limit.
     *
     * @var int
     */
    private const STD_IN_BYTE_MAX_LENGTH = (2 ** 31) - 1;

    /**
     * Minimum allowed number of bytes to read from STDIN.
     *
     * Used to prevent unsafe or inefficiently small input buffers.
     * Equal to 1,023 bytes (2^10 - 1),
     * which aligns with typical system limits.
     *
     * @var int
     */
    private const STD_IN_BYTE_MIN_LENGTH = (2 ** 10) - 1;

    /**
     * URI to open STDIN stream for reading.
     *
     * @var string
     */
    private const STD_IN_STREAM_URI = 'php://stdin';

    /**
     * Initializes the STDIN stream and sets the read byte limit.
     *
     * This constructor prepares the class to receive input via STDIN
     * using `fopen()` in read mode. It also configures the maximum
     * number of bytes to read per line using `fgets()`.
     *
     * If the provided `$length` is `null`, the default limit of
     * 8192 bytes is used. Unsafe values are automatically corrected.
     *
     * If the STDIN stream cannot be opened, an error is printed
     * to STDERR and the process exits immediately.
     *
     * @param int|null $length Optional byte limit for STDIN reads.
     */
    public function __construct(?int $length = null)
    {
        // Set safe input read limit for STDIN
        $this->setStdInByteLength(
            $length !== null ? $length : self::STD_IN_BYTE_LENGTH
        );

        $this->stdInStream = fopen(self::STD_IN_STREAM_URI, 'r');

        if (!is_resource($this->stdInStream)) {
            fwrite(STDERR, 'Failed to open stdin stream' . PHP_EOL);
            exit(1);
        }
    }

    /**
     * Returns the maximum number of bytes allowed
     * when reading from STDIN.
     *
     * This value is typically used as the `$length` parameter
     * in `fgets()` to prevent buffer overflows
     * and oversized WebSocket messages.
     *
     * @return int Number of bytes allowed (default: 8192)
     */
    final public function getStdInByteLength(): int
    {
        return $this->stdInByteLength;
    }

    /**
     * Sets the maximum number of bytes
     * to read from STDIN using fgets().
     *
     * If the given length is lower than 1 KiB - 1 byte (1023 bytes)
     * or greater than 2 GiB - 1 byte (2,147,483,647 bytes),
     * it is considered unsafe or impractical,
     * and the value is automatically reset to the default
     * of 8192 bytes.
     *
     * In such cases, a warning is written to STDERR
     * instead of throwing an exception,
     * for compatibility with WebSocketd and CLI servers.
     *
     * @param int $length
     *     Number of bytes to allow when reading from STDIN.
     *     Must be in the range [1023, 2147483647].
     *
     * @return void
     */
    public function setStdInByteLength(int $length): void
    {
        // Segure range: 1023 ≤ $length ≤ 2 GiB - 1
        if (
            $length < self::STD_IN_BYTE_MIN_LENGTH
            || $length > self::STD_IN_BYTE_MAX_LENGTH
        ) {
            fwrite(
                STDERR,
                'Warning: STDIN byte length must be between'
                . ' 1023 and 2 GiB - 1. Resetting to default (8192 bytes).'
                . PHP_EOL
            );

            $this->stdInByteLength = self::STD_IN_BYTE_LENGTH; // 8192

            return;
        }

        $this->stdInByteLength = $length;
    }

    /**
     * Starts the main WebSocket loop with optional STDIN byte limit.
     *
     * Continuously reads STDIN lines (via stream_select),
     * parses input as JSON, and delegates to insert/update logic
     * depending on the 'action' key.
     * Also periodically emits any new messages from the backend.
     *
     * If `$length` is `null`, the default limit of 8192 bytes is used.
     * Otherwise, it defines the maximum number of bytes
     * to read from STDIN per line.
     * Out-of-range values are reset to the default.
     *
     * @param int|null $length
     *     Optional maximum number of bytes to read
     *     per STDIN line (default: 8192).
     *
     * @return void
     */
    final public function run(?int $length = null): void
    {
        $this->checkExtensions();

        // Set safe input read limit for STDIN
        $this->setStdInByteLength(
            $length !== null ? $length : self::STD_IN_BYTE_LENGTH
        );

        while (true) {
            $this->read = [ $this->stdInStream ];
            //$this->stdInEntry = false;
            $this->write = $this->except = [];
            $this->hasInput = stream_select(
                $this->read,
                $this->write,
                $this->except,
                self::SECONDS,
                self::MICRO_SECONDS
            );

            if (
                $this->hasInput
                && in_array($this->stdInStream, $this->read)
            ) {
                try {
                    // Read up to 8192 bytes max from STDIN
                    if (
                        !($this->stdInEntry = fgets(
                            $this->stdInStream,
                            $this->stdInByteLength + 1
                        ))
                    ) {
                        continue 1;
                    }

                    /*
                     * Reject overly large input
                     * (protection against abuse or attack).
                     */
                    if (strlen($this->stdInEntry) > $this->stdInByteLength) {
                        fwrite(
                            STDERR,
                            'Warning: STDIN entry exceeds 8192 bytes,'
                            . ' ignoring'
                            . PHP_EOL
                        );

                        continue 1;
                    }

                    /*
                     * Decode JSON and validate ID fields
                     * as non-negative integer strings.
                     */
                    $this->jsonDecoded =
                        JsonCoder::decode($this->stdInEntry);
                    if (!is_array($this->jsonDecoded)) {
                        continue 1;
                    }

                    $this->action = $this->jsonDecoded['action'] ?? '';

                    if ($this->action === 'insert') {
                        $this->dataBaseObj->setMsgEntry($this->jsonDecoded);

                        if ($this->dataBaseObj->insertMsgEntry()) {
                            $this->dataBaseObj->printAllMsgEntries();
                        }
                    } elseif ($this->action === 'update') {
                        $this->dataBaseObj->setMsgEntry($this->jsonDecoded);

                        if ($this->dataBaseObj->updateMsgEntry()) {
                            $this->dataBaseObj->printMsgEntry();
                        }
                    }
                } catch (Throwable $e) {
                    /*
                     * Catch any runtime errors from input processing
                     * (JSON decoding, logic, file access)
                     * and continue to the next WebSocket message
                     * in the main loop.
                     */
                    fwrite(
                        STDERR,
                        'Runtime error: ' . $e->getMessage() . PHP_EOL
                    );
                }
            } else {
                try {
                    $this->dataBaseObj->printAllMsgEntries();
                } catch (Throwable $e) {
                    /*
                     * Catch errors during log file monitoring
                     * (read failures, decoding, etc.)
                     * and continue watching for new entries
                     * without crashing the main loop.
                     */
                    fwrite(
                        STDERR,
                        'Log read error: ' . $e->getMessage() . PHP_EOL
                    );
                }
            }
        }
    }

    /**
     * Checks for required PHP extensions
     * and enters passive wait mode if missing.
     *
     * This method verifies whether essential PHP extensions
     * (such as `bcmath`) are loaded.
     * If any required extensions are not available,
     * it writes an error message to STDERR
     * and enters a passive loop reading from STDIN.
     *
     * This design avoids terminating the process,
     * allowing the script to remain connected under websocketd
     * without crashing or exiting unexpectedly.
     *
     * @return void
     */
    final private function checkExtensions(): void
    {
        $this->missingExt =
            Extension::detectMissing(self::REQUIRED_EXTENSIONS);

        if (!empty($this->missingExt)) {
            fwrite(
                STDERR,
                "ERROR: Missing required PHP extensions: "
                . implode(', ', $this->missingExt) . PHP_EOL
            );

            while (fgets(STDIN) !== false) {
                // Do nothing
            }
        }
    }

    /**
     * Last detected action name in input JSON
     * ('insert', 'update', etc).
     *
     * @var string
     */
    private string $action = '';

    /**
     * Placeholder arrays for exception streams in `stream_select`.
     *
     * @var array<int, mixed>
     */
    private array $except = [];

    /**
     * Result of the last stream_select call.
     * Indicates if there was input available (int)
     * or false on failure.
     *
     * @var int|false
     */
    private $hasInput = false;

    /**
     * Last decoded JSON object from STDIN.
     * Should be an associative array on success, or null on failure.
     *
     * @var array<string, mixed>|null
     */
    private ?array $jsonDecoded = null;

    /**
     * List of missing PHP extensions required for proper operation.
     *
     * Populated during internal checks
     * (example: in checkExtensions()) to record
     * which essential PHP extensions
     * are not loaded in the current environment.
     * If non-empty, the server may enter a passive wait state
     * to avoid failure.
     *
     * @var string[]
     */
    private array $missingExt = [];

    /**
     * Streams to monitor with stream_select for read availability.
     *
     * @var array<int, resource|false>
     */
    private array $read = [];

    /**
     * Last raw line read from STDIN.
     *
     * @var string|false
     */
    private $stdInEntry = false;

    /**
     * Maximum number of bytes to read from STDIN using fgets().
     *
     * This limit prevents buffer overflows
     * and abuse via oversized input.
     * Defaults to 8192 bytes (2^13),
     * defined by self::STD_IN_BYTE_LENGTH.
     * Matches the internal default buffer size for line reading.
     *
     * Used as the `$length` parameter in:
     *     fgets($this->stdInStream, $this->stdInByteLength + 1)
     *
     * @var int
     */
    private int $stdInByteLength = self::STD_IN_BYTE_LENGTH;

    /**
     * Stream resource for STDIN.
     *
     * @var resource|false
     */
    private $stdInStream = false;

    /**
     * Placeholder arrays for write streams in `stream_select`.
     *
     * @var mixed[]
     */
    private array $write = [];
}
