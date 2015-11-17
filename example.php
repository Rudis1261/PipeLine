<?php

// Require the PipeLine class at the very least
require_once("class.pipeline.php");

// Open a pipe with 100 concurrent requests
$pipe = new pipeline(100);

$pipe->addUrl(
    "http://httpbin.org/ip", [
        "type" => "ip-address"
    ]);

$pipe->addUrl(
    "http://httpbin.org/image", [
        "type" => "image"
    ]);

$pipe->addUrl(
    "http://httpbin.org/redirect/1", [
        "type" => "redirect"
    ]);

$results = $pipe->open();

// Prints out the summary
$pipe->summary();

// What happened to the requests?
foreach ($results as $result) {
    echo "URL: " . $result['url'];
    echo "CONTEXT PROVIDED: " . print_r($result['context'], true) . PHP_EOL;
    echo "AVAILABLE KEYS: " . print_r(array_keys($result), true) . PHP_EOL;
    echo "HTTP CODE: " . $result['code'] . PHP_EOL;
    echo "CURL Error: " . $result['error'] . PHP_EOL;
    echo "BODY Length: " . strlen($result['response']) . PHP_EOL;
    echo "RESPONSE Body: " . substr($result['response'], 0, 40) . PHP_EOL;
    echo PHP_EOL . PHP_EOL;
}