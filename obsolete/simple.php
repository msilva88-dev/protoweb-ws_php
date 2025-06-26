#!/usr/bin/env php
<?php
// Continuously read from stdin and echo to stdout
while ($line = fgets(STDIN)) {
    echo trim($line) . PHP_EOL; // Echo the received message
}
