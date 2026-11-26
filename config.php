<?php
// ====== শুধু এই ফাইলটি এডিট করুন ======
define('PROVIDER', 'gemini');            // 'gemini' (ফ্রি টিয়ার আছে) অথবা 'claude'
define('GEMINI_KEY', 'এখানে_আপনার_GEMINI_API_KEY');
define('GEMINI_MODEL', 'gemini-2.5-flash');   // Google AI Studio-তে বর্তমান মডেলের নাম দেখে নিন
define('CLAUDE_KEY', 'এখানে_আপনার_CLAUDE_API_KEY');
define('CLAUDE_MODEL', 'claude-sonnet-5-5');
define('RATE_LIMIT', 40);                // প্রতি IP-তে দিনে সর্বোচ্চ কতবার AI ব্যবহার করা যাবে
