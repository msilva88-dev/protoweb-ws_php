#!/usr/bin/env php
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
 * WebSocketd Loader Script
 *
 * This acts as a wrapper to dynamically include and execute another PHP file.
 *
 * It allows editing the actual WebSocket logic file
 * (e.g. `simplechatfile.php`)
 * without restarting websocketd. Useful for development.
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @license BSD-2-Clause
 * @version 1.0
 * @since 2025
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

$scriptName = basename($argv[1] ?? "");

// Append ".php" if no extension is given
if (pathinfo($scriptName, PATHINFO_EXTENSION) === "") {
    $scriptName .= ".php";
}

// Target handler to include (must be in the same directory)
$targetScript = __DIR__ . "/" . $scriptName;

// Validate and include the target script
if (is_file($targetScript)) {
    try {
        require $targetScript;

        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Exception: {$e->getMessage()}" . PHP_EOL);
        exit(2);
    }
}

// If file does not exist or is not valid, return 1
fwrite(STDERR, "Error: script '{$targetScript}' not found." . PHP_EOL);
exit(1);
