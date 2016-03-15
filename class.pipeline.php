<?php

/**
 * Class pipeline
 * Date: 14 November 2015
 * Author: Rudi Strydom <iam@thatguy.co.za>
 * Description: I needed a MultiCurler which only needs to GET stuff fast, without all the possible optional parameters.
 *              But with one exception, I needed to be able to pass in context and on receiving the response. Have this context
 *              for the response to make sense and be processed correctly.
 *
 * Example Usage:
 *
 * $pipe = new pipeline(100);
 *
 * // Add a url with some context, which is returned with the result
 * $pipe->addUrl(
 *      "http://httpbin.org/ip", [
 *          "type" => "ip-address"  // this context is passed back with the response
 *      ]);
 *
 * $pipe->addUrl(
 *      "http://httpbin.org/redirect/1", [
 *          "type" => "redirect"   // this context is passed back with the response
 *      ]);
 *
 * NOW Supports post
 * $pipe->addPostUrl(
 *      "http://httpbin.org/ip",
 *      ['action' => 'submit', 'name' => 'fred', 'surname' => 'dunfold'],
 *      ['request_number' => 1]
 * );
 *
 * $result = $pipe->open();
 *
 * # Prints out the summary
 * $pipe->summary();
 */
class pipeline
{
    public $requests = [];
    public $responses = [];
    public $queue = [];
    public $options = [];
    public $concurrent = 100;
    public $timeout = 30;
    public $curlHandles = [];
    public $requestMapping = [];
    public $startTime;


    function __construct( $concurrent = 0 )
    {
        if (!empty($concurrent)) {
            $this->concurrent = $concurrent;
        }
    }


    function addUrl($url, $context = [])
    {
        return $this->requests[] = [
            "url"     => $url,
            "context" => $context,
        ];
    }

    function addPostUrl($url, $postFields = [], $context = [])
    {
        return $this->requests[] = [
            "url"     => $url,
            "post_fields" => $postFields,
            "context" => $context,
            "pipeline_type" => "post"
        ];
    }


    function open()
    {
        if (empty($this->requests) || empty($this->concurrent)) {
            return false;
        }

        $this->startTime = microtime(true);
        $this->generateHandles();
        $this->fetchQueue();

        return $this->responses;
    }


    function fetchQueue()
    {
        // Go through some blocks
        foreach($this->curlHandles as $blockIndex => $blocks) {

            // Create multiple MC handles
            $multiCurlHandle = curl_multi_init();
            foreach($blocks as $handle) {
                curl_multi_add_handle($multiCurlHandle, $handle);
            }

            // Make sure it's running
            $active = null;
            do {
                $connection = curl_multi_exec($multiCurlHandle, $active);
            } while ($connection == CURLM_CALL_MULTI_PERFORM);

            // Wait for activity on any curl-connection
            while ($active && $connection == CURLM_OK) {
                if (curl_multi_select($multiCurlHandle) == -1) {
                    usleep(100);
                }

                // Continue to exec until curl is ready to, give us more data
                do {
                    $connection = curl_multi_exec($multiCurlHandle, $active);
                } while ($connection == CURLM_CALL_MULTI_PERFORM);
            }

            // Process the response
            foreach($blocks as $handleIndex => $handle) {
                $request = $this->requestMapping[$blockIndex][$handleIndex];
                $this->processResponse($handle, $multiCurlHandle, $request);
            }

            // Close the entire MC handle
            curl_multi_close($multiCurlHandle);
        }
    }


    function processResponse($handle, $multiCurlHandle, $request)
    {
        $response = curl_multi_getcontent($handle);
        $errors = curl_errno($handle);
        $headers = curl_getinfo($handle);

        if (empty($response) || $headers['http_code'] !== 200 || $errors > 0) {
            $response = false;
        }

        $this->responses[] = [
            'response'  => $response,
            'error'     => $errors,
            'code'      => $headers["http_code"],
            'headers'   => $headers,
            'context'   => $request['context'],
            'url'       => $request['url'],
        ];

        // Close this handle
        curl_multi_remove_handle($multiCurlHandle, $handle);
    }


    function generateHandles()
    {
        $blocks = array_chunk($this->requests, $this->concurrent);
        foreach($blocks as $blockIndex => $block) {
            //var_dump("running block! $blockIndex");
            foreach($block as $index => $request){
                $ch = curl_init();

                // Handle specific request types
                if (!empty($request['pipeline_type'])) {
                    if ($request['pipeline_type'] == "post" && !empty($request['post_fields'])) {
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request['post_fields']));
                    }
                }
                curl_setopt($ch, CURLOPT_URL, $request['url']);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
                curl_setopt($ch, CURLOPT_FAILONERROR, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 5);

                $this->curlHandles[$blockIndex][$index] = $ch;
                $this->requestMapping[$blockIndex][$index] = $request;
            }
        }
    }


    function summary()
    {
        $endTime = round(microtime(true) - $this->startTime, 2);
        $keys = [];
        $keys['REQUESTS'] = count($this->requests);
        $keys['RESPONSES'] = count($this->responses);

        foreach($keys as $keyIndex => $value) {
            echo $keyIndex . " :: " . $value . PHP_EOL;
        }

        $success = [];
        $failures = [];
        foreach($this->responses as $index => $response) {
            if ($response['code'] == 200 && !empty($response['response'])) {
                $success[] = "";
            } else {
                $failures[$response['code']] = $response['url'];
            }
        }

        echo "SUCCESS :: " . count($success) . PHP_EOL;
        echo "FAILURES :: " . count($failures) . PHP_EOL;

        foreach($failures as $resCode => $url) {
            echo "FAILURE [" . $resCode . "] :: " . $url . PHP_EOL;
        }

        echo "EXEC TIME :: " . $endTime . "s" . PHP_EOL;
    }
}
