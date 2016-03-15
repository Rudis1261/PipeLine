# PipeLine
Yet another PHP Multicurl wrapper, because the other's are either too complex, or just plain worthless.

## Example Usage:
```php
<?php

// Require the PipeLine class at the very least
require_once("class.pipeline.php");

// Open a pipe with 100 concurrent requests
$pipe = new pipeline(100);

// Returns an IP Address and the "type" => "ip-address" context
$pipe->addUrl("http://httpbin.org/ip", [ "type" => "ip-address" ]);

// Returns an image and the "type" => "image" context
$pipe->addUrl("http://httpbin.org/image", [ "type" => "image" ]);
$pipe->addUrl("http://httpbin.org/redirect/1", [ "type" => "redirect" ]);

// POST Example
/*
  The post fields 'action', 'name', 'surname' in this example is posted with
  Their respective values.
  The context 'request_number' => 1 is always returned
*/
 $pipe->addPostUrl("http://httpbin.org/post",
      ['action' => 'submit', 'name' => 'fred', 'surname' => 'dunfold'],
      ['request_number' => 1]);

// Open the pipe and get the results
$results = $pipe->open();

// OPTIONAL! Prints out the summary
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
```
