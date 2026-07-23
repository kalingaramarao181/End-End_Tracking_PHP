<?php

require_once __DIR__ . '/../config/openai.php';

class CandidateMatcher
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = OPENAI_API_KEY;
    }

    /**
     * Compare Job & Candidate
     */
    public function compare($jobJson, $candidateJson)
    {

        $prompt = <<<PROMPT

You are an Expert US IT Recruiter.

You will receive

1. Job JSON

2. Candidate Resume JSON

Your task is to compare both.

Consider

- Skills
- Experience
- Visa
- Location
- Certifications
- Education
- Domain

Return ONLY valid JSON.

{
    "overall_score":0,
    "skill_score":0,
    "experience_score":0,
    "location_score":0,
    "visa_score":0,
    "education_score":0,
    "certification_score":0,
    "recommendation":"",
    "ai_reason":""
}

Job JSON

$jobJson

Candidate JSON

$candidateJson

PROMPT;

        $payload = [

            "model" => "gpt-5-mini",

            "input" => $prompt,

            "text" => [

                "format" => [

                    "type" => "text"

                ]

            ]

        ];

        $curl = curl_init();

        curl_setopt_array($curl,[

            CURLOPT_URL=>"https://api.openai.com/v1/responses",

            CURLOPT_RETURNTRANSFER=>true,

            CURLOPT_POST=>true,

            CURLOPT_HTTPHEADER=>[

                "Authorization: Bearer ".$this->apiKey,

                "Content-Type: application/json"

            ],

            CURLOPT_POSTFIELDS=>json_encode($payload)

        ]);

        $result = curl_exec($curl);

        if(curl_errno($curl))
        {
            throw new Exception(curl_error($curl));
        }

        curl_close($curl);

        $response=json_decode($result,true);

        if(isset($response["error"]))
        {
            throw new Exception(
                $response["error"]["message"]
            );
        }

                foreach($response["output"] as $output)
        {

            if(
                isset($output["type"]) &&
                $output["type"]=="message"
            )
            {

                foreach($output["content"] as $content)
                {

                    if(
                        isset($content["type"]) &&
                        $content["type"]=="output_text"
                    )
                    {

                        return json_decode(
                            trim($content["text"]),
                            true
                        );

                    }

                }

            }

        }

        throw new Exception(
            "Unable to compare candidate."
        );

    }

}