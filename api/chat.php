<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

/* =========================================
   RESPONSE
========================================= */

function sendResponse($success, $reply, $needsHuman = false)
{
    echo json_encode([
        'success' => $success,
        'reply' => $reply,
        'needs_human' => $needsHuman
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/* =========================================
   TEXT HELPERS
========================================= */

function normalizeText($text)
{
    $text = strtolower(trim((string)$text));

    $text = preg_replace(
        '/[^\p{L}\p{N}\s&.-]/u',
        ' ',
        $text
    );

    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

function tokenize($text)
{
    $text = normalizeText($text);

    if ($text === '') {
        return [];
    }

    return array_values(
        array_filter(
            preg_split('/\s+/', $text)
        )
    );
}

/* =========================================
   JSON LOADER
========================================= */

function loadJsonFile($file)
{
    if (!file_exists($file)) {
        return [];
    }

    $content = file_get_contents($file);

    if ($content === false) {
        return [];
    }

    $data = json_decode($content, true);

    return is_array($data) ? $data : [];
}

function normalizeCollection($data, $possibleKeys = [])
{
    if (!is_array($data)) {
        return [];
    }

    if (
        count($data) === 0 ||
        array_keys($data) === range(0, count($data) - 1)
    ) {
        return $data;
    }

    foreach ($possibleKeys as $key) {
        if (
            isset($data[$key]) &&
            is_array($data[$key])
        ) {
            return $data[$key];
        }
    }

    return [];
}

/* =========================================
   KNOWLEDGE BASE
========================================= */

function getKnowledgeBase()
{
    $company = loadJsonFile(
        __DIR__ . '/../data/company.json'
    );

    $servicesData = loadJsonFile(
        __DIR__ . '/../data/services.json'
    );

    $productsData = loadJsonFile(
        __DIR__ . '/../data/products.json'
    );

    $faqData = loadJsonFile(
        __DIR__ . '/../data/faq.json'
    );

    return [
        'company' => $company,

        'services' => normalizeCollection(
            $servicesData,
            ['services', 'data', 'items']
        ),

        'products' => normalizeCollection(
            $productsData,
            ['products', 'data', 'items']
        ),

        'faq' => normalizeCollection(
            $faqData,
            ['faq', 'faqs', 'data', 'items']
        )
    ];
}

/* =========================================
   COMPANY INTENT
========================================= */

function findCompanyIntent($message)
{
    $text = normalizeText($message);

    /* ABOUT COMPANY */

    $aboutPatterns = [
        'what is migratech',
        'what is migratech softwares',
        'who is migratech',
        'tell me about migratech',
        'about migratech',
        'about migratech softwares',
        'what does migratech do',
        'what does migratech softwares do'
    ];

    foreach ($aboutPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return 'about';
        }
    }

    /* CONTACT */

    $contactPatterns = [
        'how can i contact',
        'how do i contact',
        'how can i reach',
        'how do i reach',
        'contact migratech',
        'contact migratech softwares',
        'migratech contact',
        'contact details',
        'contact information',
        'phone number',
        'phone numbers',
        'contact number',
        'contact numbers',
        'email address',
        'email id',
        'migratech email',
        'support number',
        'sales number'
    ];

    foreach ($contactPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return 'contact';
        }
    }

    /* LOCATION */

    $locationPatterns = [
        'where is migratech',
        'where is migratech located',
        'where is migratech softwares located',
        'migratech location',
        'migratech address',
        'where are you located',
        'where are you based',
        'location of migratech'
    ];

    foreach ($locationPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return 'location';
        }
    }

    /* WORKING HOURS */

    $hoursPatterns = [
        'working hours',
        'work hours',
        'office hours',
        'business hours',
        'opening hours',
        'operating hours',
        'when are you open',
        'when is migratech open',
        'what time do you open',
        'what time do you close',
        'when does migratech open',
        'when does migratech close',
        'is migratech open',
        'is migratech closed'
    ];

    foreach ($hoursPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return 'hours';
        }
    }

    /* DOMAINS */

    $domainPatterns = [
        'which industries',
        'which industry',
        'industries does migratech',
        'domains does migratech',
        'migratech domains',
        'migratech industries',
        'what industries',
        'what domains'
    ];

    foreach ($domainPatterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return 'domains';
        }
    }

    return null;
}

/* =========================================
   COMPANY RESPONSE
========================================= */

function buildCompanyReply($intent, $company)
{
    $name = $company['name'] ?? 'Migratech Softwares Private Limited';

    if ($intent === 'about') {
        $founded = $company['founded'] ?? '';
        $tagline = $company['tagline'] ?? '';
        $description = $company['description'] ?? '';

        return "{$name} was founded in {$founded} with the tagline \"{$tagline}\". {$description}";
    }

    if ($intent === 'contact') {
        $phones = $company['contact']['phones'] ?? [];
        $email = $company['contact']['email'] ?? '';

        $phoneText = implode(' or ', $phones);

        return "You can contact {$name} by phone at {$phoneText}, or by email at {$email}.";
    }

    if ($intent === 'location') {
        $city = $company['location']['city'] ?? '';
        $state = $company['location']['state'] ?? '';
        $country = $company['location']['country'] ?? '';

        return "{$name} is based in {$city}, {$state}, {$country}.";
    }

    if ($intent === 'hours') {
        $mondaySaturday =
            $company['working_hours']['monday_to_saturday']
            ?? '10:00 AM – 06:00 PM';

        $sunday =
            $company['working_hours']['sunday']
            ?? 'Closed';

        return "Our working hours are Monday through Saturday, from {$mondaySaturday}. We are {$sunday} on Sundays.";
    }

    if ($intent === 'domains') {
        $domains = $company['domains'] ?? [];

        return "Migratech specializes in the following domains: "
            . implode(', ', $domains)
            . ".";
    }

    return null;
}

/* =========================================
   FIND ITEM BY NAME
========================================= */

function findItemByName($items, $targetName)
{
    $target = normalizeText($targetName);

    foreach ($items as $item) {

        if (!is_array($item)) {
            continue;
        }

        $name = isset($item['name'])
            ? normalizeText($item['name'])
            : '';

        if ($name !== '' && $name === $target) {
            return $item;
        }
    }

    return null;
}

/* =========================================
   DESCRIPTION
========================================= */

function getItemDescription($item)
{
    if (
        isset($item['description']) &&
        is_string($item['description'])
    ) {
        return trim($item['description']);
    }

    if (
        isset($item['details']) &&
        is_string($item['details'])
    ) {
        return trim($item['details']);
    }

    return '';
}

/* =========================================
   PRIORITY SERVICE / PRODUCT MATCH
========================================= */

function findPriorityMatch($message, $kb)
{
    $text = normalizeText($message);

    $rules = [

        [
            'keywords' => [
                'erp',
                'erp solution',
                'erp solutions',
                'enterprise resource planning'
            ],
            'type' => 'service',
            'name' => 'ERP Development'
        ],

        [
            'keywords' => [
                'web application',
                'web application development',
                'web app',
                'web app development'
            ],
            'type' => 'service',
            'name' => 'Web Application Development'
        ],

        [
            'keywords' => [
                'website design',
                'website designing',
                'design a website',
                'design website',
                'build a website',
                'build website',
                'develop a website',
                'develop website',
                'business website'
            ],
            'type' => 'service',
            'name' => 'Website Designing'
        ],

        [
            'keywords' => [
                'mobile app',
                'mobile application',
                'android app',
                'ios app',
                'app development'
            ],
            'type' => 'service',
            'name' => 'Mobile App Development'
        ],

        [
            'keywords' => [
                'application testing',
                'app testing',
                'software testing'
            ],
            'type' => 'service',
            'name' => 'Application Testing'
        ],

        [
            'keywords' => [
                'cyber security',
                'cybersecurity',
                'it consulting',
                'it consultancy'
            ],
            'type' => 'service',
            'name' => 'IT Consulting & Cyber Security'
        ],

        [
            'keywords' => [
                'website hosting',
                'web hosting',
                'hosting service',
                'hosting'
            ],
            'type' => 'service',
            'name' => 'Website Hosting'
        ],

        [
            'keywords' => [
                'digital marketing',
                'seo',
                'search engine optimization',
                'social media marketing',
                'smo',
                'ppc'
            ],
            'type' => 'service',
            'name' => 'Digital Marketing'
        ],

        [
            'keywords' => [
                'bulk sms',
                'bulk message',
                'sms service'
            ],
            'type' => 'service',
            'name' => 'Bulk SMS'
        ],

        [
            'keywords' => [
                'voice call',
                'voice calling',
                'calling service'
            ],
            'type' => 'service',
            'name' => 'Voice Call'
        ],

        [
            'keywords' => [
                'whatsapp marketing',
                'whatsapp promotion'
            ],
            'type' => 'service',
            'name' => 'WhatsApp Marketing'
        ],

        [
            'keywords' => [
                'interactive flat panel',
                'interactive panel',
                'flat panel',
                'ifpd'
            ],
            'type' => 'product',
            'name' => 'Interactive Flat Panel'
        ],

        [
            'keywords' => [
                'smartclass',
                'smart class'
            ],
            'type' => 'product',
            'name' => 'SmartClass'
        ],

        [
            'keywords' => [
                'attendance machine',
                'biometric',
                'biometric attendance',
                'fingerprint attendance',
                'face attendance'
            ],
            'type' => 'product',
            'name' => 'Attendance Machine (Biometrics)'
        ],

        [
            'keywords' => [
                'gps device',
                'gps devices',
                'gps'
            ],
            'type' => 'product',
            'name' => 'GPS Devices'
        ]
    ];

    foreach ($rules as $rule) {

        foreach ($rule['keywords'] as $keyword) {

            if (strpos($text, normalizeText($keyword)) !== false) {

                $items = $rule['type'] === 'service'
                    ? $kb['services']
                    : $kb['products'];

                $item = findItemByName(
                    $items,
                    $rule['name']
                );

                if ($item) {
                    return [
                        'type' => $rule['type'],
                        'item' => $item
                    ];
                }
            }
        }
    }

    return null;
}

/* =========================================
   LOCAL SERVICE / PRODUCT RESPONSE
========================================= */

function buildLocalReply($message, $match)
{
    $text = normalizeText($message);

    $item = $match['item'];
    $type = $match['type'];

    $name = $item['name'] ?? '';
    $description = getItemDescription($item);

    if ($type === 'product') {
        return "Yes, Migratech offers {$name}. {$description}";
    }

    return "Yes, Migratech provides {$name}. {$description}";
}

/* =========================================
   SMART FAQ MATCH
========================================= */

function findFaqMatch($message, $faq)
{
    $text = normalizeText($message);
    $tokens = array_unique(tokenize($text));

    $bestItem = null;
    $bestScore = 0;

    foreach ($faq as $item) {

        if (!is_array($item)) {
            continue;
        }

        $question = normalizeText(
            $item['question'] ?? ''
        );

        if ($question === '') {
            continue;
        }

        /* Exact match */
        if ($text === $question) {
            return $item;
        }

        $score = 0;

        /* Full question inside message */
        if (strpos($text, $question) !== false) {
            $score += 100;
        }

        /* Question word matching */
        $questionTokens = array_unique(
            tokenize($question)
        );

        foreach ($questionTokens as $word) {

            if (strlen($word) < 3) {
                continue;
            }

            if (in_array($word, $tokens, true)) {
                $score += 5;
            }
        }

        /* Optional keywords */
        if (
            isset($item['keywords']) &&
            is_array($item['keywords'])
        ) {
            foreach ($item['keywords'] as $keyword) {

                $keyword = normalizeText($keyword);

                if (
                    $keyword !== '' &&
                    strpos($text, $keyword) !== false
                ) {
                    $score += 30;
                }
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestItem = $item;
        }
    }

    /*
       Require a meaningful match.
       This prevents unrelated questions from
       matching an FAQ accidentally.
    */
    if ($bestItem !== null && $bestScore >= 10) {
        return $bestItem;
    }

    return null;
}

/* =========================================
   HUMAN HANDOFF
========================================= */

function isHumanRequest($message)
{
    $text = normalizeText($message);

    $patterns = [
        'talk to a human',
        'speak to a human',
        'talk with a human',
        'speak with a human',
        'talk to someone',
        'speak to someone',
        'talk with someone',
        'speak with someone',
        'talk to your team',
        'speak to your team',
        'contact your team',
        'sales team',
        'contact sales',
        'need quotation',
        'want quotation',
        'get quotation',
        'need a quote',
        'want a quote',
        'custom requirement',
        'custom requirements',
        'place an order',
        'want to order',
        'call me',
        'please call',
        'contact me',
        'need human assistance'
    ];

    foreach ($patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

/* =========================================
   GEMINI FALLBACK
========================================= */

function askGemini($message, $kb)
{
    if (!function_exists('curl_init')) {
        return null;
    }

    if (
        !defined('GEMINI_API_KEY') ||
        !defined('GEMINI_MODEL') ||
        empty(GEMINI_API_KEY) ||
        empty(GEMINI_MODEL)
    ) {
        return null;
    }

    $knowledge = json_encode(
        $kb,
        JSON_UNESCAPED_UNICODE |
        JSON_PRETTY_PRINT
    );

    $prompt = <<<PROMPT
You are the official virtual assistant for Migratech Softwares.

Answer the user's question using ONLY the company information below.

Rules:
- Be concise and natural.
- Do not invent services, products, prices or policies.
- If information is unavailable, say so.
- For quotation, pricing or custom requirements, suggest contacting Migratech.

MIGRATECH KNOWLEDGE:

{$knowledge}

USER QUESTION:

{$message}
PROMPT;

    $url =
        'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode(GEMINI_MODEL)
        . ':generateContent?key='
        . rawurlencode(GEMINI_API_KEY);

    $payload = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT =>
            defined('GEMINI_TIMEOUT')
                ? GEMINI_TIMEOUT
                : 30
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $data = json_decode($response, true);

    if (
        isset(
            $data['candidates'][0]['content']['parts'][0]['text']
        )
    ) {
        return trim(
            $data['candidates'][0]['content']['parts'][0]['text']
        );
    }

    return null;
}

/* =========================================
   MAIN REQUEST
========================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(
        false,
        'Invalid request method.'
    );
}

$rawInput = file_get_contents('php://input');

$data = json_decode(
    $rawInput,
    true
);

if (!is_array($data)) {
    sendResponse(
        false,
        'Invalid JSON request.'
    );
}

$message = isset($data['message'])
    ? trim($data['message'])
    : '';

if ($message === '') {
    sendResponse(
        false,
        'Please enter a message.'
    );
}

$kb = getKnowledgeBase();

/* =========================================
   1. HUMAN REQUEST
========================================= */

if (isHumanRequest($message)) {
    sendResponse(
        true,
        'Sure! For pricing, quotations, custom requirements, or direct assistance, you can contact the Migratech team.',
        true
    );
}

/* =========================================
   2. COMPANY INFORMATION
========================================= */

$companyIntent = findCompanyIntent($message);

if ($companyIntent !== null) {

    $reply = buildCompanyReply(
        $companyIntent,
        $kb['company']
    );

    if ($reply !== null) {
        sendResponse(
            true,
            $reply,
            false
        );
    }
}

/* =========================================
   3. SERVICE / PRODUCT PRIORITY
========================================= */

$match = findPriorityMatch(
    $message,
    $kb
);

if ($match) {

    $reply = buildLocalReply(
        $message,
        $match
    );

    sendResponse(
        true,
        $reply,
        false
    );
}

/* =========================================
   4. FAQ
========================================= */

$faqMatch = findFaqMatch(
    $message,
    $kb['faq']
);

if ($faqMatch) {

    $answer = $faqMatch['answer']
        ?? $faqMatch['response']
        ?? '';

    if ($answer !== '') {
        sendResponse(
            true,
            $answer,
            false
        );
    }
}

/* =========================================
   5. GENERIC SERVICE / PRODUCT MATCH
========================================= */

$match = null;
$text = normalizeText($message);
$tokens = tokenize($text);

$bestScore = 0;
$bestItem = null;
$bestType = null;

foreach ([
    'services' => 'service',
    'products' => 'product'
] as $collection => $type) {

    foreach ($kb[$collection] as $item) {

        if (!is_array($item)) {
            continue;
        }

        $name = normalizeText(
            $item['name'] ?? ''
        );

        if ($name === '') {
            continue;
        }

        $score = 0;

        if ($text === $name) {
            $score += 100;
        }

        if (strpos($text, $name) !== false) {
            $score += 60;
        }

        foreach (tokenize($name) as $word) {

            if (
                strlen($word) >= 3 &&
                in_array($word, $tokens, true)
            ) {
                $score += 10;
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $bestItem = $item;
            $bestType = $type;
        }
    }
}

if ($bestItem !== null && $bestScore >= 20) {

    sendResponse(
        true,
        buildLocalReply(
            $message,
            [
                'type' => $bestType,
                'item' => $bestItem
            ]
        ),
        false
    );
}

/* =========================================
   6. GEMINI FALLBACK
========================================= */

$geminiReply = askGemini(
    $message,
    $kb
);

if (
    $geminiReply !== null &&
    $geminiReply !== ''
) {
    sendResponse(
        true,
        $geminiReply,
        false
    );
}

/* =========================================
   7. FINAL FALLBACK
========================================= */

sendResponse(
    true,
    "I'm sorry, I don't have enough information to answer that accurately. You can contact the Migratech team for further assistance.",
    true
);