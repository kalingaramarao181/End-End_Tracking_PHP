<?php

function parsePutMultipart()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        return;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'multipart/form-data') === false) {
        // Leave JSON bodies untouched so controllers can read php://input.
        if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str(file_get_contents("php://input"), $_PUT);
            $GLOBALS['_PUT'] = $_PUT;
        }
        return;
    }

    $input = file_get_contents("php://input");

    preg_match('/boundary=(.*)$/', $contentType, $matches);

    if (!isset($matches[1])) {
        return;
    }

    $boundary = '--' . trim($matches[1]);

    $blocks = explode($boundary, $input);

    $data = [];
    $files = [];

    foreach ($blocks as $block) {

        if (empty($block) || $block == "--\r\n") {
            continue;
        }

        if (!preg_match('/name="([^"]+)"/', $block, $nameMatch)) {
            continue;
        }

        $name = $nameMatch[1];

        // FILE
        if (preg_match('/filename="([^"]*)"/', $block, $fileMatch)) {

            $filename = $fileMatch[1];

            if ($filename === '') {
                continue;
            }

            preg_match('/Content-Type:\s([^\n\r]+)/', $block, $typeMatch);

            $type = trim($typeMatch[1] ?? 'application/octet-stream');

            $parts = explode("\r\n\r\n", $block, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $content = substr($parts[1], 0, -2);

            $tmp = tempnam(sys_get_temp_dir(), 'put');

            file_put_contents($tmp, $content);

            $files[$name] = [
                'name' => $filename,
                'type' => $type,
                'tmp_name' => $tmp,
                'error' => 0,
                'size' => filesize($tmp),
                '_parsed_put' => true
            ];
        }

        // FIELD
        else {

            $parts = explode("\r\n\r\n", $block, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $value = substr($parts[1], 0, -2);

            $data[$name] = $value;
        }
    }

    $GLOBALS['_PUT'] = $data;
    $GLOBALS['_PUT_FILES'] = $files;
}
