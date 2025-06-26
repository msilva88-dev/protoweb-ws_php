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

/**
 * WebSocket relay server for JSON messages.
 *
 * - Accepts messages via STDIN
 * - Saves messages in flat file logs
 * - Emits real-time updates via STDOUT
 * - Supports user mapping and message modification
 *
 * Optimized for use with websocketd.
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat
 * @since 2025
 * @subpackage Server
 * @version 1.0
 */

declare(strict_types=1);

namespace ProtoWeb\DynamicChat\Library {

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

    /**
     * Prevents instantiation of this static utility class.
     */
    private function __construct()
    {
        // Static-only class
    }
}

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
     * Returns an array of missing PHP extensions.
     *
     * Iterates over the provided list
     * and checks if each extension is loaded.
     * Invalid names (non-strings) are also considered missing.
     *
     * @param string[] $extensions
     *     List of required extension names.
     *
     * @return string[]
     *     List of missing extensions (empty if all are loaded)
     */
    final public static function getMissing(array $extensions): array
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

    /**
     * Prevents instantiation of this static utility class.
     */
    private function __construct()
    {
        // Static-only class
    }
}

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
     * Error code indicating invalid JSON input or output.
     *
     * This is set when json_decode fails to produce a valid array,
     * or json_encode fails to generate a valid string.
     *
     * @var int
     */
    private const ERROR_INVIO = 1;

    /**
     * Error code indicating invalid ID field(s).
     *
     * This occurs when one or more 'id' or '*_id' values are negative,
     * not strictly integer-formatted, or contain invalid characters.
     *
     * @var int
     */
    private const ERROR_INVID = 2;

    /**
     * Error code indicating a required PHP extension is missing.
     *
     * Set when 'ctype' or 'json' extensions are not loaded in PHP.
     *
     * @var int
     */
    private const ERROR_EXT = 3;

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
     * @param int &$error
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
        int &$error = 0
    ): ?array {
        // Validate extensions
        if (!Extension::validate(['ctype', 'json'])) {
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
     * @param int &$error
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
        int &$error = 0
    ): string {
        // Validate extensions
        if (!Extension::validate(['ctype', 'json'])) {
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

    /**
     * Prevents instantiation of this utility class.
     */
    private function __construct()
    {
        // Static-only class
    }
}
} namespace ProtoWeb\DynamicChat\DataBase {
/**
 * Interface ChatDataBaseInterface
 *
 * Defines the contract for chat database operations.
 * Responsible for handling message insertion, update, deletion,
 * and user metadata resolution in the backend (example: file, DB).
 *
 * Implementations must support JSON message flow
 * compatible with WebSocket streams.
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
interface ChatDataBaseInterface
{
    /**
     * Deletes a message entry (not yet implemented).
     *
     * @return bool Always false (placeholder).
     */
    public function deleteMsgEntry(): bool;

    /**
     * Retrieves the current message entry.
     *
     * @return array<string, int|string>
     *     Associative array representing the message.
     */
    public function getMsgEntry(): array;

    /**
     * Validates and inserts a message into the backend storage.
     *
     * @return bool True on success, false on failure.
     */
    public function insertMsgEntry(): bool;

    /**
     * Prints all new messages appended since the last read operation.
     *
     * @return bool True if new messages were printed, false otherwise.
     */
    public function printAllMsgEntries(): bool;

    /**
     * Prints the current message entry as a JSON string.
     *
     * @return bool True if printed successfully, false otherwise.
     */
    public function printMsgEntry(): bool;

    /**
     * Sets the current message entry.
     *
     * @param array<string, int|string> $msgEntry
     *     Associative array containing message data.
     *
     * @return void
     */
    public function setMsgEntry(array $msgEntry): void;

    /**
     * Validates and updates an existing message
     * in the backend storage.
     *
     * @return bool True on success, false on failure.
     */
    public function updateMsgEntry(): bool;
}

use ProtoWeb\DynamicChat\Library\Extension;

/**
 * Class ChatDataBaseBase
 *
 * Abstract base class that implements validation logic
 * and interface stubs for chat message handling.
 *
 * Provides shared logic for validating, inserting, updating,
 * and serializing chat messages. Specific implementations
 * must be provided by a subclass using a backend
 * (example: file or database).
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @implements ChatDataBaseInterface
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\DataBase
 * @since 2025
 * @version 1.0
 */
abstract class ChatDataBaseBase implements ChatDataBaseInterface
{
    /**
     * Internal message entry data.
     *
     * Holds the current chat message in associative array form.
     *
     * @var array<string, int|string> Current message entry data.
     */
    private array $msgEntry = [];

    /**
     * Maximum value of a signed 64-bit integer (SQL BIGINT).
     *
     * Used for validation of large numeric IDs stored as strings.
     *
     * @var string
     */
    private const SQL_BIGINT_MAX = '9223372036854775807';

    /**
     * Validates a message entry against a set of rules.
     *
     * @param array<string, mixed> $rules
     *     Validation rules as field => rule string.
     *     Allowed rules:
     *         - integer_positive
     *         - integer_positive_or_zero
     *         - intstr_positive (requires BCMath)
     *         - intstr_positive_or_zero (requires BCMath)
     *         - string_non_empty
     *
     * @return bool True
     *     if all fields pass validation, false otherwise.
     */
    final private function validateMsgEntry(array $rules): bool
    {
        $msgEntry = $this->getMsgEntry();

        foreach ($rules as $field => $rule) {
            if (!isset($msgEntry[$field])) {
                return false;
            }

            switch ($rule) {
                case 'integer_positive':
                    if (is_int($msgEntry[$field]) && $msgEntry[$field] <= 0) {
                        return false;
                    }

                    break;
                case 'integer_positive_or_zero':
                    if (is_int($msgEntry[$field]) && $msgEntry[$field] < 0) {
                        return false;
                    }

                    break;
                case 'intstr_positive':
                    // Validate extensions
                    if (!Extension::validate(['bcmath', 'ctype'])) {
                        return false;
                    }

                    if (
                        !is_string($msgEntry[$field])
                        || !ctype_digit($msgEntry[$field])
                        || bccomp($msgEntry[$field], '1') < 0
                        || bccomp($msgEntry[$field], self::SQL_BIGINT_MAX) > 0
                    ) {
                        return false;
                    }

                    break;
                case 'intstr_positive_or_zero':
                    // Validate extensions
                    if (!Extension::validate(['bcmath', 'ctype'])) {
                        return false;
                    }

                    if (
                        !is_string($msgEntry[$field])
                        || !ctype_digit($msgEntry[$field])
                        || bccomp($msgEntry[$field], self::SQL_BIGINT_MAX) > 0
                    ) {
                        return false;
                    }

                    break;
                case 'string_non_empty':
                    if (
                        !is_string($msgEntry[$field])
                        || trim($msgEntry[$field]) === ''
                    ) {
                        return false;
                    }

                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    /**
     * Checks if the current message entry is valid for insertion.
     *
     * @return bool True if valid for insert, false otherwise.
     */
    final private function validateInsertMsgEntry(): bool
    {
        return $this->validateMsgEntry([
            'booking_no' => 'string_non_empty',
            'message' => 'string_non_empty',
            'receiver_id' => 'intstr_positive_or_zero',
            'sender_id' => 'intstr_positive'
        ]);
    }

    /**
     * Checks if the current message entry is valid for update.
     *
     * @return bool True if valid for update, false otherwise.
     */
    final private function validateUpdateMsgEntry(): bool
    {
        return $this->validateMsgEntry([
            'id' => 'intstr_positive',
            'message' => 'string_non_empty',
            'sender_id' => 'intstr_positive'
        ]);
    }

    /**
     * Inserts the current message into the database
     * (to be implemented by subclass).
     *
     * @return bool True on success, false otherwise.
     */
    abstract protected function insertMsgEntryToDB(): bool;

    /**
     * Inserts the sender's user data into the database
     * (to be implemented by subclass).
     *
     * @return void
     */
    abstract protected function insertUserDataToDB(): void;

    /**
     * Normalizes the receiver_id field in the current message
     * (to be implemented by subclass).
     *
     * @return void
     */
    abstract protected function normalizeReceiverIdFromMsgEntry(): void;

    /**
     * Prints all new messages from database
     * (to be implemented by subclass).
     *
     * @return bool True on success, false otherwise.
     */
    abstract protected function printAllJsonMsgsFromDB(): bool;

    /**
     * Prints current message entry as JSON
     * (to be implemented by subclass).
     *
     * @return bool True on success, false otherwise.
     */
    abstract protected function printJsonMsgFromMsgEntry(): bool;

    /**
     * Restores user names into the current message
     * (to be implemented by subclass).
     *
     * @return void
     */
    abstract protected function restoreUserNameFromUserData(): void;

    /**
     * Checks if the sender already exists in the user map
     * (to be implemented by subclass).
     *
     * @return bool
     *     True if name exists under another ID, false otherwise.
     */
    abstract protected function selectUserDataFromDB(): bool;

    /**
     * Updates an existing message in the database
     * (to be implemented by subclass).
     *
     * @return bool True on success, false otherwise.
     */
    abstract protected function updateMsgEntryToDB(): bool;

    /**
     * Not implemented. Placeholder for deleting message.
     *
     * @return bool Always false (not implemented).
     */
    final public function deleteMsgEntry(): bool
    {
        return false; // draft
    }

    /**
     * Returns the current message entry array.
     *
     * @return array Associative array representing a message.
     */
    final public function getMsgEntry(): array
    {
        return $this->msgEntry;
    }

    /**
     * Inserts the message entry after validation and user checks.
     *
     * @return bool True on success, false otherwise.
     */
    final public function insertMsgEntry(): bool
    {
        if ($this->validateInsertMsgEntry()) {
            $this->normalizeReceiverIdFromMsgEntry();

            if (!$this->selectUserDataFromDB()) {
                $this->insertUserDataToDB();
            }

            return $this->insertMsgEntryToDB();
        } else {
            return false;
        }
    }

    /**
     * Triggers printing of all new message entries.
     *
     * @return bool True if output occurred, false otherwise.
     */
    final public function printAllMsgEntries(): bool
    {
        return $this->printAllJsonMsgsFromDB();
    }

    /**
     * Restores and prints the current message.
     *
     * @return bool True if output succeeded, false otherwise.
     */
    final public function printMsgEntry(): bool
    {
        $this->restoreUserNameFromUserData();

        return $this->printJsonMsgFromMsgEntry();
    }

    /**
     * Sets the internal message entry.
     *
     * @param array $msgEntry Message entry array.
     *
     * @return void
     */
    final public function setMsgEntry(array $msgEntry): void
    {
        $this->msgEntry = $msgEntry;
    }

    /**
     * Updates a message entry after validation.
     *
     * @return bool True on success, false otherwise.
     */
    final public function updateMsgEntry(): bool
    {
        if ($this->validateUpdateMsgEntry()) {
            return $this->updateMsgEntryToDB();
        } else {
            return false;
        }
    }
}

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

use ProtoWeb\DynamicChat\Library\DataNormalizer;
// use ProtoWeb\DynamicChat\Library\JsonCoder;

/**
 * Path to the message log file used to store chat entries.
 *
 * @var string
 */
define('MSG_LOG_FILE', '/tmp/simplefilechat.log');

/**
 * Path to the user map file
 * used to store sender_id => sender_name mappings.
 *
 * @var string
 */
define('USER_MAP_FILE', '/tmp/simplefilechat_users.json');

/**
 * Class ChatDataBaseFile
 *
 * Implements a file-based chat database backend by extending
 * ChatDataBaseBase and using JSON and plain-text files for storage.
 *
 * Manages chat messages in a newline-delimited JSON log file,
 * and user identifiers in a separate JSON map.
 *
 * This implementation is suitable for lightweight
 * or local chat systems without requiring external databases.
 *
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @extends ChatDataBaseBase
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\DataBase
 * @since 2025
 * @version 1.0
 */
final class ChatDataBaseFile extends ChatDataBaseBase
{
    use ChatDataBaseTrait;

    /**
     * Tracks the last byte offset read from the message log file.
     *
     * Used by the emitter to avoid reprocessing previously emitted
     * messages during incremental reads.
     *
     * @var int
     */
    private int $lastSize = 0;

    /**
     * Tracks the highest message ID found in the message log.
     *
     * This property is updated during message insertion to ensure
     * that each new message receives a unique and sequential ID.
     *
     * @var string
     */
    private string $maxId = '0';

    /**
     * Path to the log file used to store chat messages.
     *
     * Defaults to MSG_LOG_FILE if not explicitly set.
     *
     * @var string
     */
    private string $msgLogFile = MSG_LOG_FILE;

    /**
     * Associative array mapping sender/receiver IDs to usernames.
     *
     * @var string[]
     */
    private array $userMap = [];

    /**
     * Path to the file storing the user map in JSON.
     *
     * Defaults to USER_MAP_FILE if not explicitly set.
     *
     * @var string
     */
    private string $userMapFile = USER_MAP_FILE;

    /**
     * Attempts to acquire an exclusive non-blocking lock
     * on a file pointer.
     *
     * @param resource|null $fp File pointer from fopen().
     * @param int $delayMicroseconds Microseconds between retries.
     * @param int $initial Internal use (retry loop counter).
     * @param int $retries Maximum number of attempts.
     *
     * @return bool True on successful lock, false otherwise.
     */
    final private function lockWithRetries(
        /* ?resource */ $fp,
        int $delayMicroseconds = 10000,
        int $initial = 0,
        int $retries = 5
    ): bool {
        if (!is_resource($fp)) {
            fwrite(
                STDERR,
                'Invalid file pointer provided to lockWithRetries.' . PHP_EOL
            );

            return false;
        }

        for ($initial = 0; $initial < $retries; $initial++) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                return true;
            }

            // Short wait before the next attempt
            usleep($delayMicroseconds);
        }

        return false;
    }

    /**
     * Atomically writes the updated log entries to storage.
     *
     * It writes the given array of JSON-encoded strings
     * to a temporary file, then replaces the original log file
     * with the new one using `rename()`.
     * If any step fails, logs the error to STDERR and returns false.
     *
     * @param array<int, array<string, int|string>> $msgLog
     *     Array of JSON-encoded messages.
     *
     * @return bool
     *     True on success, false on failure.
     */
    final private function writeUpdatedMsgEntryToDB(array $msgLog): bool
    {
        $tempFile = $this->msgLogFile . '.tmp';

        try {
            if (($fp = fopen($tempFile, 'w')) !== false) {
                foreach ($msgLog as $msgEntry) {
                    fwrite($fp, $msgEntry . PHP_EOL);
                }

                fflush($fp);
                fclose($fp);

                /*
                 * Atomic replace,
                 * no LOCK needed because rename is atomic in POSIX.
                 */
                rename($tempFile, $this->msgLogFile);

                return true;
            }
        } catch (Throwable $e) {
            @unlink($tempFile);
            fwrite(
                STDERR,
                'Error in writeUpdatedMsgEntryToDB: '
                . $e->getMessage()
                . PHP_EOL
            );
        }

        return false;
    }

    /**
     * Appends a new message entry to the log file,
     * assigning a unique sequential ID based on existing entries.
     *
     * It reads the current log to find the highest ID,
     * appends a new JSON-encoded line at the end with an incremented ID,
     * and ensures file safety using an exclusive lock.
     *
     * @return bool True on success, false on failure.
     */
    final protected function insertMsgEntryToDB(): bool
    {
        try {
            if (($fp = fopen($this->msgLogFile, 'c+')) !== false) {
                if ($this->lockWithRetries($fp)) {
                    $this->maxId = '0';

                    while (($this->stdInEntry = fgets($fp))) {
                        /*
                         * Decode JSON and validate ID fields
                         * as non-negative integer strings.
                         */
                        $jsonDecoded =
                            JsonCoder::decode($this->stdInEntry);

                        if (
                            isset($jsonDecoded['id'])
                            && $jsonDecoded['id'] !== null
                        ) {
                            if (
                                bccomp($jsonDecoded['id'], $this->maxId) > 0
                            ) {
                                $this->maxId = $jsonDecoded['id'];
                            }
                        }
                    }

                    $newId = bcadd($this->maxId, '1');
                    $msgEntry = $this->getMsgEntry();

                    /*
                     * Revalidating the IDs here is unnecessary
                     * because $this->validateInsertMsgEntry()
                     * is invoked in insertMsgEntry()
                     * prior to this method.
                     */

                    $msgEntrySorted = [
                        'id' => $newId,
                        'booking_no' => $msgEntry['booking_no'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'receiver_id' => $msgEntry['receiver_id'],
                        'sender_id' => $msgEntry['sender_id'],
                        'message' => $msgEntry['message']
                    ];

                    /*
                     * Validate ID fields as non-negative values,
                     * normalize to int or numeric string
                     * within PHP int range,
                     * then encode data to JSON.
                     */
                    $json = JsonCoder::encode($msgEntrySorted);

                    fseek($fp, 0, SEEK_END);

                    if ($json !== false) {
                        fwrite($fp, $json . PHP_EOL);
                    } else {
                        fwrite(
                            STDERR,
                            'JSON encode error (insert): '
                            . json_last_error_msg() . PHP_EOL
                        );
                    }

                    fflush($fp);
                    flock($fp, LOCK_UN);
                }

                fclose($fp);

                return true;
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Error in insertMsgEntry: ' . $e->getMessage() . PHP_EOL
            );
        }

        return false;
    }

    /**
     * Updates the user map file
     * with the current sender_id => sender_name mapping.
     *
     * It appends or updates the in-memory `userMap` array,
     * then writes it to storage as a pretty-printed JSON file.
     * The file is locked during the operation
     * to avoid concurrent access issues.
     *
     * @return void
     */
    final protected function insertUserDataToDB(): void
    {
        $msgEntry = $this->getMsgEntry();

        /*
         * Revalidating the IDs here is unnecessary
         * because $this->validateInsertMsgEntry() is invoked
         * in insertMsgEntry() prior to this method.
         */

        // Trim this non-verified sender name
        DataNormalizer::string($msgEntry['sender_name']);

        // Store sender name by ID as string key
        $this->userMap[$msgEntry['sender_id']] = $msgEntry['sender_name'];

        try {
            if (($fp = fopen($this->userMapFile, 'c+')) !== false) {
                if ($this->lockWithRetries($fp)) {
                    $json = json_encode($this->userMap, JSON_PRETTY_PRINT);

                    if ($json !== false) {
                        ftruncate($fp, 0);
                        rewind($fp);
                        fwrite($fp, $json);
                        fflush($fp);
                    } else {
                        fwrite(
                            STDERR,
                            'JSON encode error (userData): '
                            . json_last_error_msg() . PHP_EOL
                        );
                    }

                    flock($fp, LOCK_UN);
                }

                fclose($fp);
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Unexpected error while updating user map file: '
                . $e->getMessage()
                . PHP_EOL
            );
        }
    }

    /**
     * Resolves and normalizes receiver_id
     * based on user map and receiver_name.
     * If not valid, defaults to 0 (public/broadcast).
     *
     * @return void
     */
    final protected function normalizeReceiverIdFromMsgEntry(): void
    {
        $msgEntry = $this->getMsgEntry();

        /*
         * Revalidating the IDs here is unnecessary
         * because $this->validateInsertMsgEntry() is invoked
         * in insertMsgEntry() prior to this method.
         */

        // Trim these non-verified receiver and sender name
        DataNormalizer::string($msgEntry['receiver_name']);
        DataNormalizer::string($msgEntry['sender_name']);

        // If receiver does not exist or is same as sender, set to 0
        if (
            !isset($this->userMap[$msgEntry['receiver_id']])
            || $msgEntry['receiver_id'] === $msgEntry['sender_id']
        ) {
            $msgEntry['receiver_id'] = '0';

            $this->setMsgEntry($msgEntry);

            return;
        }

        /*
         * Try to resolve by receiver name
         * if provided and different from sender.
         */
        if (
            $msgEntry['receiver_name'] !== ''
            && $msgEntry['receiver_name'] !== $msgEntry['sender_name']
        ) {
            foreach ($this->userMap as $id => $name) {
                if (strcasecmp($name, $msgEntry['receiver_name']) === 0) {
                    $msgEntry['receiver_id'] = $id;

                    $this->setMsgEntry($msgEntry);

                    return;
                }
            }
        }
    }

    /**
     * Prints only new messages that were appended since the last read.
     *
     * This method checks for changes in the message log file's size.
     * If new data is available,
     * it reads the new entries from the file, decodes each JSON line,
     * and emits them via `printMsgEntry()`.
     *
     * Updates the internal `$lastSize` tracker after processing.
     *
     * @return bool True if new data was emitted, false otherwise.
     */
    final protected function printAllJsonMsgsFromDB(): bool
    {
        clearstatcache(true, $this->msgLogFile);

        if (($currentSize = filesize($this->msgLogFile)) <= $this->lastSize) {
            return false;
        }

        try {
            if (($fp = fopen($this->msgLogFile, 'r')) !== false) {
                // jump to where we left off
                if (fseek($fp, $this->lastSize) !== 0) {
                    fwrite(
                        STDERR,
                        'Failed to seek to last read position in log file'
                        . PHP_EOL
                    );
                    fclose($fp);

                    return false;
                }

                while (($this->stdInEntry = fgets($fp))) {
                    /*
                     * Decode JSON and validate ID fields
                     * as non-negative integer strings.
                     */
                    $jsonDecoded = JsonCoder::decode($this->stdInEntry);

                    if (is_array($jsonDecoded)) {
                        $this->setMsgEntry($jsonDecoded);
                        $this->printMsgEntry();
                    }
                }

                fclose($fp);

                $this->lastSize = $currentSize;

                return true;
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Error in printAllMsgEntries: ' . $e->getMessage() . PHP_EOL
            );
        }

        return false;
    }

    /**
     * Populates sender_name and receiver_name fields
     * based on user map.
     *
     * @return void
     */
    final protected function restoreUserNameFromUserData(): void
    {
        $msgEntry = $this->getMsgEntry();

        // Restore receiver_name from userMap
        if (
            isset($this->userMap[((string)$msgEntry['receiver_id'] ?? '')])
        ) {
            $msgEntry['receiver_name'] =
                $this->userMap[(string)$msgEntry['receiver_id']];
        }

        // Restore sender_name from userMap
        $msgEntry['sender_name'] =
            $this->userMap[(string)($msgEntry['sender_id'] ?? '')]
            ?? 'guest';

        $this->setMsgEntry($msgEntry);
    }

    /**
     * Checks if sender_name is already registered to a different ID.
     *
     * @return bool True if name exists with a different ID.
     */
    final protected function selectUserDataFromDB(): bool
    {
        $msgEntry = $this->getMsgEntry();

        /*
         * Revalidating the IDs here is unnecessary
         * because $this->validateInsertMsgEntry() is invoked
         * in insertMsgEntry() prior to this method.
         */

        // Trim this non-verified sender name
        DataNormalizer::string($msgEntry['sender_name']);

        foreach ($this->userMap as $id => $name) {
            if (
                strcasecmp($name, $msgEntry['sender_name']) === 0
                && bccomp((string)$id, $msgEntry['sender_id']) !== 0
            ) {
                // User name is already taken by another user
                return true;
            }
        }

        // User does not exist from database
        return false;
    }

    /**
     * Finds and replaces an existing message
     * in the log with updated content.
     *
     * This method searches the log file for a message
     * matching the given `id` and `sender_id`,
     * updates the `message` field, and writes the full log
     * back to storage in-place using an atomic temporary file.
     *
     * The current `$msgEntry` is updated
     * to reflect the modified message if successful.
     *
     * @return bool
     *     True on successful update,
     *     false if the message was not found,
     *     JSON encoding failed, or file operations failed.
     */
    final protected function updateMsgEntryToDB(): bool
    {
        $msgEntry = $this->getMsgEntry();

        try {
            $msgLog = file(
                $this->msgLogFile,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );

            if (!is_array($msgLog)) {
                fwrite(
                    STDERR,
                    'Unable to read log file into memory for message update.'
                    . PHP_EOL
                );

                return false;
            }

            foreach ($msgLog as $i => $msgData) {
                /*
                 * Decode JSON and validate ID fields
                 * as non-negative integer strings.
                 */
                $jsonDecoded = JsonCoder::decode($msgData);

                /*
                 * Revalidating the IDs here is unnecessary
                 * because $this->validateUpdateMsgEntry() is invoked
                 * in updateMsgEntry() prior to this method.
                 */

                if (
                    is_array($jsonDecoded)
                    && isset($jsonDecoded['id'], $jsonDecoded['sender_id'])
                    && $jsonDecoded['id'] === $msgEntry['id']
                    && $jsonDecoded['sender_id'] === $msgEntry['sender_id']
                ) {
                    $jsonDecoded['message'] = $msgEntry['message'];

                    /*
                     * Validate ID fields as non-negative values,
                     * normalize to int or numeric string
                     * within PHP int range,
                     * then encode data to JSON.
                     */
                    $json = JsonCoder::encode($jsonDecoded);

                    if ($json !== false) {
                        $msgLog[$i] = $json;

                        $this->setMsgEntry($jsonDecoded);

                        return $this->writeUpdatedMsgEntryToDB($msgLog);
                    } else {
                        fwrite(
                            STDERR,
                            'Failed to encode updated message for log write: '
                            . json_last_error_msg() . PHP_EOL
                        );
                    }

                    break;
                }
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Error in updateMsgEntryToDB: ' . $e->getMessage() . PHP_EOL
            );
        }

        return false;
    }

    /**
     * Getter for the current message log file path.
     *
     * @return string
     */
    final public function getMsgLogFile(): string
    {
        return $this->msgLogFile;
    }

    /**
     * Getter for the current user map file path.
     *
     * @return string
     */
    final public function getUserMapFile(): string
    {
        return $this->userMapFile;
    }

    /**
     * Sets the log file path, creating the file if it does not exist.
     *
     * This method ensures the log file exists before use.
     * If file creation fails, it writes the error to STDERR and exits.
     *
     * @param string $msgLogFile
     *     Absolute or relative path to the message log file.
     *
     * @return void
     */
    final public function setMsgLogFile(string $msgLogFile): void
    {
        $this->msgLogFile = $msgLogFile;

        try {
            if (!file_exists($this->msgLogFile)) {
                touch($this->msgLogFile);
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Error initializing message log file: '
                 . $e->getMessage()
                 . PHP_EOL
            );
            exit(1);
        }
    }

    /**
     * Sets and initializes the user map file path.
     *
     * This method sets the file path used to store the user map
     * (associating sender/receiver IDs with user names).
     *
     * If the file does not exist, it is created with an empty
     * JSON object (`{}`), formatted for readability.
     *
     * The file is then read and parsed into an associative array,
     * which is stored internally in `$this->userMap`.
     * If reading or decoding fails, an empty map is used instead.
     *
     * Any I/O or decoding errors are logged to STDERR.
     * Fatal errors during initialization cause the process to exit.
     *
     * @param string $userMapFile
     *     Absolute or relative path to the user map JSON file.
     *
     * @return void
     */
    final public function setUserMapFile(string $userMapFile): void
    {
        $this->userMapFile = $userMapFile;

        try {
            if (!file_exists($this->userMapFile)) {
                file_put_contents($this->userMapFile, json_encode(
                    [],
                    JSON_PRETTY_PRINT
                ));
            }

            if (!($content = file_get_contents($this->userMapFile))) {
                fwrite(STDERR, 'Error reading user map file.' . PHP_EOL);

                $this->userMap = [];
            } elseif (
                !is_array($jsonDecoded = JsonCoder::decode($content))
            ) {
                fwrite(STDERR, 'Error decoding user map JSON.' . PHP_EOL);

                $this->userMap = [];
            } else {
                $this->userMap = $jsonDecoded;
            }
        } catch (Throwable $e) {
            fwrite(
                STDERR,
                'Error initializing user map file: '
                . $e->getMessage()
                . PHP_EOL
            );
            exit(1);
        }
    }

    /**
     * ChatDataBaseFile constructor.
     * Initializes log and user map file paths from configuration.
     *
     * @param array<string, string> $config
     *     Optional associative array with keys
     *     'msgLogFile' and 'userMapFile'.
     */
    final public function __construct(array $config = [])
    {
        $this->setMsgLogFile($config['msgLogFile'] ?? MSG_LOG_FILE);
        $this->setUserMapFile($config['userMapFile'] ?? USER_MAP_FILE);
    }
}

// use ProtoWeb\DynamicChat\Library\DataNormalizer;
// use ProtoWeb\DynamicChat\Library\JsonCoder;

/**
 * ...
 *
 * @var string
 */
define('CI3_INSTANCE_PATH', '/srv/http/codeigniter3/index.php');

/**
 * Class ChatDataBaseCI3
 *
 * ...
 *
 * @extends ChatDataBaseBase
 *
 * @package ProtoWeb\DynamicChat\DataBase
 */
final class ChatDataBaseCI3 extends ChatDataBaseBase
{
    use ChatDataBaseTrait;

    /**
     * CI3 model instance for chat operations.
     *
     * @var object
     */
    private ?object $chatModel = null;

    /**
     * Appends a new message entry to the db,
     * assigning a unique sequential ID based on existing entries.
     *
     * It reads the current db to find the highest ID,
     * appends a new JSON-encoded line at the end with an incremented ID,
     * and ensures file safety using an exclusive lock.
     *
     * @return bool True on success, false on failure.
     */
    final protected function insertMsgEntryToDB(): bool
    {
        $msgEntry = $this->getMsgEntry();

        return $this->chatModel->insertMessage($msgEntry);
    }

    /**
     * Updates the database from CodeIgniter 3 Model
     * with the current sender_id => sender_name mapping.
     *
     * ...
     *
     * @return void
     */
    final protected function insertUserDataToDB(): void
    {
        $msgEntry = $this->getMsgEntry();

        // Trim this non-verified sender name
        DataNormalizer::string($msgEntry['sender_name']);

        $this->chatModel->registerUserIfNeeded(
            $msgEntry['sender_id'],
            $msgEntry['sender_name']
        );
    }

    /**
     * Resolves and normalizes receiver_id
     * based on CodeIngniter3 Model and receiver_name.
     * If not valid, defaults to 0 (public/broadcast).
     *
     * @return void
     */
    final protected function normalizeReceiverIdFromMsgEntry(): void
    {
        $msgEntry = $this->getMsgEntry();

        // Trim these non-verified receiver and sender name
        DataNormalizer::string($msgEntry['receiver_name']);
        DataNormalizer::string($msgEntry['sender_name']);

        // If receiver does not exist or is same as sender, set to 0
        if (
            $this->chatModel->checkUserID((int)$msgEntry['receiver_id'])
            || (int)$msgEntry['receiver_id'] === (int)$msgEntry['sender_id']
        ) {
            $msgEntry['receiver_id'] = 0;

            $this->setMsgEntry($msgEntry);

            return;
        }

        /*
         * Try to resolve by receiver name
         * if provided and different from sender.
         */
        if (
            $msgEntry['receiver_name'] !== ''
            && $msgEntry['receiver_name'] !== $msgEntry['sender_name']
        ) {
            $msgEntry['receiver_id'] = $this->chatModel->getUserIdByUserName(
                $msgEntry['receiver_name']
            );

            $this->setMsgEntry($msgEntry);
        }
    }

    /**
     * Prints only new messages that were appended since the last read.
     *
     * This method checks for changes in the database from CodeIgniter 3 Model.
     * If new data is available,
     * it reads the new entries from the database from CodeIgniter 3 Model,
     * and emits them via `printMsgEntry()`.
     *
     * ...
     *
     * @return bool True if new data was emitted, false otherwise.
     */
    final protected function printAllJsonMsgsFromDB(): bool
    {
        return false;
    }

    /**
     * Populates sender_name and receiver_name fields
     * based on user map.
     *
     * @return void
     */
    final protected function restoreUserNameFromUserData(): void
    {
        $msgEntry = $this->getMsgEntry();

        // Restore receiver_name from userMap
        $msgEntry['receiver_name'] =
            $this->chatModel->getUserNameById((int)$msg['receiver_id']);

        // Restore sender_name from userMap
        $msgEntry['sender_name'] =
            $this->chatModel->getUserNameById((int)$msgEntry['sender_id'])
            ?? 'guest';

        $this->setMsgEntry($msgEntry);
    }

    /**
     * Checks if sender_name is already registered to a different ID.
     *
     * @return bool True if name exists with a different ID.
     */
    final protected function selectUserDataFromDB(): bool
    {
        $msgEntry = $this->getMsgEntry();

        // Trim this non-verified sender name
        DataNormalizer::string($msgEntry['sender_name']);

        if ($this->chatModel->checkUserId($msgEntry['sender_id'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Finds and replaces an existing message
     * in the db with updated content.
     *
     * This method searches the log file for a message
     * matching the given `id` and `sender_id`,
     * updates the `message` field, and writes the full db
     * back to storage in-place using an atomic temporary file.
     *
     * The current `$msgEntry` is updated
     * to reflect the modified message if successful.
     *
     * @return bool
     *     True on successful update,
     *     false if the message was not found,
     *     JSON encoding failed, or file operations failed.
     */
    final protected function updateMsgEntryToDB(): bool
    {
        $msgEntry = $this->getMsgEntry();
    }

    /**
     * Getter for the current CodeIgniter 3 Instance path.
     *
     * @return string
     */
    final public function getCI3InstancePath(): string
    {
        return $this->ci3InstancePath;
    }

    final public function setCi3InstancePath(string $ci3InstancePath): void
    {
        require_once $ci3InstancePath;

        $framework =& get_instance();

        $framework->load->model('PwChatModel');

        $this->chatModel = $framework->PwChatModel;
    }

    /**
     * ChatDataBaseCI3 constructor.
     * Initializes CodeIgniter 3 instance path from configuration.
     *
     * @param array<string, string> $config
     *     Optional associative array with keys.
     */
    final public function __construct(array $config = [])
    {
        $this->setCi3InstancePath($config['ci3InstancePath'] ?? CI3_INSTANCE_PATH);
    }
}
} namespace ProtoWeb\DynamicChat\Server {
/**
 * Interface ChatServerInterface
 *
 * Defines the contract for a chat server
 * implementation that receives, processes, and emits chat messages
 * over a WebSocket-compatible stream.
 *
 * Implementations should typically run an event loop
 * that listens for JSON input (example: via STDIN)
 * and emits JSON output (example: via STDOUT),
 * possibly using a backend such as file storage, a database, etc.
 *
 * @package ProtoWeb\DynamicChat\Server
 */
interface ChatServerInterface
{
    /**
     * Starts the main event loop of the chat server.
     *
     * Implementations must continuously monitor input
     * (example: STDIN),
     * handle incoming messages (insert/update),
     * and emit valid outputs
     * (example: newly inserted or updated messages).
     *
     * @return void
     */
    public function run(): void;
}

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
 * @implements ChatServerInterface
 *
 * @package ProtoWeb\DynamicChat\Server
 */
abstract class ChatServerBase implements ChatServerInterface
{
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
     * Last raw line read from STDIN.
     *
     * @var string|false
     */
    private $stdInEntry = false;

    /**
     * Stream resource for STDIN.
     *
     * @var resource|false
     */
    private $stdInStream = false;

    /**
     * Streams to monitor with stream_select for read availability.
     *
     * @var array<int, resource|false>
     */
    private array $read = [];

    /**
     * Placeholder arrays for write streams in `stream_select`.
     *
     * @var mixed[]
     */
    private array $write = [];

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
     * URI to open STDIN stream for reading.
     *
     * @var string
     */
    private const STDIN_STREAM_URI = 'php://stdin';

    /**
     * Seconds to block on `stream_select` (set to 0 = non-blocking).
     *
     * @var int
     */
    private const SECONDS = 0;

    /**
     * Microseconds to block on `stream_select` (200ms).
     *
     * @var int
     */
    private const MICRO_SECONDS = 200000;


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
        $this->missingExt = Extension::getMissing(self::REQUIRED_EXTENSIONS);

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
     * Starts the main WebSocket loop.
     *
     * Continuously reads STDIN lines (via stream_select),
     * parses input as JSON, and delegates to insert/update logic
     * depending on the 'action' key.
     * Also periodically emits any new messages from the backend.
     *
     * @return void
     */
    final public function run(): void
    {
        $this->checkExtensions();

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
                    if (!($this->stdInEntry = fgets($this->stdInStream))) {
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
     * Initializes STDIN stream for non-blocking input detection.
     */
    public function __construct()
    {
        $this->stdInStream = fopen(self::STDIN_STREAM_URI, 'r');

        if (!is_resource($this->stdInStream)) {
            fwrite(STDERR, 'Failed to open stdin stream' . PHP_EOL);
            exit(1);
        }
    }
}

use ProtoWeb\DynamicChat\DataBase\ChatDataBaseCI3;
use ProtoWeb\DynamicChat\DataBase\ChatDataBaseFile;

/**
 * Class ChatServer
 *
 * Concrete implementation of ChatServerBase that initializes
 * a database handler for WebSocket chat operations.
 *
 * It supports different backend types
 * (example: file-based, CodeIgniter3)
 * by instantiating the corresponding ChatDataBase implementation.
 *
 * By default, it uses ChatDataBaseFile which stores messages
 * and user data in local files.
 *
 * @extends ChatServerBase
 *
 * @package ProtoWeb\DynamicChat\Server
 */
final class ChatServer extends ChatServerBase
{
    /**
     * Instance of the database backend object
     * implementing ChatDataBaseInterface.
     *
     * It handles message storage, retrieval, and user data resolution.
     *
     * @var object|null
     *     Can be ChatDataBaseFile or other implementations.
     */
    public ?object $dataBaseObj = null;

    /**
     * ChatServer constructor.
     *
     * Initializes the chat server with a specified backend type.
     * The default is 'jsonFiles' using ChatDataBaseFile.
     *
     * @param string $type
     *     Backend type. Example values: 'jsonFiles', 'CodeIgniter3'.
     *
     * @param array<string, string> $config
     *     Optional configuration values:
     *     - 'msgLogFile' => path to the message log file.
     *     - 'userMapFile' => path to the user map file.
     *
     * @return void
     */
    final public function __construct(
        string $type = 'jsonFiles',
        array $config = []
    ) {
        parent::__construct();

        if ($type === 'CodeIgniter3') {
            $this->dataBaseObj = new ChatDataBaseCI3($config);
        } else {
            $this->dataBaseObj = new ChatDataBaseFile($config);
        }
    }
}
} namespace {
use ProtoWeb\DynamicChat\Server\ChatServer;

function main(): void
{
    $DynamicChatObj = new ChatServer();
    $DynamicChatObj->run();
}

// Run the Server
main();
}
