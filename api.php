<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';
function fail($m, $c = 400) { http_response_code($c); echo json_encode(['error' => $m], JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST দরকার', 405);
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail('ভুল অনুরোধ');
$tool = $in['tool'] ?? ''; $text = trim($in['input'] ?? ''); $o = $in['opts'] ?? []; $hist = $in['history'] ?? [];
if ($text === '' || mb_strlen($text) > 4000) fail('লেখা খালি বা অনেক বড় (সর্বোচ্চ ৪০০০ অক্ষর)');

// প্রতি IP-তে দৈনিক সীমা (আপনার API খরচ বাঁচাতে)
$ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'x')[0];
$dir = __DIR__ . '/_rate';
if (!is_dir($dir)) { @mkdir($dir); @file_put_contents($dir . '/.htaccess', "Deny from all\n"); }
$f = $dir . '/' . md5(trim($ip) . date('Ymd'));
$n = (int)@file_get_contents($f);
if ($n >= RATE_LIMIT) fail('আজকের ফ্রি সীমা শেষ। কাল আবার চেষ্টা করুন।', 429);
@file_put_contents($f, $n + 1);

$bn = 'উত্তর ব্যবহারকারীর ভাষায় দিন (বাংলায় লিখলে বাংলায়)। ';
$S = [
 'chat' => $bn . 'আপনি একজন সহায়ক ও বন্ধুত্বপূর্ণ AI সহকারী। পরিষ্কার ও সংক্ষিপ্ত উত্তর দিন।',
 'writer' => $bn . 'আপনি একজন দক্ষ কনটেন্ট রাইটার। ধরন: ' . ($o['type'] ?? 'Blog') . '। টোন: ' . ($o['tone'] ?? 'বন্ধুত্বপূর্ণ') . '। Facebook Post/Caption হলে সংক্ষিপ্ত ও আকর্ষণীয় করুন এবং উপযুক্ত ইমোজি ও হ্যাশট্যাগ দিন। Blog/Article হলে শিরোনাম (## দিয়ে), ভূমিকা, কয়েকটি অংশ ও উপসংহার দিন।',
 'imageprompt' => 'Convert the user idea into ONE detailed English image-generation prompt in the style: ' . ($o['style'] ?? 'realistic photo') . '. Output only the prompt, max 60 words, no quotes.',
 'script' => $bn . 'আপনি একজন ভিডিও স্ক্রিপ্ট রাইটার। প্ল্যাটফর্ম: ' . ($o['platform'] ?? 'YouTube') . '। দৈর্ঘ্য: ' . ($o['length'] ?? '৩ মিনিট') . '। হুক, মূল অংশ, কল-টু-অ্যাকশন আলাদা করে সময়সহ লিখুন।',
 'seo' => $bn . 'আপনি YouTube SEO বিশেষজ্ঞ। দিন: ## Title (৫টি অপশন), ## Description (প্রথম ২ লাইনে মূল কিওয়ার্ড), ## Tags (কমা দিয়ে ১৫টি), ## Keywords (বাংলা ও ইংরেজি)।',
 'translate' => 'Translate the user text into ' . ($o['to'] ?? 'English') . '. Output only the translation, nothing else.',
 'cv' => $bn . 'আপনি পেশাদার CV রাইটার। ভাষা: ' . ($o['lang'] ?? 'English') . '। ব্যবহারকারীর তথ্য থেকে পরিষ্কার CV লিখুন: ## দিয়ে সেকশন (Summary, Experience, Education, Skills), তালিকা - দিয়ে। কোনো তথ্য বানাবেন না; যা দেওয়া হয়নি তা বাদ দিন।',
];
if (!isset($S[$tool])) fail('অজানা টুল');

function post($url, $headers, $body) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE), CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 25]);
  $r = curl_exec($ch); $e = curl_error($ch); curl_close($ch);
  if ($r === false) fail('AI সার্ভারে সংযোগ হয়নি: ' . $e, 502);
  return json_decode($r, true);
}
$msgs = [];
if ($tool === 'chat' && is_array($hist)) foreach (array_slice($hist, -10) as $h) { if (!empty($h['t'])) $msgs[] = ['r' => $h['r'] === 'ai' ? 'assistant' : 'user', 't' => mb_substr($h['t'], 0, 2000)]; }
$msgs[] = ['r' => 'user', 't' => $text];

if (PROVIDER === 'claude') {
  $d = post('https://api.anthropic.com/v1/messages', ['Content-Type: application/json', 'x-api-key: ' . CLAUDE_KEY, 'anthropic-version: 2023-06-01'],
    ['model' => CLAUDE_MODEL, 'max_tokens' => 2000, 'system' => $S[$tool], 'messages' => array_map(fn($m) => ['role' => $m['r'], 'content' => $m['t']], $msgs)]);
  $out = $d['content'][0]['text'] ?? null;
} else {
  $d = post('https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent', ['Content-Type: application/json', 'x-goog-api-key: ' . GEMINI_KEY],
    ['systemInstruction' => ['parts' => [['text' => $S[$tool]]]], 'contents' => array_map(fn($m) => ['role' => $m['r'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['t']]]], $msgs), 'generationConfig' => ['maxOutputTokens' => 2000]]);
  $out = $d['candidates'][0]['content']['parts'][0]['text'] ?? null;
}
if (!$out) fail('AI উত্তর দেয়নি। API key ও মডেলের নাম ঠিক আছে কিনা দেখুন।', 502);
echo json_encode(['text' => $out], JSON_UNESCAPED_UNICODE);
