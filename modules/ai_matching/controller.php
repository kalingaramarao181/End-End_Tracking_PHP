<?php

require_once __DIR__ . '/model.php';
require_once __DIR__ . '/../../services/CandidateMatcher.php';

class AIMatchingController
{
    private $model;
    private $matcher;

    public function __construct()
    {
        $this->model = new AIMatchingModel();
        $this->matcher = new CandidateMatcher();
    }

    /*
    |--------------------------------------------------------------------------
    | JSON RESPONSE
    |--------------------------------------------------------------------------
    */
    private function jsonResponse($status, $data)
    {
        http_response_code($status);

        header("Content-Type: application/json");

        echo json_encode($data);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | START AI MATCHING
    |--------------------------------------------------------------------------
    */
    public function startMatching($jobId)
    {

        /*
        |--------------------------------------------------------------------------
        | LOAD JOB
        |--------------------------------------------------------------------------
        */

        $job = $this->model->getJob($jobId);

        if (!$job["success"]) {

            $this->jsonResponse(404, $job);

        }

        /*
        |--------------------------------------------------------------------------
        | LOAD CANDIDATES
        |--------------------------------------------------------------------------
        */

        $candidates = $this->model->getCandidates();

        if (!$candidates["success"]) {

            $this->jsonResponse(500, $candidates);

        }

        /*
        |--------------------------------------------------------------------------
        | DELETE OLD MATCHES
        |--------------------------------------------------------------------------
        */

        $this->model->deleteOldMatches($jobId);

        $matched = 0;

        $errors = [];

        $jobJson = $job["data"]["parsed_job_json"];
                foreach ($candidates["data"] as $candidate) {

            try {

                $candidateJson = $candidate["parse_resume"];

                if (
                    empty($candidateJson)
                ) {
                    continue;
                }

                $match = $this->matcher->compare(

                    $jobJson,

                    $candidateJson

                );

                $save = $this->model->saveMatch(

                    $jobId,

                    $candidate["id"],

                    $match

                );

                if ($save["success"]) {

                    $matched++;

                }

            } catch (Exception $e) {

                $errors[] = [

                    "candidate_id" => $candidate["id"],

                    "candidate_name" => $candidate["name"],

                    "error" => $e->getMessage()

                ];

            }

        }
                $matches = $this->model->getMatchesByJob($jobId);

        $this->jsonResponse(200, [

            "success" => true,

            "message" => "AI Matching Completed.",

            "job_id" => $jobId,

            "matched_candidates" => $matched,

            "failed_candidates" => count($errors),

            "errors" => $errors,

            "results" => $matches["data"]

        ]);

    }

    /*
|--------------------------------------------------------------------------
| GET MATCH RESULTS
|--------------------------------------------------------------------------
*/

public function getMatches($jobId)
{

    $result = $this->model->getMatchesByJob(

        $jobId

    );

    if(!$result["success"])
    {

        $this->jsonResponse(

            500,

            $result

        );

    }

    $this->jsonResponse(

        200,

        $result

    );

}
    }

    