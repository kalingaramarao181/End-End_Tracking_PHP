<?php

require_once __DIR__ . '/../config/openai.php';

class OpenAIResumeParser
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = OPENAI_API_KEY;
    }

    /**
     * Upload Resume
     */
    private function uploadFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Resume file not found: " . $filePath);
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.openai.com/v1/files",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->apiKey
            ],
            CURLOPT_POSTFIELDS => [
                "purpose" => "user_data",
                "file" => new CURLFile($filePath)
            ]
        ]);

        $result = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new Exception(curl_error($curl));
        }

        curl_close($curl);

        $response = json_decode($result, true);

        if (isset($response["error"])) {
            throw new Exception($response["error"]["message"]);
        }

        if (!isset($response["id"])) {
            throw new Exception("File upload failed.\n" . json_encode($response));
        }

        return $response["id"];
    }

    /**
     * Parse Resume
     */
    public function parseResume($resumePath)
    {
        $fileId = $this->uploadFile($resumePath);

        $prompt = <<<PROMPT
Read this resume carefully.

Extract every possible detail.

Return ONLY valid JSON.

{
    "name":"",
    "email":"",
    "phone":"",
    "location":"",
    "visa_status":"",
    "skills":[],
    "experience":[],
    "education":[],
    "certifications":[],
    "summary":"",
    "linkedin":"",
    "github":""
}
PROMPT;

        $payload = [

            // Your account supports this model
            "model" => "gpt-5-mini",

            "input" => [

                [
                    "role" => "user",

                    "content" => [

                        [
                            "type" => "input_file",
                            "file_id" => $fileId
                        ],

                        [
                            "type" => "input_text",
                            "text" => $prompt
                        ]

                    ]

                ]

            ]

        ];

        $curl = curl_init();

        curl_setopt_array($curl, [

            CURLOPT_URL => "https://api.openai.com/v1/responses",

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_HTTPHEADER => [

                "Authorization: Bearer " . $this->apiKey,
                "Content-Type: application/json"

            ],

            CURLOPT_POSTFIELDS => json_encode($payload)

        ]);

        $result = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new Exception(curl_error($curl));
        }

        curl_close($curl);

        $response = json_decode($result, true);

        if (isset($response["error"])) {
            throw new Exception($response["error"]["message"]);
        }

        // Find the assistant message instead of assuming output[0]
        if (isset($response["output"])) {

            foreach ($response["output"] as $item) {

                if (
                    isset($item["type"]) &&
                    $item["type"] === "message"
                ) {

                    foreach ($item["content"] as $content) {

                        if (
                            isset($content["type"]) &&
                            $content["type"] === "output_text"
                        ) {
                            return $content["text"];
                        }

                    }

                }

            }

        }

        throw new Exception("Unable to parse OpenAI response:\n" . json_encode($response));
    }
}