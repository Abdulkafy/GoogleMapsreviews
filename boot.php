<?php
/*
 * بوت تليجرام لإدارة التقييمات - Telegram Review Manager Bot
 * نظام شرعي لجمع تقييمات حقيقية من العملاء عبر تليجرام
 */

class TelegramReviewBot {
    private $bot_token;
    private $api_url;
    private $db;
    private $admin_id;
    
    public function __construct($bot_token, $admin_id, $db_connection) {
        $this->bot_token = $bot_token;
        $this->api_url = "https://api.telegram.org/bot{$bot_token}/";
        $this->admin_id = $admin_id;
        $this->db = $db_connection;
        
        // إنشاء الجداول إذا لم تكن موجودة
        $this->createTables();
    }
    
    // إنشاء جداول قاعدة البيانات
    private function createTables() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS stores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_name VARCHAR(255) NOT NULL,
                place_id VARCHAR(255) UNIQUE NOT NULL,
                google_maps_url TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS customers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_id BIGINT UNIQUE NOT NULL,
                username VARCHAR(255),
                first_name VARCHAR(255),
                last_name VARCHAR(255),
                phone VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS review_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id BIGINT NOT NULL,
                store_id INT NOT NULL,
                status ENUM('pending', 'completed') DEFAULT 'pending',
                sent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_date TIMESTAMP NULL,
                FOREIGN KEY (customer_id) REFERENCES customers(telegram_id),
                FOREIGN KEY (store_id) REFERENCES stores(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS bot_admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_id BIGINT UNIQUE NOT NULL,
                username VARCHAR(255),
                permissions ENUM('admin', 'super_admin') DEFAULT 'admin',
                added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];
        
        foreach ($queries as $query) {
            $this->db->exec($query);
        }
        
        // إضافة المدير الرئيسي إذا لم يكن موجوداً
        $stmt = $this->db->prepare("INSERT IGNORE INTO bot_admins (telegram_id, permissions) VALUES (?, 'super_admin')");
        $stmt->execute([$this->admin_id]);
    }
    
    // إرسال رسالة عبر تليجرام
    public function sendMessage($chat_id, $text, $reply_markup = null) {
        $data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($reply_markup) {
            $data['reply_markup'] = $reply_markup;
        }
        
        return $this->apiRequest('sendMessage', $data);
    }
    
    // إرسال رسالة مع أزرار
    public function sendMessageWithKeyboard($chat_id, $text, $keyboard) {
        $reply_markup = [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        return $this->sendMessage($chat_id, $text, json_encode($reply_markup));
    }
    
    // إرسال رسالة مع إنلاين كيبورد
    public function sendMessageWithInline($chat_id, $text, $inline_keyboard) {
        $reply_markup = [
            'inline_keyboard' => $inline_keyboard
        ];
        
        return $this->sendMessage($chat_id, $text, json_encode($reply_markup));
    }
    
    // طلب API
    private function apiRequest($method, $data = []) {
        $url = $this->api_url . $method;
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return json_decode($result, true);
    }
    
    // معالجة الويب هوك
    public function handleWebhook($update) {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
        }
    }
    
    // معالجة الرسائل
    private function handleMessage($message) {
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $user = $message['from'];
        
        // تسجيل المستخدم إذا لم يكن مسجلاً
        $this->registerUser($user);
        
        // التحقق إذا كان المستخدم مديراً
        $is_admin = $this->isAdmin($user['id']);
        
        if ($is_admin) {
            $this->handleAdminMessage($chat_id, $text, $user);
        } else {
            $this->handleCustomerMessage($chat_id, $text, $user);
        }
    }
    
    // معالجة رسائل المدير
    private function handleAdminMessage($chat_id, $text, $user) {
        switch ($text) {
            case '/start':
                $this->showAdminPanel($chat_id);
                break;
                
            case '📊 الإحصائيات':
                $this->showStats($chat_id);
                break;
                
            case '🏪 إدارة المتاجر':
                $this->showStoresManagement($chat_id);
                break;
                
            case '👥 العملاء':
                $this->showCustomers($chat_id);
                break;
                
            case '📤 إرسال طلب تقييم':
                $this->askForReviewRequest($chat_id);
                break;
                
            default:
                if (strpos($text, 'إضافة متجر:') === 0) {
                    $this->addNewStore($chat_id, $text);
                } else {
                    $this->showAdminPanel($chat_id);
                }
        }
    }
    
    // معالجة رسائل العملاء
    private function handleCustomerMessage($chat_id, $text, $user) {
        switch ($text) {
            case '/start':
                $welcome_message = "مرحباً {$user['first_name']}! 👋\n\n";
                $welcome_message .= "شكراً لاهتمامك بمساعدتنا على التحسين!\n";
                $welcome_message .= "سنرسل لك طلبات التقييم بين الحين والآخر.\n\n";
                $welcome_message .= "لتقييم أي متجر، ما عليك سوى النقر على الرابط الذي سنرسله لك واتباع التعليمات.";
                
                $keyboard = [
                    [['text' => '📝 كيفية التقييم', 'callback_data' => 'how_to_review']],
                    [['text' => 'ℹ️ حول النظام', 'callback_data' => 'about_system']]
                ];
                
                $this->sendMessageWithInline($chat_id, $welcome_message, $keyboard);
                break;
                
            default:
                $this->sendMessage($chat_id, "استخدم /start للبدء 🚀");
        }
    }
    
    // معالجة الكولباك
    private function handleCallbackQuery($callback_query) {
        $chat_id = $callback_query['message']['chat']['id'];
        $data = $callback_query['data'];
        $user = $callback_query['from'];
        
        switch ($data) {
            case 'how_to_review':
                $instructions = "📝 <b>كيفية إضافة التقييم:</b>\n\n";
                $instructions .= "1. انقر على رابط المتجر الذي نرسله لك\n";
                $instructions .= "2. اختر عدد النجوم من 1 إلى 5 ⭐\n";
                $instructions .= "3. اكتب تعليقك عن تجربتك الحقيقية\n";
                $instructions .= "4. انشر التقييم\n\n";
                $instructions .= "شكراً لمساعدتنا في التحسين! 💙";
                
                $this->sendMessage($chat_id, $instructions);
                break;
                
            case 'about_system':
                $about = "ℹ️ <b>حول النظام:</b>\n\n";
                $about .= "هذا النظام مصمم لجمع تقييمات <b>حقيقية</b> و<b>صادقة</b> من العملاء.\n\n";
                $about .= "نؤمن بأن التقييمات الحقيقية تساعدنا على:\n";
                $about .= "✅ تحسين جودة الخدمة\n";
                $about .= "✅ فهم احتياجات العملاء\n";
                $about .= "✅ بناء سمعة طيبة\n\n";
                $about .= "شكراً لتعاونكم! 🌟";
                
                $this->sendMessage($chat_id, $about);
                break;
                
            case 'add_store':
                $this->sendMessage($chat_id, "لإضافة متجر جديد، أرسل:\n\n<code>إضافة متجر: اسم المتجر|Place_ID|رابط_جوجل_مابس</code>\n\nمثال:\n<code>إضافة متجر: متجر التقنية|ChIJd123456|https://g.page/mystore</code>");
                break;
        }
        
        // إجابة على الكولباك
        $this->apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callback_query['id']
        ]);
    }
    
    // عرض لوحة تحكم المدير
    private function showAdminPanel($chat_id) {
        $stats = $this->getStats();
        
        $message = "👨‍💼 <b>لوحة تحكم المدير</b>\n\n";
        $message .= "📊 <b>الإحصائيات:</b>\n";
        $message .= "• إجمالي العملاء: <b>{$stats['total_customers']}</b>\n";
        $message .= "• إجمالي المتاجر: <b>{$stats['total_stores']}</b>\n";
        $message .= "• طلبات التقييم: <b>{$stats['total_requests']}</b>\n";
        $message .= "• التقييمات المكتملة: <b>{$stats['completed_reviews']}</b>\n\n";
        $message .= "اختر من الأزرار أدناه:";
        
        $keyboard = [
            ['📊 الإحصائيات', '🏪 إدارة المتاجر'],
            ['👥 العملاء', '📤 إرسال طلب تقييم']
        ];
        
        $this->sendMessageWithKeyboard($chat_id, $message, $keyboard);
    }
    
    // عرض الإحصائيات
    private function showStats($chat_id) {
        $stats = $this->getStats();
        
        $message = "📊 <b>التقارير والإحصائيات</b>\n\n";
        $message .= "👥 <b>العملاء:</b>\n";
        $message .= "• إجمالي العملاء: <b>{$stats['total_customers']}</b>\n";
        $message .= "• عملاء جدد اليوم: <b>{$stats['new_customers_today']}</b>\n\n";
        
        $message .= "🏪 <b>المتاجر:</b>\n";
        $message .= "• إجمالي المتاجر: <b>{$stats['total_stores']}</b>\n\n";
        
        $message .= "📝 <b>التقييمات:</b>\n";
        $message .= "• طلبات التقييم: <b>{$stats['total_requests']}</b>\n";
        $message .= "• مكتملة: <b>{$stats['completed_reviews']}</b>\n";
        $message .= "• قيد الانتظار: <b>{$stats['pending_reviews']}</b>\n";
        $message .= "• نسبة الإنجاز: <b>{$stats['completion_rate']}%</b>";
        
        $this->sendMessage($chat_id, $message);
    }
    
    // إدارة المتاجر
    private function showStoresManagement($chat_id) {
        $stores = $this->getStoresList();
        
        $message = "🏪 <b>إدارة المتاجر</b>\n\n";
        
        if (empty($stores)) {
            $message .= "لا توجد متاجر مسجلة بعد.\n\n";
        } else {
            foreach ($stores as $store) {
                $message .= "• <b>{$store['store_name']}</b>\n";
                $message .= "  📍 {$store['place_id']}\n\n";
            }
        }
        
        $inline_keyboard = [[
            ['text' => '➕ إضافة متجر جديد', 'callback_data' => 'add_store']
        ]];
        
        $this->sendMessageWithInline($chat_id, $message, $inline_keyboard);
    }
    
    // إضافة متجر جديد
    private function addNewStore($chat_id, $text) {
        $parts = explode('|', str_replace('إضافة متجر:', '', $text));
        
        if (count($parts) < 3) {
            $this->sendMessage($chat_id, "❌ صيغة غير صحيحة. استخدم:\n\n<code>إضافة متجر: الاسم|Place_ID|الرابط</code>");
            return;
        }
        
        $store_name = trim($parts[0]);
        $place_id = trim($parts[1]);
        $maps_url = trim($parts[2]);
        
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO stores (store_name, place_id, google_maps_url) VALUES (?, ?, ?)"
            );
            $stmt->execute([$store_name, $place_id, $maps_url]);
            
            $this->sendMessage($chat_id, "✅ تم إضافة المتجر <b>{$store_name}</b> بنجاح!");
        } catch (Exception $e) {
            $this->sendMessage($chat_id, "❌ خطأ في إضافة المتجر: " . $e->getMessage());
        }
    }
    
    // عرض العملاء
    private function showCustomers($chat_id) {
        $customers = $this->getCustomersList();
        
        $message = "👥 <b>قائمة العملاء</b>\n\n";
        
        if (empty($customers)) {
            $message .= "لا يوجد عملاء مسجلين بعد.";
        } else {
            foreach (array_slice($customers, 0, 10) as $customer) {
                $message .= "• {$customer['first_name']}";
                if ($customer['username']) {
                    $message .= " (@{$customer['username']})";
                }
                $message .= "\n";
            }
            
            if (count($customers) > 10) {
                $message .= "\n... وعشرات غيرهم 💙";
            }
        }
        
        $this->sendMessage($chat_id, $message);
    }
    
    // طلب إرسال تقييم
    private function askForReviewRequest($chat_id) {
        $stores = $this->getStoresList();
        
        if (empty($stores)) {
            $this->sendMessage($chat_id, "❌ لا توجد متاجر مسجلة. أضف متاجر أولاً.");
            return;
        }
        
        $message = "📤 <b>إرسال طلب تقييم</b>\n\n";
        $message .= "اختر المتجر الذي تريد إرسال طلب التقييم له:";
        
        $inline_keyboard = [];
        foreach ($stores as $store) {
            $inline_keyboard[] = [
                ['text' => $store['store_name'], 'callback_data' => "send_review_{$store['id']}"]
            ];
        }
        
        $this->sendMessageWithInline($chat_id, $message, $inline_keyboard);
    }
    
    // إرسال طلبات التقييم للعملاء
    public function sendReviewToAllCustomers($store_id) {
        // الحصول على بيانات المتجر
        $stmt = $this->db->prepare("SELECT * FROM stores WHERE id = ?");
        $stmt->execute([$store_id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$store) {
            return false;
        }
        
        // الحصول على جميع العملاء
        $stmt = $this->db->prepare("SELECT * FROM customers");
        $stmt->execute();
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $success_count = 0;
        
        foreach ($customers as $customer) {
            $review_url = "https://search.google.com/local/writereview?placeid=" . $store['place_id'];
            
            $message = "📝 <b>طلب تقييم جديد</b>\n\n";
            $message .= "مرحباً {$customer['first_name']}!\n\n";
            $message .= "نسعد بتقييمك للمتجر: <b>{$store['store_name']}</b>\n\n";
            $message .= "لإضافة التقييم، انقر على الرابط أدناه واتبع التعليمات:\n";
            $message .= "<a href='{$review_url}'>📎 اضغط هنا للتقييم</a>\n\n";
            $message .= "شكراً لمساعدتنا في التحسين! 🌟";
            
            $keyboard = [
                [['text' => '📝 كيفية التقييم', 'callback_data' => 'how_to_review']]
            ];
            
            $result = $this->sendMessageWithInline($customer['telegram_id'], $message, $keyboard);
            
            if ($result['ok']) {
                // تسجيل طلب التقييم
                $this->logReviewRequest($customer['telegram_id'], $store_id);
                $success_count++;
            }
            
            // تأخير بين الرسائل لتجنب حظر تليجرام
            sleep(1);
        }
        
        return $success_count;
    }
    
    // تسجيل طلب التقييم
    private function logReviewRequest($customer_id, $store_id) {
        $stmt = $this->db->prepare(
            "INSERT INTO review_requests (customer_id, store_id) VALUES (?, ?)"
        );
        return $stmt->execute([$customer_id, $store_id]);
    }
    
    // الحصول على الإحصائيات
    private function getStats() {
        $stats = [];
        
        // إجمالي العملاء
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM customers");
        $stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // عملاء اليوم
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM customers WHERE DATE(created_at) = CURDATE()");
        $stats['new_customers_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // إجمالي المتاجر
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM stores");
        $stats['total_stores'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // طلبات التقييم
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM review_requests");
        $stats['total_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // التقييمات المكتملة
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM review_requests WHERE status = 'completed'");
        $stats['completed_reviews'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // قيد الانتظار
        $stats['pending_reviews'] = $stats['total_requests'] - $stats['completed_reviews'];
        
        // نسبة الإنجاز
        $stats['completion_rate'] = $stats['total_requests'] > 0 ? 
            round(($stats['completed_reviews'] / $stats['total_requests']) * 100, 2) : 0;
        
        return $stats;
    }
    
    // الحصول على قائمة المتاجر
    private function getStoresList() {
        $stmt = $this->db->query("SELECT * FROM stores ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // الحصول على قائمة العملاء
    private function getCustomersList() {
        $stmt = $this->db->query("SELECT * FROM customers ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // تسجيل المستخدم
    private function registerUser($user) {
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO customers (telegram_id, username, first_name, last_name) 
             VALUES (?, ?, ?, ?)"
        );
        
        return $stmt->execute([
            $user['id'],
            $user['username'] ?? null,
            $user['first_name'] ?? '',
            $user['last_name'] ?? ''
        ]);
    }
    
    // التحقق إذا كان المستخدم مديراً
    private function isAdmin($user_id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM bot_admins WHERE telegram_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    }
}

// الاستخدام الرئيسي
try {
    // إعدادات البوت
    $bot_token = "8392043927:AAGiPIvU3s6ekEsBhaO7dDaqGnu8_zIK6tk"; // ضع توكن البوت هنا
    $admin_id = "7700286311"; // ضع آيدي التليجرام الخاص بك هنا
    
    // الاتصال بقاعدة البيانات
    $db_host = 'localhost';
    $db_name = 'telegram_review_bot';
    $db_user = 'username';
    $db_pass = 'password';
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // إنشاء instance البوت
    $bot = new TelegramReviewBot($bot_token, $admin_id, $pdo);
    
    // معالجة الويب هوك
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);
    
    if ($update) {
        $bot->handleWebhook($update);
    }
    
} catch(Exception $e) {
    error_log("Bot error: " . $e->getMessage());
}

echo "Bot is running!";
?>

<!-- تعليمات التثبيت -->
<?php
/*
 * 📋 تعليمات تشغيل البوت:
 * 
 * 1. أنشئ بوت جديد عبر @BotFather واحصل على التوكن
 * 2. اضبط الويب هوك: 
 *    https://api.telegram.org/bot{YOUR_BOT_TOKEN}/setWebhook?url={YOUR_WEBHOOK_URL}
 * 3. عدل المتغيرات أعلاه (البوت توكن وآيدي المدير)
 * 4. أنشئ قاعدة البيانات وجداولها
 * 5. أضف المتاجر عبر لوحة المدير
 * 6. ابدأ بإرسال طلبات التقييم للعملاء
 * 
 * ⚠️ ملاحظة: هذا النظام شرعي ويجمع تقييمات حقيقية فقط
 */
?>