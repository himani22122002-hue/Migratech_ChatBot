<?php
header('Content-Type: application/json; charset=UTF-8');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'reply' => 'Method not allowed. Please use POST.',
        'needs_human' => true
    ]);
    exit;
}

// Include configuration
$configFile = __DIR__ . '/../config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'reply' => 'Configuration file missing.',
        'needs_human' => true
    ]);
    exit;
}
require_once $configFile;

// Get raw POST input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input) || empty($input['message']) || trim($input['message']) === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'reply' => 'Invalid request: "message" field is required.',
        'needs_human' => false
    ]);
    exit;
}

$userMessage = trim($input['message']);

// Load knowledge base files
$dataDir = __DIR__ . '/../data/';
$companyFile = $dataDir . 'company.json';
$servicesFile = $dataDir . 'services.json';
$productsFile = $dataDir . 'products.json';
$faqFile = $dataDir . 'faq.json';

$companyInfo = file_exists($companyFile) ? file_get_contents($companyFile) : '{}';
$servicesInfo = file_exists($servicesFile) ? file_get_contents($servicesFile) : '{}';
$productsInfo = file_exists($productsFile) ? file_get_contents($productsFile) : '{}';
$faqInfo = file_exists($faqFile) ? file_get_contents($faqFile) : '{}';

// Construct system prompt / context
$systemPrompt = "You are the official AI assistant for Migratech Softwares Private Limited. "
    . "Use the following knowledge base to answer user queries accurately, politely, and concisely. "
    . "If the answer is not in the knowledge base, provide helpful guidance based on company info or suggest contacting support.\n\n"
    . "=== KNOWLEDGE BASE ===\n"
    . "1. Company Information:\n" . $companyInfo . "\n\n"
    . "2. Services:\n" . $servicesInfo . "\n\n"
    . "3. Products:\n" . $productsInfo . "\n\n"
    . "4. Frequently Asked Questions (FAQs):\n" . $faqInfo . "\n"
    . "======================\n\n"
    . "User Query: " . $userMessage;

// Prepare Gemini API request
$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-1.5-flash';
$timeout = defined('GEMINI_TIMEOUT') ? GEMINI_TIMEOUT : 30;

if (empty($apiKey) || $apiKey === 'YOUR_GEMINI_API_KEY') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'reply' => 'Gemini API key is not configured.',
        'needs_human' => true
    ]);
    exit;
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $systemPrompt]
            ]
        ]
    ]
];

$responseJson = '';
$httpCode = 500;
$errorMsg = '';

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $responseJson = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== '') {
        $errorMsg = 'cURL error: ' . $curlError;
        $httpCode = 502;
    }
} else {
    // Try curl CLI / python fallback
    $payloadJson = json_encode($payload);
    $tmpFile = tempnam(sys_get_temp_dir(), 'gemini_req');
    file_put_contents($tmpFile, $payloadJson);
    
    $cmd = sprintf('curl -s -X POST -H "Content-Type: application/json" -d @%s %s', escapeshellarg($tmpFile), escapeshellarg($url));
    $responseJson = shell_exec($cmd);
    @unlink($tmpFile);
    
    $decodedTest = json_decode($responseJson, true);
    if (!empty($responseJson) && is_array($decodedTest) && !isset($decodedTest['error'])) {
        $httpCode = 200;
    } else {
        // Fallback to python execution with .encode('utf-8')
        $pyScript = sprintf(
            'import urllib.request, json\nurl = %s\ndata = %s.encode("utf-8")\nreq = urllib.request.Request(url, data=data, headers={"Content-Type": "application/json"}, method="POST")\ntry:\n    with urllib.request.urlopen(req) as resp:\n        print(resp.read().decode("utf-8"))\nexcept Exception as e:\n    print("ERR:", e)',
            var_export($url, true),
            var_export($payloadJson, true)
        );
        $pyFile = tempnam(sys_get_temp_dir(), 'gemini_py');
        file_put_contents($pyFile, str_replace('\\n', "\n", $pyScript));
        $responseJson = shell_exec('python ' . escapeshellarg($pyFile));
        @unlink($pyFile);
        
        $decodedPy = json_decode($responseJson, true);
        if (!empty($responseJson) && is_array($decodedPy) && !isset($decodedPy['error'])) {
            $httpCode = 200;
        } else {
            $errorMsg = 'Failed to connect via fallbacks: ' . trim($responseJson);
            $httpCode = 502;
        }
    }
}

if ($httpCode !== 200) {
    http_response_code(502);
    $errorData = json_decode($responseJson, true);
    $apiErr = isset($errorData['error']['message']) ? $errorData['error']['message'] : ($errorMsg !== '' ? $errorMsg : 'AI service returned error status ' . $httpCode);
    echo json_encode([
        'success' => false,
        'reply' => 'Error: ' . $apiErr,
        'needs_human' => true
    ]);
    exit;
}

$responseData = json_decode($responseJson, true);
$reply = '';

if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
} else {
    $reply = 'I received an empty response from the AI assistant.';
}

echo json_encode([
    'success' => true,
    'reply' => $reply,
    'needs_human' => false
]);
