<?php

require_once __DIR__ . '/controller.php';

$matchingController = new AIMatchingController();

$method = $_SERVER['REQUEST_METHOD'];

$requestUri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

$requestUri = str_replace('', '', $requestUri);

/*
|--------------------------------------------------------------------------
| START AI MATCHING
|--------------------------------------------------------------------------
|
| POST /matching/start/1
|
*/

if (

    $method === "POST"

    &&

    preg_match(

        '#^/matching/start/([0-9]+)$#',

        $requestUri,

        $matches

    )

) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();

    requirePermission(

        "jobs",

        "can_view"

    );

    $matchingController->startMatching(

        (int)$matches[1]

    );

    exit;

}

/*
|--------------------------------------------------------------------------
| GET MATCHED CANDIDATES
|--------------------------------------------------------------------------
|
| GET /matching/job/1
|
*/

if (

    $method === "GET"

    &&

    preg_match(

        '#^/matching/job/([0-9]+)$#',

        $requestUri,

        $matches

    )

) {

    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/role.php';

    authenticate();

    requirePermission(

        "jobs",

        "can_view"

    );

    $matchingController->getMatches(

        (int)$matches[1]

    );

    exit;

}