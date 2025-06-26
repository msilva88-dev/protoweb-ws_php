#!/usr/bin/env php
<?php

/*
 * Copyright (c) 2025, Márcio Delgado <marcio@libreware.info>
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

namespace WebSocketServiceApp {

use RuntimeException;

/**
 * Class WebSocketHandler
 *
 * Handles WebSocket requests and framework-specific logic.
 *
 * This class processes incoming WebSocket messages, integrates with
 * a specified PHP framework (e.g., CodeIgniter3), and provides logging
 * and validation utilities. If no framework is used, it handles raw
 * WebSocket messages directly.
 *
 * Features:
 * - Supports dynamic framework initialization via JSON configuration.
 * - Validates framework paths before loading.
 * - Provides error logging and debugging capabilities.
 * - Handles WebSocket requests either through a framework or as raw messages.
 *
 * Usage Example:
 * ```
 * $handler = new WebSocketHandler($basePath, $env, $framework, $includePath);
 * $handler->request($websocketMessage);
 * ```
 *
 * @package WebSocketServiceApp
 */
class WebSocketHandler
{
    /**
     * The instance of the initialized framework.
     *
     * This stores a reference to the framework instance
     * if it has been initialized.
     * It is `null` when no framework is in use.
     *
     * @var object|null
     */
    private ?object $frameWork = null;

    /**
     * The name of the framework being used.
     *
     * This variable stores the name of the framework
     * integrated into the WebSocket service (e.g., 'codeIgniter3').
     * If no framework is used, it defaults to 'notUsed'.
     *
     * @var string
     */
    private string $frameWorkName = 'notUsed';

    /**
     * The configuration file path.
     *
     * This constant defines the absolute path to the JSON configuration file
     * used for initializing and configuring the WebSocket framework.
     *
     * @var string
     */
    private const CONFIG_FILE = __DIR__ . '/handler_config.json';

    /**
     * Validates file and directory paths required for the framework.
     *
     * Ensures the base framework file and the include path exist.
     * If validation fails, appropriate errors are logged.
     *
     * @param string $base The base framework file path (e.g., 'index.php').
     * @param string $path The include path for framework dependencies.
     *
     * @return bool Returns `true` if paths are valid, `false` otherwise.
     */
    private function validatePaths(string $base, string $path): bool
    {
                echo 'FINEY1' . PHP_EOL;
                echo $path . PHP_EOL;
                echo $base . PHP_EOL;

        if ($path && !is_dir($path)) {
            $this->handleLog('error', "Invalid include path: $path");

                echo 'FINEY5' . PHP_EOL;

            return false;
        }

                echo 'FINEY2' . PHP_EOL;

        if ($base && !file_exists($path . '/' . $base)) {
            $this->handleLog('error', "Base file not found: $base");

                echo 'FINEY6' . PHP_EOL;
                echo $path . PHP_EOL;
                echo $base . PHP_EOL;

            return false;
        }

                echo 'FINEY3' . PHP_EOL;

        $this->handleLog('info', 'Base file and include path are valid.');

                echo 'FINEY4' . PHP_EOL;

        return true;
    }

    /**
     * Initializes the CodeIgniter 3 framework.
     *
     * This method validates the framework entry point
     * and paths before loading the framework and its model
     * (`PwSimpleChatModel`).
     *
     * @param string $base The base path for the framework entry file.
     * @param string $env The environment setting for CodeIgniter 3.
     * @param string $path The additional include path for the framework.
     *
     * @throws RuntimeException If the framework entry file is not found
     *     or paths are invalid.
     *
     * @return void
     */
    private function initializeCodeIgniter3(
        string $base,
        string $env,
        string $path
    ): void {
        /**
         * Determines the local base path for the framework.
         * Uses the provided base path, falls back to an environment variable,
         * or defaults to 'index.php'.
         *
         * @var string $localBase The resolved base path
         *     for the framework entry file.
         */
        $localBase = $base ?: getenv('CI_INDEX') ?: 'index.php';

        /**
         * Determines the local environment setting.
         * Uses the provided environment value,
         * falls back to an environment variable,
         * or defaults to 'production'.
         *
         * @var string $localEnv The resolved environment setting
         *     for the framework.
         */
        $localEnv = $env ?: getenv('CI_ENV') ?: 'production';

                echo 'FINEX1' . PHP_EOL;

        // Validate base file and include path
        if ($this->validatePaths($localBase, $path)) {

                echo 'FINEX2' . PHP_EOL;

            // Set include path if specified
            if ($path !== '') {
                echo 'FINEX4' . PHP_EOL;
                set_include_path($path);
            }
                echo 'FINEX5' . PHP_EOL;

            // Load the base framework file
            require_once $localBase;
                echo 'FINEX6' . PHP_EOL;

                echo var_dump($this->frameWork) . PHP_EOL;

            // Initialize framework
            $this->frameWork = &get_instance();
            $this->frameWork->load->model('PwSimpleChatModel', 'model');
            $this->frameWork->model->env($localEnv);

                echo 'FINEX7' . PHP_EOL;

                echo var_dump($this->frameWork) . PHP_EOL;
        }

                echo 'FINEX3' . PHP_EOL;
    }

    /**
     * Applies default framework settings.
     *
     * If no valid configuration is found,
     * this method resets the framework settings to 'notUsed',
     * ensuring the application functions without integration.
     *
     * @return void
     */
    private function useDefaultFrameworkSettings(): void
    {
        $this->frameWorkName = 'notUsed';

        $this->handleLog(
            'info',
            'Default framework settings applied.' .
            ' No framework integration configured.'
        );
    }

    /**
     * Logs a message with a specified log level.
     *
     * Logs messages for debugging, warnings, errors, or critical failures.
     * The message is written to PHP's `error_log()` function with a timestamp.
     *
     * @param string $level The log level
     *     ('info', 'warning', 'error', 'critical').
     * @param string $msg The log message to record.
     *
     * @return void
     */
    public function handleLog(string $level, string $msg): void
    {
        error_log(
            date('[Y-m-d H:i:s]') . "[WebSocket Handler - $level]: " . $msg
        );
    }

    /**
     * Adjusts the WebSocket framework configuration based on input data.
     *
     * Reads JSON input, validates the environment and host settings,
     * and applies configuration settings from `handler_config.json`.
     * If configuration is missing or invalid,
     * it falls back to default settings.
     *
     * @param string $data JSON-encoded string
     *     containing 'env' and 'host' values.
     *
     * @return void
     */
    public function adjustFrameWork(string $data): void
    {
        if (!file_exists(self::CONFIG_FILE)) {
            $this->handleLog(
                'warning',
                'Configuration file not found: ' . self::CONFIG_FILE
            );

            // Use defaults if the config file is missing
            $this->useDefaultFrameworkSettings();

            return;
        }

        /**
         * Decodes the JSON data received from input.
         *
         * @var array|null $jsonData The parsed JSON data
         *     as an associative array, or null if decoding fails.
         */
        $jsonData = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->handleLog(
                'warning',
                'Invalid JSON parameter or missing keys (env, host).' .
                ' Using default settings.'
            );

            // Use defaults if keys are invalid or missing
            $this->useDefaultFrameworkSettings();

            return;
        }

        /**
         * Decodes the JSON configuration file contents.
         *
         * @var array|null $jsonConfig The parsed JSON configuration file
         *     as an associative array, or null if decoding fails.
         */
        $jsonConfig = json_decode(file_get_contents(self::CONFIG_FILE), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->handleLog(
                'warning',
                'Invalid JSON in config file: ' . json_last_error_msg() .
                ' Using default settings.'
            );

            // Use defaults if the configuration file is invalid
            $this->useDefaultFrameworkSettings();

            return;
        }

        /**
         * Extracts the environment setting from the input JSON data.
         *
         * @var string|null $env The environment setting
         *     (e.g., 'production', 'development'),
         *     or null if not provided in the input JSON.
         */
        $env = $jsonData['env'] ?? null;

        /**
         * Extracts the host identifier from the input JSON data.
         *
         * @var string|null $host The host identifier used
         *     to locate configuration settings,
         *     or null if not provided in the input JSON.
         */
        $host = $jsonData['host'] ?? null;

        if (!is_string($jsonData['env']) || !is_string($jsonData['host'])) {
            $this->handleLog(
                'warning',
                "Invalid input JSON structure: missing 'env'" .
                " and/or 'host'. Using default settings."
            );

            // Use defaults if the configuration is invalid
            $this->useDefaultFrameworkSettings();

            return;
        }

        /**
         * Extracts the framework base path from the configuration.
         *
         * @var string|null $base The base path for the framework's
         *     main entry file, or null if not set in the config.
         */
        $base = $jsonConfig['host'][$host]['base'] ?? null;

        /**
         * Extracts the framework name from the configuration.
         *
         * @var string|null $frameWork The name of the framework
         *     (e.g., 'codeIgniter3'), or null if not set in the config.
         */
        $frameWork = $jsonConfig['host'][$host]['frameWork'] ?? null;

        /**
         * Extracts the framework include path from the configuration.
         *
         * @var string|null $path The additional include path
         *     for the framework, or null if not set in the config.
         */
        $path = $jsonConfig['host'][$host]['env'][$env] ?? null;

        // Use defaults if the configuration is invalid
        if (!is_string($base) || !is_string($frameWork) || !is_string($path)) {
            $this->handleLog(
                'warning',
                "Invalid input JSON structure: missing 'base', 'frameWork'" .
                " and/or 'path'. Using default settings." .
                'Please verify ' . self::CONFIG_FILE . '.'
            );

            $this->useDefaultFrameworkSettings();

            return;
        }

        // Set the framework name
        $this->frameWorkName = $frameWork ?? 'notUsed';

        if (!in_array($frameWork, ['codeIgniter3', 'notUsed'])) {
            $this->handleLog(
                'error',
                "Invalid framework specified: $frameWork"
            );

            exit(1);
        }

        // Initialize framework if applicable
        if ($this->frameWorkName === 'codeIgniter3') {
            $this->initializeCodeIgniter3($base, $env, $path);
        }
    }

    /**
     * Processes a WebSocket request.
     *
     * If a framework is integrated,
     * it delegates processing to the framework's model.
     * Otherwise, it simply outputs the raw input data.
     *
     * @param string $data The WebSocket message or input data.
     *
     * @return void
     *
     * @throws RuntimeException If the framework instance is not initialized.
     */
    public function request(string $data): void
    {
        try {
            if ($this->frameWorkName === 'codeIgniter3') {
                echo 'FINE4' . PHP_EOL;
                echo var_dump($this->frameWork) . PHP_EOL;
                echo $this->frameWorkName . PHP_EOL;

                if ($this->frameWork === null) {

                    throw new RuntimeException(
                        'Framework instance is not initialized.'
                    );
                }

                // Use the framework to process the request
                echo $this->frameWork->model->request($data) . PHP_EOL;
                echo 'FINE5' . PHP_EOL;
            } else {
                echo 'FINE6' . PHP_EOL;
                echo var_dump($this->frameWork) . PHP_EOL;
                echo $this->frameWorkName . PHP_EOL;
                // Default behavior: Print the input data
                echo $data . PHP_EOL;
                echo 'FINE7' . PHP_EOL;
            }
        } catch (RuntimeException $e) {
            $this->handleLog(
                'critical',
                'Runtime Exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * WebSocketHandler constructor.
     *
     * Initializes the WebSocket handler, optionally setting up integration
     * with a specified framework (e.g., CodeIgniter 3).
     * It also ensures the provided framework is valid before proceeding.
     *
     * @param string $base The base path for the framework entry file.
     *     Defaults to an empty string.
     * @param string $env The environment setting
     *     (e.g., 'production', 'development'). Defaults to an empty string.
     * @param string $frameWork The name of the framework to use.
     *     Defaults to 'notUsed'.
     * @param string $path The additional include path for the framework.
     *     Defaults to an empty string.
     *
     * @throws RuntimeException If framework initialization fails.
     */
    public function __construct(
        string $base = '',
        string $env = '',
        string $frameWork = '',
        string $path = ''
    ) {
        if (!in_array($frameWork, ['codeIgniter3', 'notUsed'])) {
            $handler->handleLog(
                'error',
                "Invalid framework specified: $frameWork"
            );

            exit(1);
        }

        $this->frameWorkName = $frameWork ?? 'notUsed';

        try {
            if ($this->frameWorkName === 'codeIgniter3') {
                $this->initializeCodeIgniter3($base, $env, $path);
            }
        } catch (RuntimeException $e) {
            $this->handleLog(
                'critical',
                'Runtime Exception during initialization: ' . $e->getMessage()
            );
        }
    }
}

} // End of WebSocketServiceApp namespace

// Use the namespace to call the class
namespace {

use WebSocketServiceApp\WebSocketHandler;

/**
 * Main execution logic for the WebSocket script.
 *
 * Initializes the WebSocket handler and processes incoming WebSocket messages.
 */

/**
 * The name of the framework to use.
 *
 * It determines whether to integrate with a framework (e.g., 'codeIgniter3')
 * or handle raw WebSocket input.
 *
 * @var string $frameWork The framework name provided via CLI argument,
 *     defaults to 'notUsed' if no argument is given.
 */
$frameWork = $argv[1] ?? 'notUsed';

/**
 * The environment setting for the application.
 *
 * Used to configure different runtime environments such as 'production',
 * 'development', or 'testing'.
 *
 * @var string $env The environment name provided via CLI argument,
 *     defaults to an empty string if not provided.
 */
$env = $argv[2] ?? '';

/**
 * The base path for the framework entry file.
 *
 * This specifies the main entry script for the chosen framework.
 * Example: '/srv/http/framework/index.php'
 *
 * @var string $base The base path provided via CLI argument,
 *     defaults to an empty string if not provided.
 */
$base = $argv[3] ?? '';

/**
 * The additional include path for the framework.
 *
 * Defines an optional include path for framework dependencies
 * or custom extensions.
 * Example: '/srv/http/framework'
 *
 * @var string $path The include path provided via CLI argument,
 *     defaults to an empty string if not provided.
 */
$path = $argv[4] ?? '';

/**
 * Initializes the WebSocketHandler instance.
 *
 * It processes WebSocket messages based on the selected framework and
 * configuration settings.
 *
 * @var WebSocketHandler $handler The WebSocket handler instance responsible
 *     for processing incoming messages.
 */
$handler = new WebSocketHandler($base, $env, $frameWork, $path);

// Continuously read from stdin and process WebSocket messages
while (($data = fgets(STDIN)) !== false) {
    /**
     * Trims whitespace and newlines from input data.
     *
     * @var string $data The WebSocket message
     *     or input data received from STDIN.
     */
    $data = trim($data); // Trim whitespace/newline to avoid errors

    if (!empty($data)) {
        $handler->adjustFrameWork($data);

        // Directly process and print STDIN if framework is 'notUsed'
        $handler->request($data);
    }
}

// Handle unexpected errors in fgets
if ($data === false && !feof(STDIN)) {
    $handler->handleLog('error', 'Error reading input from STDIN.');
}

} // End of global namespace
