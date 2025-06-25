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
 * PHP version 7.4+
 *
 * @author Marcio Delgado <marcio@libreware.info>
 * @copyright 2025 Marcio Delgado
 * @extends ChatServerBase
 * @license BSD-2-Clause
 * @package ProtoWeb\DynamicChat\Server
 * @since 2025
 * @version 1.0
 */
final class ChatServer extends ChatServerBase
{
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
    public function __construct(
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
}
