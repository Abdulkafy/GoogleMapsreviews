<?php
// ==================== إعدادات البوت ====================
define('BOT_TOKEN', '8348513467:AAF2gdtmVQ8YEO20e9QW53RHlDnkMgUmbRI');
define('WEBHOOK_URL', 'https://dev-sellingnumbers.pantheonsite.io/bot/bot.php');
define('ADMIN_ID', '7700286311');

// ==================== تفعيل تسجيل الأخطاء ====================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================== تسجيل جميع العمليات ====================
function log_message($message) {
    $log_file = __DIR__ . '/bot_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// ==================== معالجة الويب هوك ====================
function set_webhook() {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook";
    $postData = [
        'url' => WEBHOOK_URL,
        'drop_pending_updates' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    log_message("Webhook Set: $result");
    return $result;
}

// ==================== إرسال رسالة ====================
function send_message($chat_id, $text, $reply_markup = null) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $postData = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $postData['reply_markup'] = $reply_markup;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    log_message("Message Sent to $chat_id: $text");
    return $result;
}

// ==================== معالجة الأوامر ====================
function handle_command($update) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $first_name = $message['from']['first_name'] ?? 'مستخدم';
    
    log_message("Received: $text from $chat_id");
    
    switch($text) {
        case '/start':
            $welcome = "مرحباً <b>$first_name</b>! 👋\n\n";
            $welcome .= "أنا بوت مساعدك الذكي\n";
            $welcome .= "يمكنك استخدام الأوامر التالية:\n\n";
            $welcome .= "📊 /info - معلومات الحساب\n";
            $welcome .= "🆘 /help - المساعدة\n";
            $welcome .= "👤 /about - حول البوت";
            
            send_message($chat_id, $welcome);
            break;
            
        case '/info':
            $user_info = "🆔 <b>معلومات حسابك:</b>\n";
            $user_info .= "👤 الاسم: $first_name\n";
            $user_info .= "🆔 رقم التعريف: $chat_id\n";
            $user_info .= "📅 تاريخ اليوم: " . date('Y-m-d H:i:s');
            
            send_message($chat_id, $user_info);
            break;
            
        case '/help':
            $help = "❓ <b>مركز المساعدة:</b>\n\n";
            $help .= "إذا واجهتك أي مشكلة، تواصل مع المطور:\n";
            $help .= "👨‍💻 المسؤول: @USERNAME\n\n";
            $help .= "الأوامر المتاحة:\n";
            $help .= "/start - بدء الاستخدام\n";
            $help .= "/info - معلومات الحساب\n";
            $help .= "/help - هذه الرسالة\n";
            $help .= "/about - معلومات عن البوت";
            
            send_message($chat_id, $help);
            break;
            
        case '/about':
            $about = "🤖 <b>حول البوت:</b>\n\n";
            $about .= "البوت مصمم خصيصاً لإدارة الحسابات\n";
            $about .= "الإصدار: 2.0\n";
            $about .= "تاريخ التحديث: " . date('Y-m-d');
            
            send_message($chat_id, $about);
            break;
            
        default:
            // معالجة الرسائل العادية
            if (!empty(trim($text))) {
                $response = "🤖 <b>تم استلام رسالتك:</b>\n\"$text\"\n\n";
                $response .= "استخدم /help لرؤية الأوامر المتاحة";
                send_message($chat_id, $response);
            }
            break;
    }
}

// ==================== التحقق من البوت ====================
function verify_bot() {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getMe";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($result, true);
    
    if ($data['ok']) {
        log_message("Bot Verified: " . $data['result']['username']);
        return true;
    } else {
        log_message("Bot Verification Failed: " . $result);
        return false;
    }
}

// ==================== البرنامج الرئيسي ====================
try {
    // تسجيل بدء التشغيل
    log_message("=== Bot Started ===");
    
    // الحصول على البيانات الواردة
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);
    
    // إذا كان طلب تعيين الويب هوك
    if (isset($_GET['setwebhook'])) {
        $result = set_webhook();
        echo "Webhook set: " . $result;
        log_message("Webhook setup via browser: $result");
        exit;
    }
    
    // إذا كان طلب التحقق من البوت
    if (isset($_GET['verify'])) {
        $verified = verify_bot();
        echo $verified ? "Bot verified successfully!" : "Bot verification failed!";
        exit;
    }
    
    // إذا كانت بيانات من تليجرام
    if (!empty($update)) {
        log_message("Update received: " . json_encode($update));
        
        // معالجة الرسالة
        if (isset($update['message'])) {
            handle_command($update);
        }
        
        // الرد لطلب تليجرام
        echo "OK";
    } else {
        // إذا لم تكن بيانات تليجرام، عرض واجهة التحكم
        echo "
        <!DOCTYPE html>
        <html dir='rtl'>
        <head>
            <meta charset='UTF-8'>
            <title>تحكم البوت</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; }
                .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
                .status { background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0; border-right: 4px solid #4caf50; }
                .btn { display: inline-block; background: #4caf50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 5px; transition: background 0.3s; }
                .btn:hover { background: #45a049; }
                .btn-danger { background: #f44336; }
                .btn-danger:hover { background: #da190b; }
                .log { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-top: 20px; font-family: monospace; white-space: pre-wrap; max-height: 300px; overflow-y: auto; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>🤖 لوحة تحكم البوت</h1>
                
                <div class='status'>
                    <strong>✅ البوت نشط وجاهز للعمل</strong><br>
                    <small>آخر تحديث: " . date('Y-m-d H:i:s') . "</small>
                </div>
                
                <div style='text-align: center;'>
                    <a href='?setwebhook=1' class='btn'>🔄 تعيين الويب هوك</a>
                    <a href='?verify=1' class='btn'>🔍 التحقق من البوت</a>
                    <a href='bot_log.txt' class='btn' target='_blank'>📊 عرض السجلات</a>
                </div>
                
                <h3>📝 آخر السجلات:</h3>
                <div class='log'>" . 
                    (file_exists('bot_log.txt') ? 
                     htmlspecialchars(implode("\n", array_slice(file('bot_log.txt'), -10))) : 
                     'لا توجد سجلات yet') . 
                "</div>
                
                <div style='margin-top: 20px; text-align: center; color: #666;'>
                    <small>مسار البوت: " . realpath(__FILE__) . "</small>
                </div>
            </div>
        </body>
        </html>";
    }
    
} catch (Exception $e) {
    // معالجة الأخطاء
    log_message("ERROR: " . $e->getMessage());
    http_response_code(500);
    echo "Error occurred: " . $e->getMessage();
}

log_message("=== Bot Finished ===");
?>