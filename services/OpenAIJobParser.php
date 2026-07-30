<?php

require_once __DIR__ . '/../config/openai.php';

class OpenAIJobParser
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = OPENAI_API_KEY;
    }

    /**
     * Parse Job Description using OpenAI
     */
    public function parseJobDescription($jobDescription)
    {
        $prompt = <<<PROMPT
You are an expert US IT Recruiter.

Read the following Job Description carefully.

Extract every possible detail.

Return ONLY valid JSON.

{
    "position":"",
    "location":"",
    "duration":"",
    "domain":"",
    "interview_process":"",
    "rate":"",
    "visa":"",
    "experience":"",
    "primary_skills":[],
    "secondary_skills":[],
    "responsibilities":[],
    "education":"",
    "certifications":[],
    "summary":""
}

Job Description:

$jobDescription

PROMPT;

        $payload = [

            "model" => "gpt-5-mini",

            "input" => $prompt,

            "text" => [
                "format" => [
                    "type" => "json_object"
                ]
            ]

        ];

        $curl = curl_init();

        curl_setopt_array($curl, [

            CURLOPT_URL => "https://api.openai.com/v1/responses",

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,

            CURLOPT_HTTPHEADER => [

                "Authorization: Bearer " . $this->apiKey,
                "Content-Type: application/json"

            ],

            CURLOPT_POSTFIELDS => json_encode($payload)

        ]);

        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            throw new Exception(curl_error($curl));
        }

        curl_close($curl);

        $response = json_decode($result, true);

        if ($httpCode !== 200) {
            throw new Exception($result);
        }

        if (isset($response["error"])) {
            throw new Exception($response["error"]["message"]);
        }

        if (!isset($response["output"])) {
            throw new Exception("Invalid response received from OpenAI.");
        }

        foreach ($response["output"] as $output) {

            if (
                isset($output["type"]) &&
                $output["type"] === "message"
            ) {

                foreach ($output["content"] as $content) {

                    if (
                        isset($content["type"]) &&
                        $content["type"] === "output_text"
                    ) {

                        return trim($content["text"]);
                    }
                }
            }
        }

        throw new Exception("Unable to parse Job Description.");
    }
}
