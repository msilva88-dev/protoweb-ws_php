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

define('CONFIG_FILE_PATH', __DIR__ . '/handler_config.json');


/**
 * Sends an HTTP GET request to the provided URL and returns the response.
 *
 * @param string $url The URL to request.
 * @param int $timeOut The timeout for the cURL request in seconds.
 *
 * @return string The response body.
 *
 * @throws RuntimeException If the request fails
 *   or returns a non-200 HTTP status code.
 */
function fetchResponse(string $url, int $timeOut = 10): string
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        $error = curl_error($ch);

        curl_close($ch);

        throw new RuntimeException(
            "Failed to fetch response: " . ($error ?: "HTTP $httpCode")
        );
    }

    curl_close($ch);

    return $response;
}


/**
 * Constructs the full URL for an action based on the configuration
 * and input data.
 *
 * @param array $data The input data containing the action and parameters.
 * @param string $host The base URL for the environment and host.
 * @param array $config The configuration array containing the route map.
 *
 * @return string The constructed action URL.
 *
 * @throws InvalidArgumentException If the action is invalid or missing.
 */
function getActionUrl(array $data, string $host, array $config): string
{
    if (isset($data['action'], $config['routeMap'][$data['action']])) {
        return $host . $config['routeMap'][$data['action']] . '?' .
            http_build_query($data);
    }

    throw new InvalidArgumentException("Invalid action.");
}


/**
 * Resolves the base host URL for the given host
 * and environment from the configuration.
 *
 * @param array $data The input data containing host and environment.
 * @param array $config The configuration array containing the host map.
 *
 * @return string The resolved base URL for the host and environment.
 *
 * @throws InvalidArgumentException If the host or environment is invalid.
 */
function getHostAndEnv(array $data, array $config): string
{
    if (
        isset($data['env'], $data['host']) &&
        isset($config['hostMap'][$data['host']][$data['env']])
    ) {
        return $config['hostMap'][$data['host']][$data['env']];
    }

    throw new InvalidArgumentException("Invalid host and/or environment.");
}


/**
 * Logs an error message and exits the script.
 *
 * @param string $msg The error message to log.
 *
 * @return void
 */
function handleError(string $msg): void
{
    error_log(date('[Y-m-d H:i:s]') . ' - [WebSocket Handler] ' . $msg);
    exit(1);
}


/**
 * Validates the required keys in the input data.
 *
 * @param array $data The input data to validate.
 *
 * @return void
 *
 * @throws InvalidArgumentException If a required key is missing.
 */
function validateInput(array $data): void
{
    $requiredKeys = ['env', 'host', 'action'];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $data)) {
            throw new InvalidArgumentException("Missing required key: $key");
        }
    }
}


try {
    // Check if the file exists before opening
    if (!file_exists(CONFIG_FILE_PATH)) {
        throw new RuntimeException("File not found: CONFIG_FILE_PATH");
    }

    // Open the file for reading
    $configFile = new SplFileObject(CONFIG_FILE_PATH, 'r');

    // Decode the JSON data into an associative array
    $config = json_decode(
        $configFile->fread($configFile->getSize()),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!isset($config['hostMap'], $config['routeMap'])) {
        throw new UnexpectedValueException(
            "Missing hostMap and/or routeMap sections."
        );
    }
} catch (JsonException $e) {
    handleError('JSON decoding error: ' . $e->getMessage());
} catch (UnexpectedValueException $e) {
    handleError('Configuration data error: ' . $e->getMessage());
} catch (RuntimeException $e) {
    handleError('File error: ' . $e->getMessage());
}

// Process input from STDIN
while ($line = trim(fgets(STDIN))) {
    if (!mb_check_encoding($line, 'UTF-8')) {
        echo json_encode(
            ["status" => "error", "message" => "Invalid UTF-8 input"]
        ) . PHP_EOL;

        continue;
    }

    try {
        // Decode the JSON data into an associative array
        $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

        // Validate the input data
        validateInput($data);

        $host = getHostAndEnv($data, $config);
        $url = getActionUrl($data, $host, $config);

        // Forward the request to the appropriate JSON endpoint
        echo fetchResponse($url) . PHP_EOL;
    } catch (InvalidArgumentException $e) {
        echo json_encode(
            ["status" => "error", "message" => $e->getMessage()]
        ) . PHP_EOL;
    } catch (JsonException $e) {
        echo json_encode(
            ["status" => "error", "message" => $e->getMessage()]
        ) . PHP_EOL;
    } catch (RuntimeException $e) {
        echo json_encode(
            ["status" => "error", "message" => $e->getMessage()]
        ) . PHP_EOL;
    }
}
