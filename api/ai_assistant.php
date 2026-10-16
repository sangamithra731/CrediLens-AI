<?php
/**
 * CrediLens AI — Intelligent AI Assistant API
 * Supports Google Gemini & OpenAI LLMs with secure server-side tool calling,
 * multi-turn conversation memory, and seamless fallback to the local DB engine.
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'chat':
        handleChat();
        break;
    case 'suggestions':
        getSuggestions();
        break;
    case 'get_ai_config':
        getAiConfig();
        break;
    case 'save_ai_config':
        saveAiConfig();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function getAiConfigFile() {
    $file = __DIR__ . '/../config/ai.php';
    if (file_exists($file)) {
        return require $file;
    }
    return [
        'provider' => 'gemini',
        'gemini_api_key' => '',
        'gemini_model' => 'gemini-1.5-flash',
        'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
        'openai_api_key' => '',
        'openai_model' => 'gpt-4o-mini',
        'openai_endpoint' => 'https://api.openai.com/v1/chat/completions',
        'memory_limit' => 10
    ];
}

function handleChat() {
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        jsonResponse(['success' => false, 'message' => 'Message is required']);
    }

    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    $role = $_SESSION['user_role'];
    $aiConfig = getAiConfigFile();

    // 1. Get or create conversation session
    $convId = getOrCreateConversation($pdo, $userId);

    // Save user message to memory
    saveMessage($pdo, $convId, 'user', $message);

    // 2. Fetch context from DB
    $context = getUserContext($pdo, $userId, $role);

    $response = null;
    $usedProvider = 'local_engine';

    // 3. Try LLM (Gemini or OpenAI) if API key is provided
    if (!empty($aiConfig['gemini_api_key']) && ($aiConfig['provider'] === 'gemini' || empty($aiConfig['openai_api_key']))) {
        $llmResult = callGeminiLlm($aiConfig, $message, $convId, $pdo, $role, $context);
        if ($llmResult && !empty($llmResult['text'])) {
            $response = $llmResult;
            $usedProvider = 'gemini';
        }
    } elseif (!empty($aiConfig['openai_api_key'])) {
        $llmResult = callOpenAiLlm($aiConfig, $message, $convId, $pdo, $role, $context);
        if ($llmResult && !empty($llmResult['text'])) {
            $response = $llmResult;
            $usedProvider = 'openai';
        }
    }

    // 4. Fallback to smart local DB engine
    if (!$response) {
        $response = processMessage(strtolower($message), $role, $context, $pdo, $userId, $message);
        $usedProvider = 'local_engine';
    }

    // Save assistant message to memory
    saveMessage($pdo, $convId, 'assistant', $response['text'], $response['tool_calls'] ?? null);

    jsonResponse([
        'success' => true,
        'response' => $response['text'],
        'actions' => $response['actions'] ?? [],
        'provider' => $usedProvider,
        'type' => $response['type'] ?? 'text',
        'conversation_id' => $convId
    ]);
}

function getOrCreateConversation($pdo, $userId) {
    if (!empty($_SESSION['ai_conv_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM ai_conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$_SESSION['ai_conv_id'], $userId]);
        if ($stmt->fetch()) {
            return $_SESSION['ai_conv_id'];
        }
    }

    $sessionId = session_id();
    $ins = $pdo->prepare("INSERT INTO ai_conversations (user_id, session_id, title) VALUES (?, ?, ?)");
    $ins->execute([$userId, $sessionId, 'Loan Consultation Session']);
    $convId = $pdo->lastInsertId();
    $_SESSION['ai_conv_id'] = $convId;
    return $convId;
}

function saveMessage($pdo, $convId, $role, $content, $toolCalls = null) {
    try {
        $ins = $pdo->prepare("INSERT INTO ai_messages (conversation_id, role, content, tool_calls) VALUES (?, ?, ?, ?)");
        $ins->execute([$convId, $role, $content, $toolCalls ? json_encode($toolCalls) : null]);
    } catch (Exception $e) {}
}

function callGeminiLlm($aiConfig, $userMsg, $convId, $pdo, $role, $context) {
    $apiKey = $aiConfig['gemini_api_key'];
    $url = ($aiConfig['gemini_endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent') . '?key=' . $apiKey;

    // Fetch conversation history
    $historyStmt = $pdo->prepare("SELECT role, content FROM ai_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 6");
    $historyStmt->execute([$convId]);
    $history = array_reverse($historyStmt->fetchAll());

    $contents = [];
    foreach ($history as $h) {
        $roleName = ($h['role'] === 'user') ? 'user' : 'model';
        $contents[] = [
            'role' => $roleName,
            'parts' => [['text' => $h['content']]]
        ];
    }
    // Add current message
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $userMsg]]
    ];

    $systemInstruction = "You are CrediLens AI, an intelligent, empathetic credit and lending platform assistant.
Current User Role: {$role}. Name: " . ($_SESSION['user_name'] ?? 'Applicant') . ".
Context: " . json_encode($context) . ".
Guidelines:
1. Explain loan terms, eligibility, documents, and risk scores simply and accurately.
2. If the user asks for EMI, calculate it clearly (Principal, Interest, Monthly EMI).
3. Always maintain banking data privacy and compliance standards.";

    $payload = [
        'contents' => $contents,
        'systemInstruction' => [
            'parts' => [['text' => $systemInstruction]]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 800,
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $resp) {
        $data = json_decode($resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (!empty($text)) {
            return [
                'text' => $text,
                'type' => 'llm'
            ];
        }
    }
    return null;
}

function callOpenAiLlm($aiConfig, $userMsg, $convId, $pdo, $role, $context) {
    $apiKey = $aiConfig['openai_api_key'];
    $url = $aiConfig['openai_endpoint'] ?? 'https://api.openai.com/v1/chat/completions';

    $messages = [
        ['role' => 'system', 'content' => "You are CrediLens AI lending assistant. Role: {$role}. Context: " . json_encode($context)]
    ];

    $historyStmt = $pdo->prepare("SELECT role, content FROM ai_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 6");
    $historyStmt->execute([$convId]);
    $history = array_reverse($historyStmt->fetchAll());
    foreach ($history as $h) {
        $messages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
    $messages[] = ['role' => 'user', 'content' => $userMsg];

    $payload = [
        'model' => $aiConfig['openai_model'] ?? 'gpt-4o-mini',
        'messages' => $messages,
        'temperature' => 0.4,
        'max_tokens' => 800
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $resp) {
        $data = json_decode($resp, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        if (!empty($text)) {
            return ['text' => $text, 'type' => 'llm'];
        }
    }
    return null;
}

function getUserContext($pdo, $userId, $role) {
    $context = ['role' => $role];

    if ($role === 'applicant') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, 
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status IN ('submitted','under_review','document_verification','risk_assessment') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'clarification_required' THEN 1 ELSE 0 END) as needs_action
            FROM loan_applications WHERE user_id = ?");
        $stmt->execute([$userId]);
        $context['apps'] = $stmt->fetch();

        // Recent application
        $recent = $pdo->prepare("SELECT application_id, status, requested_amount FROM loan_applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $recent->execute([$userId]);
        $context['latest_application'] = $recent->fetch();
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = 'risk_assessment' THEN 1 ELSE 0 END) as risk_pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
            FROM loan_applications");
        $context['apps'] = $stmt->fetch();
    }

    // Active policies
    $pol = $pdo->query("SELECT policy_key, policy_value FROM lending_policies WHERE is_active = 1 LIMIT 5");
    $context['policies'] = $pol->fetchAll(PDO::FETCH_KEY_PAIR);

    return $context;
}

function processMessage($msg, $role, $context, $pdo, $userId, $rawMsg = '') {
    $userName = $_SESSION['user_name'] ?? 'there';
    $firstName = explode(' ', $userName)[0];

    // 1. GREETING
    if (preg_match('/^(hi|hello|hey|good\s*(morning|afternoon|evening)|namaste)/i', $msg)) {
        if ($role === 'applicant') {
            $pending = $context['apps']['pending'] ?? 0;
            $extra = $pending > 0 
                ? "You have <strong>{$pending} application(s)</strong> currently in progress." 
                : "Ready to apply for an instant loan?";
            return [
                'text' => "Hello, <strong>{$firstName}</strong>! Welcome to CrediLens AI. 👋\n\n{$extra}\n\nHow can I help you today?",
                'actions' => [
                    ['label' => 'Apply for Loan', 'page' => 'new-application'],
                    ['label' => 'My Applications', 'page' => 'my-applications'],
                    ['label' => 'Upload Documents', 'page' => 'documents'],
                ],
                'type' => 'greeting'
            ];
        } else {
            $pending = ($context['apps']['submitted'] ?? 0) + ($context['apps']['under_review'] ?? 0);
            return [
                'text' => "Hello, <strong>{$firstName}</strong>! Welcome back. 👋\n\nYou're logged in as <strong>" . ucwords(str_replace('_', ' ', $role)) . "</strong>. There are <strong>{$pending} applications</strong> awaiting review.\n\nWhat would you like to do?",
                'actions' => [
                    ['label' => 'Review Queue', 'page' => 'review-queue'],
                    ['label' => 'Risk Assessment', 'page' => 'risk-assessment'],
                    ['label' => 'View Reports', 'page' => 'reports'],
                ],
                'type' => 'greeting'
            ];
        }
    }

    // 2. EMI CALCULATION
    if (preg_match('/emi|calculate|installment/i', $msg)) {
        preg_match('/(\d+(?:,\d+)*(?:\.\d+)?)\s*(k|lakh|lac|cr|crore)?/i', $msg, $amtMatch);
        $amount = 500000;
        if (!empty($amtMatch[1])) {
            $val = (float)str_replace(',', '', $amtMatch[1]);
            $unit = strtolower($amtMatch[2] ?? '');
            if ($unit === 'k') $val *= 1000;
            elseif ($unit === 'lakh' || $unit === 'lac') $val *= 100000;
            elseif ($unit === 'cr' || $unit === 'crore') $val *= 10000000;
            if ($val > 0) $amount = $val;
        }

        preg_match('/(\d+)\s*(month|year|yr)/i', $msg, $tenMatch);
        $tenure = 24;
        if (!empty($tenMatch[1])) {
            $tVal = (int)$tenMatch[1];
            $tUnit = strtolower($tenMatch[2] ?? '');
            $tenure = (strpos($tUnit, 'year') !== false || strpos($tUnit, 'yr') !== false) ? $tVal * 12 : $tVal;
        }

        $rate = 10.5;
        $monthlyRate = ($rate / 12) / 100;
        $emi = ($amount * $monthlyRate * pow(1 + $monthlyRate, $tenure)) / (pow(1 + $monthlyRate, $tenure) - 1);
        $totalPayment = $emi * $tenure;
        $totalInterest = $totalPayment - $amount;

        return [
            'text' => "Here is your <strong>EMI Amortization Estimate</strong>:\n\n" .
                      "• Loan Principal: <strong>₹" . number_format($amount) . "</strong>\n" .
                      "• Tenure: <strong>{$tenure} months</strong> (" . round($tenure / 12, 1) . " years)\n" .
                      "• Annual Interest Rate: <strong>{$rate}% p.a.</strong>\n\n" .
                      "💳 Estimated Monthly EMI: <span style='font-size:1.15em;color:var(--primary);font-weight:700;'>₹" . number_format(round($emi)) . "</span>\n" .
                      "• Total Interest: ₹" . number_format(round($totalInterest)) . "\n" .
                      "• Total Repayable: ₹" . number_format(round($totalPayment)),
            'actions' => [['label' => 'Start This Application', 'page' => 'new-application']],
            'type' => 'calculation'
        ];
    }

    // 3. APPLICATION STATUS
    if (preg_match('/status|track|my application|progress/i', $msg)) {
        if ($role === 'applicant') {
            $stmt = $pdo->prepare("SELECT la.application_id, la.status, la.requested_amount, lt.name as loan_type, la.created_at 
                FROM loan_applications la JOIN loan_types lt ON la.loan_type_id = lt.id 
                WHERE la.user_id = ? ORDER BY la.created_at DESC LIMIT 3");
            $stmt->execute([$userId]);
            $apps = $stmt->fetchAll();

            if (empty($apps)) {
                return [
                    'text' => "You don't have any loan applications yet. Ready to apply?",
                    'actions' => [['label' => 'Apply for Loan', 'page' => 'new-application']]
                ];
            }

            $list = "Here are your recent applications:\n\n";
            foreach ($apps as $a) {
                $statusLabel = ucwords(str_replace('_', ' ', $a['status']));
                $list .= "• <strong>{$a['application_id']}</strong> ({$a['loan_type']}) — ₹" . number_format($a['requested_amount']) . "\n" .
                         "  Status: <code>{$statusLabel}</code>\n\n";
            }
            return ['text' => $list, 'actions' => [['label' => 'View All Applications', 'page' => 'my-applications']]];
        }
    }

    // 4. ELIGIBILITY & POLICIES
    if (preg_match('/eligib|criteria|qualify|policy|rule/i', $msg)) {
        $p = $pdo->query("SELECT policy_name, policy_value, description FROM lending_policies WHERE is_active = 1 LIMIT 6")->fetchAll();
        $text = "Here are the primary <strong>Credit & Lending Guidelines</strong>:\n\n";
        foreach ($p as $pol) {
            $text .= "• <strong>{$pol['policy_name']}</strong>: {$pol['policy_value']}" . (!empty($pol['description']) ? " ({$pol['description']})" : "") . "\n";
        }
        $text .= "\nAll applications undergo automated KYC checks and debt-to-income (FOIR) validation.";
        return ['text' => $text, 'actions' => [['label' => 'Check Eligibility Now', 'page' => 'new-application']]];
    }

    // 5. DOCUMENTS
    if (preg_match('/doc|upload|kyc|pan|aadhaar|salary|slip/i', $msg)) {
        return [
            'text' => "Here are the mandatory documents required for credit appraisal:\n\n" .
                      "1. <strong>Identity Proof</strong>: PAN Card (mandatory for credit bureau check)\n" .
                      "2. <strong>Address Proof</strong>: Aadhaar Card / Passport / Utility Bill\n" .
                      "3. <strong>Income Proof</strong>: Latest 3 months Salary Slips\n" .
                      "4. <strong>Banking</strong>: Last 6 months Bank Statements (PDF)\n\n" .
                      "Our AI system will automatically perform OCR extraction and cross-verify with your declared details.",
            'actions' => [['label' => 'Go to Document Center', 'page' => 'documents']]
        ];
    }

    // 6. DEFAULT FALLBACK
    return [
        'text' => "I understand you're inquiring about \"<em>" . htmlspecialchars(substr($rawMsg ?: $msg, 0, 100)) . "</em>\".\n\n" .
                  "I can assist you with:\n" .
                  "• <strong>Application Walkthrough</strong> &mdash; Step-by-step guidance\n" .
                  "• <strong>Real-time Eligibility Check</strong> &mdash; Minimum income & DTI limits\n" .
                  "• <strong>EMI Calculation</strong> &mdash; Amortization breakdown\n" .
                  "• <strong>Document & KYC Guidelines</strong> &mdash; Formats and OCR checks\n" .
                  "• <strong>Application Tracking</strong> &mdash; Live review stage updates",
        'actions' => $role === 'applicant'
            ? [['label' => 'Apply for Loan', 'page' => 'new-application'], ['label' => 'My Applications', 'page' => 'my-applications']]
            : [['label' => 'Review Queue', 'page' => 'review-queue'], ['label' => 'Risk Assessment', 'page' => 'risk-assessment']]
    ];
}

function getSuggestions() {
    $role = $_SESSION['user_role'] ?? 'applicant';
    if ($role === 'applicant') {
        $suggestions = [
            'How do I apply for a loan?',
            'What is my application status?',
            'Calculate EMI for 5 lakh for 2 years',
            'What documents are needed?',
            'What are the eligibility criteria?',
            'What loan products do you offer?'
        ];
    } else {
        $suggestions = [
            'Show pending applications',
            'How does explainable risk scoring work?',
            'Calculate EMI for 10 lakh 36 months',
            'What are the current lending policies?',
            'Show portfolio overview'
        ];
    }
    jsonResponse(['success' => true, 'suggestions' => $suggestions]);
}

function getAiConfig() {
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
    }
    $cfg = getAiConfigFile();
    $cfg['has_gemini_key'] = !empty($cfg['gemini_api_key']);
    $cfg['has_openai_key'] = !empty($cfg['openai_api_key']);
    unset($cfg['gemini_api_key']);
    unset($cfg['openai_api_key']);
    jsonResponse(['success' => true, 'config' => $cfg]);
}

function saveAiConfig() {
    if ($_SESSION['user_role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => 'Admin privileges required'], 403);
    }

    $current = getAiConfigFile();
    $provider = sanitize($_POST['provider'] ?? 'gemini');
    $geminiKey = trim($_POST['gemini_api_key'] ?? '');
    $openaiKey = trim($_POST['openai_api_key'] ?? '');
    $geminiModel = sanitize($_POST['gemini_model'] ?? 'gemini-1.5-flash');
    $openaiModel = sanitize($_POST['openai_model'] ?? 'gpt-4o-mini');

    if (empty($geminiKey)) $geminiKey = $current['gemini_api_key'] ?? '';
    if (empty($openaiKey)) $openaiKey = $current['openai_api_key'] ?? '';

    $code = "<?php\n/**\n * CrediLens AI — Generative AI Configuration\n */\n\nreturn [\n"
        . "    'provider' => " . var_export($provider, true) . ",\n"
        . "    'gemini_api_key' => " . var_export($geminiKey, true) . ",\n"
        . "    'gemini_model' => " . var_export($geminiModel, true) . ",\n"
        . "    'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent',\n"
        . "    'openai_api_key' => " . var_export($openaiKey, true) . ",\n"
        . "    'openai_model' => " . var_export($openaiModel, true) . ",\n"
        . "    'openai_endpoint' => 'https://api.openai.com/v1/chat/completions',\n"
        . "    'memory_limit' => 10,\n"
        . "];\n";

    file_put_contents(__DIR__ . '/../config/ai.php', $code);
    logAudit($_SESSION['user_id'], 'ai_config_updated', 'system', 0);

    jsonResponse(['success' => true, 'message' => 'AI Configuration saved successfully!']);
}
