<?php
// تحذير أمني ⚠️
/*
هذا البوت لأغراض تعليمية فقط
بيع وشراء الحسابات قد يخالف شروط استخدام المنصات
يجب استشارة محامٍ قبل التنفيذ
*/

// إعدادات الأساسيات
header('Content-Type: application/json');

// التوكن الخاص بالبوت
$BOT_TOKEN = '8558966612:AAHI1wtbngvCI1PHNR_NnjMbQu1PljfMkf8';

// أي دي المسؤول
$ADMIN_ID = '7700286311';

// تسجيل الأخطاء
file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Bot started\n", FILE_APPEND);

// دالة لإرسال طلبات إلى تليجرام API
function telegramAPI($method, $parameters = []) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/{$method}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] CURL Error: " . $error . "\n", FILE_APPEND);
    }
    
    return json_decode($response, true);
}

// تهيئة قاعدة البيانات
function init_db() {
    try {
        $conn = new SQLite3('marketplace.db');
        
        // جدول المنتجات
        $conn->exec('
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price REAL NOT NULL,
                type TEXT NOT NULL,
                country TEXT,
                stock INTEGER DEFAULT 0,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // جدول الطلبات
        $conn->exec('
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                product_id INTEGER,
                quantity INTEGER,
                total_price REAL,
                status TEXT DEFAULT "pending",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                payment_proof TEXT,
                customer_info TEXT,
                screenshot_sent BOOLEAN DEFAULT 0
            )
        ');
        
        // جدول الأرقام المتاحة للبيع
        $conn->exec('
            CREATE TABLE IF NOT EXISTS available_numbers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                number TEXT NOT NULL,
                product_id INTEGER,
                sold BOOLEAN DEFAULT 0,
                sold_to INTEGER,
                sold_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                verification_code TEXT,
                code_sent BOOLEAN DEFAULT 0,
                waiting_for_code BOOLEAN DEFAULT 0,
                FOREIGN KEY (product_id) REFERENCES products (id)
            )
        ');
        
        // جدول الحسابات المتاحة للبيع
        $conn->exec('
            CREATE TABLE IF NOT EXISTS available_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_data TEXT NOT NULL,
                product_id INTEGER,
                sold BOOLEAN DEFAULT 0,
                sold_to INTEGER,
                sold_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products (id)
            )
        ');
        
        // جدول جلسات المستخدمين
        $conn->exec('
            CREATE TABLE IF NOT EXISTS user_sessions (
                user_id INTEGER PRIMARY KEY,
                current_order_id INTEGER,
                waiting_for_screenshot BOOLEAN DEFAULT 0,
                waiting_for_code BOOLEAN DEFAULT 0,
                current_number_id INTEGER,
                last_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // جدول جلسات المسؤول
        $conn->exec('
            CREATE TABLE IF NOT EXISTS admin_sessions (
                chat_id INTEGER PRIMARY KEY,
                product_id INTEGER,
                action TEXT,
                data TEXT
            )
        ');
        
        // التحقق من وجود منتجات
        $stmt = $conn->prepare('SELECT COUNT(*) as count FROM products');
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($row['count'] == 0) {
            $products = [
                ['رقم أمريكي', 2.0, 'number', 'USA', 0, 'رقم أمريكي لتلقي الرسائل - يدعم جميع التطبيقات'],
                ['رقم سعودي', 3.0, 'number', 'KSA', 0, 'رقم سعودي لتلقي الرسائل - متوافق مع جميع التطبيقات'],
                ['رقم إماراتي', 4.0, 'number', 'UAE', 0, 'رقم إماراتي لتلقي الرسائل - خدمة سريعة'],
                ['حساب فيسبوك', 5.0, 'account', 'any', 0, 'حساب فيسبوك جاهز - صديق قديم'],
                ['حساب انستجرام', 4.0, 'account', 'any', 0, 'حساب انستجرام جاهز - متابعين'],
                ['حساب تويتر', 6.0, 'account', 'any', 0, 'حساب تويتر قديم - مؤكد']
            ];
            
            $stmt = $conn->prepare('
                INSERT INTO products (name, price, type, country, stock, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            
            foreach ($products as $product) {
                $stmt->bindValue(1, $product[0], SQLITE3_TEXT);
                $stmt->bindValue(2, $product[1], SQLITE3_FLOAT);
                $stmt->bindValue(3, $product[2], SQLITE3_TEXT);
                $stmt->bindValue(4, $product[3], SQLITE3_TEXT);
                $stmt->bindValue(5, $product[4], SQLITE3_INTEGER);
                $stmt->bindValue(6, $product[5], SQLITE3_TEXT);
                $stmt->execute();
            }
            
            file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Default products added\n", FILE_APPEND);
        }
        
        $conn->close();
        return true;
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

// استدعاء تهيئة قاعدة البيانات
init_db();

// الحصول على بيانات الويب هوك
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    echo "OK";
    exit;
}

// معالجة الرسالة
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $userId = $message['from']['id'];
    $firstName = $message['from']['first_name'];
    
    file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Message from {$firstName}: {$text}\n", FILE_APPEND);
    
    // معالجة الصور (لقطات الشاشة) - يجب أن تكون أولاً
    if (isset($message['photo'])) {
        handle_screenshot($chatId, $userId, $message['photo']);
    } 
    // أوامر المسؤول
    elseif ($userId == $ADMIN_ID && $text === '/admin') {
        show_admin_panel($chatId);
    }
    elseif ($userId == $ADMIN_ID && strpos($text, '/add_numbers') === 0) {
        handle_admin_add_numbers($chatId, $text);
    }
    elseif ($userId == $ADMIN_ID && strpos($text, '/add_accounts') === 0) {
        handle_admin_add_accounts($chatId, $text);
    }
    elseif ($userId == $ADMIN_ID && strpos($text, '/add_code') === 0) {
        handle_admin_add_code($chatId, $text);
    }
    // الأوامر العادية
    elseif ($text === '/start') {
        start_command($chatId, $firstName, $userId);
    } elseif (strpos($text, 'تم الدفع') !== false) {
        ask_for_screenshot($chatId, $userId);
    } elseif (strpos($text, 'طلب الكود') !== false || strpos($text, 'ارسل الكود') !== false || strpos($text, 'الكود') !== false) {
        ask_for_verification_code($chatId, $userId);
    } else {
        handle_regular_message($chatId, $userId, $text);
    }
} elseif (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $data = $callbackQuery['data'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $messageId = $callbackQuery['message']['message_id'];
    $userId = $callbackQuery['from']['id'];
    
    file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Callback: {$data} from user {$userId}\n", FILE_APPEND);
    
    handle_callback($chatId, $messageId, $data, $userId);
}

// أوامر البوت
function start_command($chatId, $firstName, $userId) {
    global $ADMIN_ID;
    
    $keyboard = [
        [
            ['text' => '🛒 شراء أرقام', 'callback_data' => 'buy_numbers'],
            ['text' => '🛒 شراء حسابات', 'callback_data' => 'buy_accounts']
        ],
        [
            ['text' => '💰 طرق الدفع', 'callback_data' => 'payment_methods'],
            ['text' => '📦 طلباتي', 'callback_data' => 'my_orders']
        ]
    ];
    
    // إضافة لوحة المسؤول فقط للمسؤول
    if ($userId == $ADMIN_ID) {
        $keyboard[] = [['text' => '👨‍💼 لوحة المسؤول', 'callback_data' => 'admin_panel']];
    }
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    $text = "مرحباً {$firstName} 👋\n\n"
          . "🎯 بوت بيع وشراء الحسابات والأرقام\n\n"
          . "اختر الخدمة التي تريدها:";
    
    sendMessage($chatId, $text, $reply_markup);
}

function handle_callback($chatId, $messageId, $data, $userId) {
    global $ADMIN_ID;
    
    file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Handling callback: {$data}\n", FILE_APPEND);
    
    switch ($data) {
        case 'buy_numbers':
            show_numbers($chatId, $messageId);
            break;
        case 'buy_accounts':
            show_accounts($chatId, $messageId);
            break;
        case 'payment_methods':
            show_payment_methods($chatId, $messageId);
            break;
        case 'my_orders':
            show_my_orders($chatId, $messageId, $userId);
            break;
        case 'request_code':
            ask_for_verification_code($chatId, $userId);
            break;
        case 'admin_panel':
            if ($userId == $ADMIN_ID) {
                show_admin_panel($chatId, $messageId);
            } else {
                sendMessage($chatId, "❌ ليس لديك صلاحية الوصول إلى هذه اللوحة");
            }
            break;
        case 'add_numbers':
            if ($userId == $ADMIN_ID) {
                show_add_numbers_menu($chatId, $messageId);
            }
            break;
        case 'add_accounts':
            if ($userId == $ADMIN_ID) {
                show_add_accounts_menu($chatId, $messageId);
            }
            break;
        case 'view_stats':
            if ($userId == $ADMIN_ID) {
                show_admin_stats($chatId, $messageId);
            }
            break;
        case 'view_products':
            if ($userId == $ADMIN_ID) {
                show_admin_products($chatId, $messageId);
            }
            break;
        case 'view_orders':
            if ($userId == $ADMIN_ID) {
                show_admin_orders($chatId, $messageId);
            }
            break;
        case 'back_main':
            start_command($chatId, '', $userId);
            break;
        case 'back_admin':
            if ($userId == $ADMIN_ID) {
                show_admin_panel($chatId, $messageId);
            }
            break;
        default:
            if (strpos($data, 'product_') === 0) {
                $product_id = intval(str_replace('product_', '', $data));
                show_product_details($chatId, $messageId, $product_id);
            } elseif (strpos($data, 'buy_') === 0) {
                $product_id = intval(str_replace('buy_', '', $data));
                start_purchase($chatId, $messageId, $product_id, $userId);
            } elseif (strpos($data, 'add_num_to_') === 0) {
                $product_id = intval(str_replace('add_num_to_', '', $data));
                ask_for_numbers_input($chatId, $messageId, $product_id);
            } elseif (strpos($data, 'add_acc_to_') === 0) {
                $product_id = intval(str_replace('add_acc_to_', '', $data));
                ask_for_accounts_input($chatId, $messageId, $product_id);
            }
            break;
    }
}

// دوال المستخدمين العاديين
function show_numbers($chatId, $messageId) {
    try {
        $conn = new SQLite3('marketplace.db');
        $stmt = $conn->prepare("SELECT * FROM products WHERE type = 'number' AND stock > 0");
        $result = $stmt->execute();
        
        $keyboard = [];
        while ($product = $result->fetchArray(SQLITE3_ASSOC)) {
            $available_count = get_available_numbers_count($product['id']);
            $keyboard[] = [[
                'text' => "{$product['name']} - \${$product['price']} ({$available_count} متوفر)",
                'callback_data' => "product_{$product['id']}"
            ]];
        }
        
        if (empty($keyboard)) {
            $keyboard[] = [['text' => '❌ لا توجد أرقام متاحة', 'callback_data' => 'back_main']];
        } else {
            $keyboard[] = [['text' => '🔙 رجوع', 'callback_data' => 'back_main']];
        }
        
        $reply_markup = ['inline_keyboard' => $keyboard];
        
        $text = "📱 أرقام التفعيل المتاحة:\n\n"
              . "🔢 بعد الشراء ستحصل على:\n"
              . "• الرقم المطلوب\n"
              . "• رمز التفعيل عند الطلب\n"
              . "• دعم فني متواصل\n\n"
              . "اختر الرقم الذي تريده:";
        
        editMessageText($chatId, $messageId, $text, $reply_markup);
        $conn->close();
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Error in show_numbers: " . $e->getMessage() . "\n", FILE_APPEND);
        sendMessage($chatId, "❌ حدث خطأ في تحميل الأرقام");
    }
}

function show_accounts($chatId, $messageId) {
    try {
        $conn = new SQLite3('marketplace.db');
        $stmt = $conn->prepare("SELECT * FROM products WHERE type = 'account' AND stock > 0");
        $result = $stmt->execute();
        
        $keyboard = [];
        while ($product = $result->fetchArray(SQLITE3_ASSOC)) {
            $available_count = get_available_accounts_count($product['id']);
            $keyboard[] = [[
                'text' => "{$product['name']} - \${$product['price']} ({$available_count} متوفر)",
                'callback_data' => "product_{$product['id']}"
            ]];
        }
        
        if (empty($keyboard)) {
            $keyboard[] = [['text' => '❌ لا توجد حسابات متاحة', 'callback_data' => 'back_main']];
        } else {
            $keyboard[] = [['text' => '🔙 رجوع', 'callback_data' => 'back_main']];
        }
        
        $reply_markup = ['inline_keyboard' => $keyboard];
        
        $text = "👤 الحسابات المتاحة:\n\nاختر الحساب الذي تريده:";
        
        editMessageText($chatId, $messageId, $text, $reply_markup);
        $conn->close();
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Error in show_accounts: " . $e->getMessage() . "\n", FILE_APPEND);
        sendMessage($chatId, "❌ حدث خطأ في تحميل الحسابات");
    }
}

function show_product_details($chatId, $messageId, $product_id) {
    try {
        $conn = new SQLite3('marketplace.db');
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $product = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($product) {
            $back_data = $product['type'] == 'number' ? 'buy_numbers' : 'buy_accounts';
            
            if ($product['type'] == 'number') {
                $available_count = get_available_numbers_count($product_id);
            } else {
                $available_count = get_available_accounts_count($product_id);
            }
            
            $keyboard = [
                [['text' => '🛒 شراء الآن', 'callback_data' => "buy_{$product['id']}"]],
                [['text' => '🔙 رجوع', 'callback_data' => $back_data]]
            ];
            
            $reply_markup = ['inline_keyboard' => $keyboard];
            
            $text = "📋 تفاصيل المنتج:\n\n"
                  . "🏷️ الاسم: {$product['name']}\n"
                  . "💰 السعر: \${$product['price']}\n"
                  . "🌍 الدولة: {$product['country']}\n"
                  . "📦 المتوفر: {$available_count}\n"
                  . "📝 الوصف: {$product['description']}\n\n";
            
            if ($product['type'] == 'number') {
                $text .= "📞 الخدمة تشمل:\n"
                      . "• الرقم جاهز للاستخدام\n"
                      . "• استقبال رموز التفعيل\n"
                      . "• دعم فني 24/7\n\n";
            }
            
            $text .= "اضغط على شراء الآن للمتابعة:";
            
            editMessageText($chatId, $messageId, $text, $reply_markup);
        } else {
            editMessageText($chatId, $messageId, '❌ المنتج غير موجود');
        }
        $conn->close();
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Error in show_product_details: " . $e->getMessage() . "\n", FILE_APPEND);
        sendMessage($chatId, "❌ حدث خطأ في تحميل تفاصيل المنتج");
    }
}

function start_purchase($chatId, $messageId, $product_id, $userId) {
    try {
        $conn = new SQLite3('marketplace.db');
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $product = $result->fetchArray(SQLITE3_ASSOC);
        
        // التحقق من المخزون
        if ($product['type'] == 'number') {
            $available_count = get_available_numbers_count($product_id);
        } else {
            $available_count = get_available_accounts_count($product_id);
        }
        
        if ($product && $available_count > 0) {
            // إنشاء طلب جديد
            $stmt = $conn->prepare("INSERT INTO orders (user_id, product_id, quantity, total_price, status) VALUES (?, ?, 1, ?, 'pending')");
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $product_id, SQLITE3_INTEGER);
            $stmt->bindValue(3, $product['price'], SQLITE3_FLOAT);
            $stmt->execute();
            $order_id = $conn->lastInsertRowID();
            
            // حفظ في جلسة المستخدم
            $stmt = $conn->prepare("INSERT OR REPLACE INTO user_sessions (user_id, current_order_id, waiting_for_screenshot) VALUES (?, ?, 0)");
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $order_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            $text = "🛒 تأكيد الطلب:\n\n"
                  . "المنتج: {$product['name']}\n"
                  . "السعر: \${$product['price']}\n"
                  . "رقم الطلب: #{$order_id}\n\n"
                  . "💳 طرق الدفع المتاحة:\n"
                  . "• Binance: 933609958\n"
                  . "• كريمي جوال: 3009999646 / 3019896772\n"
                  . "• محفظة جيب: 782551\n\n"
                  . "📋 خطوات الإكمال:\n"
                  . "1. قم بالتحويل لأحد الحسابات أعلاه\n"
                  . "2. احفظ screenshot لإشعار الدفع\n"
                  . "3. أرسل 'تم الدفع' ثم أرسل الصورة\n\n";
            
            if ($product['type'] == 'number') {
                $text .= "📞 بعد التأكيد:\n"
                      . "• ستصلك رسالة بالرقم\n"
                      . "• استخدم الرقم في التطبيق\n"
                      . "• اطلب الكود عندما تحتاجه\n\n";
            }
            
            $text .= "⏳ سيتم إرسال المنتج خلال 24 ساعة";
            
            editMessageText($chatId, $messageId, $text);
        } else {
            editMessageText($chatId, $messageId, '❌ المنتج غير متوفر حالياً');
        }
        $conn->close();
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Error in start_purchase: " . $e->getMessage() . "\n", FILE_APPEND);
        sendMessage($chatId, "❌ حدث خطأ في معالجة الطلب");
    }
}

function ask_for_screenshot($chatId, $userId) {
    try {
        $conn = new SQLite3('marketplace.db');
        
        // البحث عن آخر طلب pending للمستخدم
        $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $order = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($order) {
            // تحديث جلسة المستخدم لانتظار لقطة الشاشة
            $stmt = $conn->prepare("UPDATE user_sessions SET waiting_for_screenshot = 1 WHERE user_id = ?");
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->execute();
            
            $text = "📸 تم استلام طلبك!\n\n"
                  . "الآن قم بإرسال 📷 screenshot لإشعار الدفع:\n\n"
                  . "⚠️ تأكد من أن الصورة واضحة وتظهر:\n"
                  . "• المبلغ المحول\n"
                  . "• رقم الحساب المحول إليه\n"
                  . "• تاريخ ووقت التحويل\n\n"
                  . "⬇️ أرسل الصورة الآن:";
            
            sendMessage($chatId, $text);
        } else {
            sendMessage($chatId, '❌ لا يوجد طلب قيد المعالجة. ابدأ بطلب جديد باستخدام /start');
        }
        $conn->close();
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Error in ask_for_screenshot: " . $e->getMessage() . "\n", FILE_APPEND);
        sendMessage($chatId, "❌ حدث خطأ في معالجة الطلب");
    }
}

function handle_screenshot($chatId, $userId, $photo) {
    try {
        $conn = new SQLite3('marketplace.db');
        
        // التحقق إذا كان المستخدم ينتظر إرسال لقطة الشاشة
        $stmt = $conn->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND waiting_for_screenshot = 1");
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $session = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($session) {
            $order_id = $session['current_order_id'];
            
            // الحصول على أكبر حجم للصورة (الأفضل جودة)
            $largest_photo = end($photo);
            $file_id = $largest_photo['file_id'];
            
            // تحديث الطلب
            $stmt = $conn->prepare("UPDATE orders SET status = 'paid', payment_proof = ?, screenshot_sent = 1 WHERE id = ?");
            $stmt->bindValue(1, $file_id, SQLITE3_TEXT);
            $stmt->bindValue(2, $order_id, SQLITE3_INTEGER);
            $stmt->execute();
            
            // إرسال المنتج للمستخدم
            send_product_to_customer($userId, $order_id);
            
            // إرسال إشعار للمسؤول
            notify_admin($order_id, $file_id, $userId);
            
            // مسح جلسة المستخدم
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
            $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
            $stmt->execute();
            
            $text = "✅ تم استلام إثبات الدفع بنجاح!\n\n"
                  . "📦 تم تأكيد طلبك رقم #{$order_id}\n"
                  . "⏳ جاري إرسال المنتج...\n"
                  . "شكراً لثقتك بك 💙";
            
            sendMessage($chatId, $text);
        } else {
            sendMessage($chatId, '❌ لم أطلب منك إرسال صورة. استخدم الأزرار للبدء بطلب جديد.');
        }
        $conn->close();
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Error in handle_screenshot: " . $e->getMessage() . "\n", FILE_APPEND);
        sendMessage($chatId, "❌ حدث خطأ في معالجة الصورة");
    }
}

function send_product_to_customer($userId, $order_id) {
    $conn = new SQLite3('marketplace.db');
    
    $stmt = $conn->prepare("
        SELECT o.*, p.name, p.type 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        WHERE o.id = ?
    ");
    $stmt->bindValue(1, $order_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $order = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($order) {
        if ($order['type'] == 'number') {
            // إرسال رقم عشوائي متاح
            $number_stmt = $conn->prepare("SELECT * FROM available_numbers WHERE product_id = ? AND sold = 0 LIMIT 1");
            $number_stmt->bindValue(1, $order['product_id'], SQLITE3_INTEGER);
            $number_result = $number_stmt->execute();
            $available_number = $number_result->fetchArray(SQLITE3_ASSOC);
            
            if ($available_number) {
                // تحديث الرقم كمباع
                $update_stmt = $conn->prepare("UPDATE available_numbers SET sold = 1, sold_to = ?, sold_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->bindValue(1, $userId, SQLITE3_INTEGER);
                $update_stmt->bindValue(2, $available_number['id'], SQLITE3_INTEGER);
                $update_stmt->execute();
                
                // تحديث المخزون
                $conn->exec("UPDATE products SET stock = stock - 1 WHERE id = {$order['product_id']}");
                
                // حفظ في جلسة المستخدم
                $session_stmt = $conn->prepare("INSERT OR REPLACE INTO user_sessions (user_id, current_number_id, waiting_for_code) VALUES (?, ?, 0)");
                $session_stmt->bindValue(1, $userId, SQLITE3_INTEGER);
                $session_stmt->bindValue(2, $available_number['id'], SQLITE3_INTEGER);
                $session_stmt->execute();
                
                $keyboard = [
                    [['text' => '📲 طلب رمز التفعيل', 'callback_data' => 'request_code']],
                    [['text' => '🛒 طلب جديد', 'callback_data' => 'back_main']]
                ];
                
                $reply_markup = ['inline_keyboard' => $keyboard];
                
                $text = "🎉 تم تأكيد طلبك بنجاح!\n\n"
                      . "📦 المنتج: {$order['name']}\n"
                      . "🔢 الرقم: **{$available_number['number']}**\n"
                      . "💰 المبلغ: \${$order['total_price']}\n"
                      . "🆔 رقم الطلب: #{$order_id}\n\n"
                      . "📋 تعليمات الاستخدام:\n"
                      . "1. استخدم الرقم في التطبيق المطلوب\n"
                      . "2. انتظر رمز التحقق\n"
                      . "3. اضغط على 'طلب رمز التفعيل' للحصول على الكود\n\n"
                      . "⚠️ ملاحظة:\n"
                      . "• الرقم جاهز للاستخدام الآن\n"
                      . "• اطلب الكود فقط عندما يظهر لك طلب الرمز\n"
                      . "• الدعم متواصل 24/7\n\n"
                      . "شكراً لشرائك من متجرنا 💙";
                
                sendMessage($userId, $text, $reply_markup);
            } else {
                $text = "✅ تم استلام الدفع بنجاح!\n\n"
                      . "📦 المنتج: {$order['name']}\n"
                      . "💰 المبلغ: \${$order['total_price']}\n"
                      . "🆔 رقم الطلب: #{$order_id}\n\n"
                      . "⏳ سيتم إرسال الرقم لك خلال 24 ساعة\n"
                      . "شكراً لصبرك 💙";
                
                sendMessage($userId, $text);
            }
        } else {
            // إرسال حساب عشوائي متاح
            $account_stmt = $conn->prepare("SELECT * FROM available_accounts WHERE product_id = ? AND sold = 0 LIMIT 1");
            $account_stmt->bindValue(1, $order['product_id'], SQLITE3_INTEGER);
            $account_result = $account_stmt->execute();
            $available_account = $account_result->fetchArray(SQLITE3_ASSOC);
            
            if ($available_account) {
                // تحديث الحساب كمباع
                $update_stmt = $conn->prepare("UPDATE available_accounts SET sold = 1, sold_to = ?, sold_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->bindValue(1, $userId, SQLITE3_INTEGER);
                $update_stmt->bindValue(2, $available_account['id'], SQLITE3_INTEGER);
                $update_stmt->execute();
                
                // تحديث المخزون
                $conn->exec("UPDATE products SET stock = stock - 1 WHERE id = {$order['product_id']}");
                
                $text = "🎉 تم تأكيد طلبك بنجاح!\n\n"
                      . "📦 المنتج: {$order['name']}\n"
                      . "💼 بيانات الحساب:\n"
                      . "**{$available_account['account_data']}**\n\n"
                      . "💰 المبلغ: \${$order['total_price']}\n"
                      . "🆔 رقم الطلب: #{$order_id}\n\n"
                      . "⚠️ نصائح مهمة:\n"
                      . "• غير كلمة المرور بعد الدخول\n"
                      . "• فعّل التحقق بخطوتين\n"
                      . "• احتفظ بالبيانات في مكان آمن\n\n"
                      . "شكراً لشرائك من متجرنا 💙";
                
                sendMessage($userId, $text);
            } else {
                $text = "✅ تم استلام الدفع بنجاح!\n\n"
                      . "📦 المنتج: {$order['name']}\n"
                      . "💰 المبلغ: \${$order['total_price']}\n"
                      . "🆔 رقم الطلب: #{$order_id}\n\n"
                      . "⏳ سيتم إرسال الحساب لك خلال 24 ساعة\n"
                      . "شكراً لصبرك 💙";
                
                sendMessage($userId, $text);
            }
        }
    }
    $conn->close();
}

function ask_for_verification_code($chatId, $userId) {
    try {
        $conn = new SQLite3('marketplace.db');
        
        // البحث عن الرقم الحالي للمستخدم
        $stmt = $conn->prepare("
            SELECT an.*, p.name 
            FROM available_numbers an 
            JOIN products p ON an.product_id = p.id 
            WHERE an.sold_to = ? AND an.sold = 1 
            ORDER BY an.sold_at DESC 
            LIMIT 1
        ");
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $number = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($number) {
            if (!empty($number['verification_code'])) {
                // إذا كان هناك كود محفوظ
                $text = "📨 رمز التفعيل للرقم **{$number['number']}**:\n\n"
                      . "🔢 **الكود: {$number['verification_code']}**\n\n"
                      . "📝 استخدم هذا الكود في التطبيق\n"
                      . "⏰ الكود صالح لمدة 10 دقائق\n"
                      . "🔄 إذا لم يعمل، اطلب كود جديد";
                
                sendMessage($chatId, $text);
            } else {
                // إذا لم يكن هناك كود، اطلب من المسؤول إضافته
                $text = "📞 تم طلب رمز تفعيل للرقم:\n\n"
                      . "🔢 **{$number['number']}**\n"
                      . "📦 المنتج: {$number['name']}\n\n"
                      . "⏳ جاري الحصول على رمز التفعيل...\n"
                      . "📋 سيصلك الكود خلال دقائق\n\n"
                      . "⚠️ تأكد من:\n"
                      . "• استخدام الرقم الصحيح\n"
                      . "• انتظار طلب الرمز في التطبيق\n"
                      . "• طلب الكود مرة واحدة فقط";
                
                sendMessage($chatId, $text);
                
                // إشعار المسؤول
                notify_admin_for_code_request($userId, $number['id'], $number['number']);
            }
        } else {
            $text = "❌ لا يوجد رقم مفعل لحسابك\n\n"
                  . "📞 يرجى شراء رقم أولاً من خلال:\n"
                  . "1. الضغط على 'شراء أرقام'\n"
                  . "2. اختيار الرقم المناسب\n"
                  . "3. إتمام عملية الدفع";
            
            sendMessage($chatId, $text);
        }
        $conn->close();
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Error in ask_for_verification_code: " . $e->getMessage() . "\n", FILE_APPEND);
        sendMessage($chatId, "❌ حدث خطأ في طلب رمز التفعيل");
    }
}

function show_payment_methods($chatId, $messageId) {
    $text = "💳 طرق الدفع المتاحة:\n\n"
          . "1. **Binance**\n"
          . "   - المحفظة: 933609958\n\n"
          . "2. **كريمي جوال**\n"
          . "   - الحساب: 3009999646\n"
          . "   - الحساب: 3019896772\n\n"
          . "3. **محفظة جيب**\n"
          . "   - الحساب: 782551\n\n"
          . "⚠️ سيتم التحقق من الدفع يدوياً\n"
          . "⏳ مدة التوصيل: 24 ساعة";
    
    $keyboard = [[['text' => '🔙 رجوع', 'callback_data' => 'back_main']]];
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    editMessageText($chatId, $messageId, $text, $reply_markup);
}

function show_my_orders($chatId, $messageId, $userId) {
    try {
        $conn = new SQLite3('marketplace.db');
        $stmt = $conn->prepare('
            SELECT o.*, p.name 
            FROM orders o 
            JOIN products p ON o.product_id = p.id 
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
        ');
        $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $text = "📦 طلباتك السابقة:\n\n";
        $has_orders = false;
        
        while ($order = $result->fetchArray(SQLITE3_ASSOC)) {
            $has_orders = true;
            $status_icon = $order['status'] == 'paid' ? '✅' : '⏳';
            $text .= "{$status_icon} الطلب #{$order['id']}\n";
            $text .= "📋 المنتج: {$order['name']}\n";
            $text .= "💰 السعر: \${$order['total_price']}\n";
            $text .= "📊 الحالة: {$order['status']}\n";
            $text .= "📅 التاريخ: " . substr($order['created_at'], 0, 16) . "\n";
            $text .= "────────────────────\n";
        }
        
        if (!$has_orders) {
            $text = "📭 لا توجد طلبات سابقة";
        }
        
        $keyboard = [[['text' => '🔙 رجوع', 'callback_data' => 'back_main']]];
        $reply_markup = ['inline_keyboard' => $keyboard];
        
        editMessageText($chatId, $messageId, $text, $reply_markup);
        $conn->close();
    } catch (Exception $e) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] Error in show_my_orders: " . $e->getMessage() . "\n", FILE_APPEND);
        sendMessage($chatId, "❌ حدث خطأ في تحميل الطلبات");
    }
}

// دوال المسؤول المحسنة
function show_admin_panel($chatId, $messageId = null) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) {
        sendMessage($chatId, "❌ ليس لديك صلاحية الوصول إلى لوحة المسؤول");
        return;
    }
    
    $stats = get_admin_stats();
    
    $keyboard = [
        [['text' => '➕ إضافة أرقام', 'callback_data' => 'add_numbers']],
        [['text' => '➕ إضافة حسابات', 'callback_data' => 'add_accounts']],
        [['text' => '📊 الإحصائيات', 'callback_data' => 'view_stats']],
        [['text' => '📦 المنتجات', 'callback_data' => 'view_products']],
        [['text' => '📋 الطلبات', 'callback_data' => 'view_orders']],
        [['text' => '🔙 رجوع', 'callback_data' => 'back_main']]
    ];
    
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    $text = "👨‍💼 لوحة المسؤول\n\n"
          . "📈 الإحصائيات الحالية:\n"
          . "• الأرقام المتاحة: {$stats['available_numbers']}\n"
          . "• الحسابات المتاحة: {$stats['available_accounts']}\n"
          . "• الطلبات المعلقة: {$stats['pending_orders']}\n"
          . "• الطلبات المدفوعة: {$stats['paid_orders']}\n"
          . "• إجمالي الأرباح: \${$stats['total_revenue']}\n\n"
          . "اختر الإجراء الذي تريد تنفيذه:";
    
    if ($messageId) {
        editMessageText($chatId, $messageId, $text, $reply_markup);
    } else {
        sendMessage($chatId, $text, $reply_markup);
    }
}

function show_add_numbers_menu($chatId, $messageId) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("SELECT * FROM products WHERE type = 'number'");
    $result = $stmt->execute();
    
    $keyboard = [];
    while ($product = $result->fetchArray(SQLITE3_ASSOC)) {
        $available_count = get_available_numbers_count($product['id']);
        $keyboard[] = [[
            'text' => "{$product['name']} ({$available_count})",
            'callback_data' => "add_num_to_{$product['id']}"
        ]];
    }
    
    $keyboard[] = [['text' => '🔙 رجوع', 'callback_data' => 'back_admin']];
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    $text = "➕ إضافة أرقام جديدة\n\n"
          . "اختر نوع الرقم الذي تريد إضافة أرقام له:";
    
    editMessageText($chatId, $messageId, $text, $reply_markup);
    $conn->close();
}

function show_add_accounts_menu($chatId, $messageId) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("SELECT * FROM products WHERE type = 'account'");
    $result = $stmt->execute();
    
    $keyboard = [];
    while ($product = $result->fetchArray(SQLITE3_ASSOC)) {
        $available_count = get_available_accounts_count($product['id']);
        $keyboard[] = [[
            'text' => "{$product['name']} ({$available_count})",
            'callback_data' => "add_acc_to_{$product['id']}"
        ]];
    }
    
    $keyboard[] = [['text' => '🔙 رجوع', 'callback_data' => 'back_admin']];
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    $text = "➕ إضافة حسابات جديدة\n\n"
          . "اختر نوع الحساب الذي تريد إضافة حسابات له:";
    
    editMessageText($chatId, $messageId, $text, $reply_markup);
    $conn->close();
}

function ask_for_numbers_input($chatId, $messageId, $product_id) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $product = $result->fetchArray(SQLITE3_ASSOC);
    
    $text = "➕ إضافة أرقام لـ **{$product['name']}**\n\n"
          . "📝 أرسل الأرقام التي تريد إضافتها:\n"
          . "• رقم واحد في كل سطر\n"
          . "• يمكنك إضافة multiple أرقام\n\n"
          . "مثال:\n"
          . "+1234567890\n"
          . "+1987654321\n"
          . "+1122334455\n\n"
          . "استخدم الأمر: /add_numbers ثم الأرقام";
    
    editMessageText($chatId, $messageId, $text);
    
    // حفظ الجلسة
    $stmt = $conn->prepare("INSERT OR REPLACE INTO admin_sessions (chat_id, product_id, action) VALUES (?, ?, 'adding_numbers')");
    $stmt->bindValue(1, $chatId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $product_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    $conn->close();
}

function ask_for_accounts_input($chatId, $messageId, $product_id) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $product = $result->fetchArray(SQLITE3_ASSOC);
    
    $text = "➕ إضافة حسابات لـ **{$product['name']}**\n\n"
          . "📝 أرسل بيانات الحسابات التي تريد إضافتها:\n"
          . "• حساب واحد في كل سطر\n"
          . "• استخدم الصيغة: يوزر:كلمة السر\n\n"
          . "مثال:\n"
          . "john_doe:password123\n"
          . "jane_smith:pass456\n"
          . "user123:secret789\n\n"
          . "استخدم الأمر: /add_accounts ثم البيانات";
    
    editMessageText($chatId, $messageId, $text);
    
    // حفظ الجلسة
    $stmt = $conn->prepare("INSERT OR REPLACE INTO admin_sessions (chat_id, product_id, action) VALUES (?, ?, 'adding_accounts')");
    $stmt->bindValue(1, $chatId, SQLITE3_INTEGER);
    $stmt->bindValue(2, $product_id, SQLITE3_INTEGER);
    $stmt->execute();
    
    $conn->close();
}

function handle_admin_add_numbers($chatId, $text) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    
    // التحقق من الجلسة
    $stmt = $conn->prepare("SELECT * FROM admin_sessions WHERE chat_id = ? AND action = 'adding_numbers'");
    $stmt->bindValue(1, $chatId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $session = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($session) {
        $product_id = $session['product_id'];
        $numbers_text = str_replace('/add_numbers ', '', $text);
        $numbers = explode("\n", $numbers_text);
        
        $added_count = 0;
        $stmt = $conn->prepare("INSERT INTO available_numbers (number, product_id) VALUES (?, ?)");
        
        foreach ($numbers as $number) {
            $number = trim($number);
            if (!empty($number) && strlen($number) > 5) {
                $stmt->bindValue(1, $number, SQLITE3_TEXT);
                $stmt->bindValue(2, $product_id, SQLITE3_INTEGER);
                $stmt->execute();
                $added_count++;
            }
        }
        
        // تحديث المخزون
        $conn->exec("UPDATE products SET stock = stock + {$added_count} WHERE id = {$product_id}");
        
        // مسح الجلسة
        $conn->exec("DELETE FROM admin_sessions WHERE chat_id = {$chatId}");
        
        $product_stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
        $product_stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
        $product_result = $product_stmt->execute();
        $product = $product_result->fetchArray(SQLITE3_ASSOC);
        
        $text = "✅ تمت الإضافة بنجاح!\n\n"
              . "📞 تم إضافة {$added_count} رقم لـ **{$product['name']}**\n"
              . "🔄 المخزون تم تحديثه تلقائياً\n"
              . "📊 الإجمالي الآن: " . get_available_numbers_count($product_id) . " رقم";
        
        sendMessage($chatId, $text);
    } else {
        sendMessage($chatId, "❌ لا توجد جلسة نشطة. استخدم لوحة المسؤول أولاً.");
    }
    $conn->close();
}

function handle_admin_add_accounts($chatId, $text) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    
    // التحقق من الجلسة
    $stmt = $conn->prepare("SELECT * FROM admin_sessions WHERE chat_id = ? AND action = 'adding_accounts'");
    $stmt->bindValue(1, $chatId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $session = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($session) {
        $product_id = $session['product_id'];
        $accounts_text = str_replace('/add_accounts ', '', $text);
        $accounts = explode("\n", $accounts_text);
        
        $added_count = 0;
        $stmt = $conn->prepare("INSERT INTO available_accounts (account_data, product_id) VALUES (?, ?)");
        
        foreach ($accounts as $account) {
            $account = trim($account);
            if (!empty($account) && strpos($account, ':') !== false) {
                $stmt->bindValue(1, $account, SQLITE3_TEXT);
                $stmt->bindValue(2, $product_id, SQLITE3_INTEGER);
                $stmt->execute();
                $added_count++;
            }
        }
        
        // تحديث المخزون
        $conn->exec("UPDATE products SET stock = stock + {$added_count} WHERE id = {$product_id}");
        
        // مسح الجلسة
        $conn->exec("DELETE FROM admin_sessions WHERE chat_id = {$chatId}");
        
        $product_stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
        $product_stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
        $product_result = $product_stmt->execute();
        $product = $product_result->fetchArray(SQLITE3_ASSOC);
        
        $text = "✅ تمت الإضافة بنجاح!\n\n"
              . "👤 تم إضافة {$added_count} حساب لـ **{$product['name']}**\n"
              . "🔄 المخزون تم تحديثه تلقائياً\n"
              . "📊 الإجمالي الآن: " . get_available_accounts_count($product_id) . " حساب";
        
        sendMessage($chatId, $text);
    } else {
        sendMessage($chatId, "❌ لا توجد جلسة نشطة. استخدم لوحة المسؤول أولاً.");
    }
    $conn->close();
}

function handle_admin_add_code($chatId, $text) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    
    // استخراج الرقم والكود من النص
    $parts = explode(' ', $text);
    if (count($parts) >= 3) {
        $number_id = intval($parts[1]);
        $code = $parts[2];
        
        // تحديث الرقم بإضافة الكود
        $stmt = $conn->prepare("UPDATE available_numbers SET verification_code = ?, code_sent = 1 WHERE id = ?");
        $stmt->bindValue(1, $code, SQLITE3_TEXT);
        $stmt->bindValue(2, $number_id, SQLITE3_INTEGER);
        $stmt->execute();
        
        // الحصول على معلومات الرقم والمستخدم
        $stmt = $conn->prepare("SELECT number, sold_to FROM available_numbers WHERE id = ?");
        $stmt->bindValue(1, $number_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $number_info = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($number_info) {
            // إرسال الكود للمستخدم
            $user_text = "✅ تم استلام رمز التفعيل!\n\n"
                       . "🔢 الرقم: **{$number_info['number']}**\n"
                       . "📨 الكود: **{$code}**\n\n"
                       . "📝 استخدم هذا الكود في التطبيق الآن\n"
                       . "⏰ الكود صالح لمدة 10 دقائق\n"
                       . "🔄 إذا لم يعمل، اطلب كود جديد";
            
            sendMessage($number_info['sold_to'], $user_text);
            
            // تأكيد للمسؤول
            $admin_text = "✅ تم إرسال رمز التفعيل بنجاح!\n\n"
                        . "🔢 الرقم: {$number_info['number']}\n"
                        . "📨 الكود: {$code}\n"
                        . "👤 تم الإرسال للمستخدم: {$number_info['sold_to']}";
            
            sendMessage($ADMIN_ID, $admin_text);
        }
    } else {
        sendMessage($chatId, "❌ صيغة الأمر غير صحيحة\n\nاستخدم:\n`/add_code رقم_التسجيل الكود`\n\nمثال:\n`/add_code 123 456789`");
    }
    
    $conn->close();
}

function notify_admin($order_id, $file_id, $user_id) {
    global $ADMIN_ID, $BOT_TOKEN;
    
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("
        SELECT o.*, p.name, p.type 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        WHERE o.id = ?
    ");
    $stmt->bindValue(1, $order_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $order = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($order) {
        $text = "🆕 طلب جديد مدفوع!\n\n"
              . "🆔 رقم الطلب: #{$order_id}\n"
              . "👤 أي دي العميل: {$user_id}\n"
              . "📦 المنتج: {$order['name']}\n"
              . "💰 المبلغ: \${$order['total_price']}\n"
              . "⏰ الوقت: " . date('Y-m-d H:i:s');
        
        sendMessage($ADMIN_ID, $text);
        
        // إرسال لقطة الشاشة للمسؤول
        telegramAPI('sendPhoto', [
            'chat_id' => $ADMIN_ID,
            'photo' => $file_id,
            'caption' => "📸 إثبات الدفع للطلب #{$order_id}"
        ]);
    }
    $conn->close();
}

function notify_admin_for_code_request($user_id, $number_id, $number) {
    global $ADMIN_ID;
    
    $text = "📨 طلب جديد لرمز التفعيل!\n\n"
          . "👤 أي دي العميل: {$user_id}\n"
          . "🔢 الرقم: **{$number}**\n"
          . "🆔 رقم التسجيل: {$number_id}\n"
          . "⏰ الوقت: " . date('Y-m-d H:i:s') . "\n\n"
          . "📝 لإضافة رمز التفعيل، استخدم الأمر:\n"
          . "`/add_code {$number_id} الرمز`\n\n"
          . "مثال:\n"
          . "`/add_code {$number_id} 123456`";
    
    sendMessage($ADMIN_ID, $text);
}

function show_admin_stats($chatId, $messageId) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $stats = get_admin_stats();
    
    $text = "📈 إحصائيات البوت التفصيلية\n\n"
          . "🛒 إحصائيات الطلبات:\n"
          . "• إجمالي الطلبات: {$stats['total_orders']}\n"
          . "• الطلبات المعلقة: {$stats['pending_orders']}\n"
          . "• الطلبات المدفوعة: {$stats['paid_orders']}\n"
          . "• الطلبات المكتملة: {$stats['completed_orders']}\n\n"
          . "💰 الإيرادات:\n"
          . "• إجمالي الأرباح: \${$stats['total_revenue']}\n"
          . "• أرباح اليوم: \${$stats['today_revenue']}\n\n"
          . "📦 المخزون:\n"
          . "• الأرقام المتاحة: {$stats['available_numbers']}\n"
          . "• الحسابات المتاحة: {$stats['available_accounts']}\n\n"
          . "⏰ آخر تحديث: " . date('Y-m-d H:i:s');
    
    $keyboard = [[['text' => '🔙 رجوع', 'callback_data' => 'back_admin']]];
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    if ($messageId) {
        editMessageText($chatId, $messageId, $text, $reply_markup);
    } else {
        sendMessage($chatId, $text, $reply_markup);
    }
}

function show_admin_products($chatId, $messageId) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("SELECT * FROM products");
    $result = $stmt->execute();
    
    $text = "📦 جميع المنتجات:\n\n";
    
    while ($product = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($product['type'] == 'number') {
            $available_count = get_available_numbers_count($product['id']);
        } else {
            $available_count = get_available_accounts_count($product['id']);
        }
        
        $text .= "🆔 {$product['id']} - {$product['name']}\n";
        $text .= "💰 السعر: \${$product['price']}\n";
        $text .= "📦 المخزون: {$available_count}\n";
        $text .= "🌍 الدولة: {$product['country']}\n";
        $text .= "────────────────────\n";
    }
    
    $keyboard = [[['text' => '🔙 رجوع', 'callback_data' => 'back_admin']]];
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    editMessageText($chatId, $messageId, $text, $reply_markup);
    $conn->close();
}

function show_admin_orders($chatId, $messageId) {
    global $ADMIN_ID;
    
    if ($chatId != $ADMIN_ID) return;
    
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("
        SELECT o.*, p.name as product_name 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    $result = $stmt->execute();
    
    $text = "📦 آخر 10 طلبات:\n\n";
    
    while ($order = $result->fetchArray(SQLITE3_ASSOC)) {
        $status_icon = $order['status'] == 'paid' ? '✅' : '⏳';
        $text .= "{$status_icon} الطلب #{$order['id']}\n";
        $text .= "👤 العميل: {$order['user_id']}\n";
        $text .= "📦 المنتج: {$order['product_name']}\n";
        $text .= "💰 المبلغ: \${$order['total_price']}\n";
        $text .= "📊 الحالة: {$order['status']}\n";
        $text .= "⏰ الوقت: " . substr($order['created_at'], 0, 16) . "\n";
        $text .= "────────────────────\n";
    }
    
    $keyboard = [[['text' => '🔙 رجوع', 'callback_data' => 'back_admin']]];
    $reply_markup = ['inline_keyboard' => $keyboard];
    
    editMessageText($chatId, $messageId, $text, $reply_markup);
    $conn->close();
}

// دوال مساعدة
function get_available_numbers_count($product_id) {
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM available_numbers WHERE product_id = ? AND sold = 0");
    $stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $conn->close();
    return $row['count'];
}

function get_available_accounts_count($product_id) {
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM available_accounts WHERE product_id = ? AND sold = 0");
    $stmt->bindValue(1, $product_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $conn->close();
    return $row['count'];
}

function get_admin_stats() {
    $conn = new SQLite3('marketplace.db');
    
    $stats = [];
    
    // إحصائيات الطلبات
    $result = $conn->query("SELECT COUNT(*) as count FROM orders");
    $stats['total_orders'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'paid'");
    $stats['paid_orders'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'completed'");
    $stats['completed_orders'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // الإيرادات
    $result = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status IN ('paid', 'completed')");
    $stats['total_revenue'] = number_format($result->fetchArray(SQLITE3_ASSOC)['total'] ?? 0, 2);
    
    $result = $conn->query("SELECT SUM(total_price) as total FROM orders WHERE status IN ('paid', 'completed') AND date(created_at) = date('now')");
    $stats['today_revenue'] = number_format($result->fetchArray(SQLITE3_ASSOC)['total'] ?? 0, 2);
    
    // المخزون
    $result = $conn->query("SELECT COUNT(*) as count FROM available_numbers WHERE sold = 0");
    $stats['available_numbers'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM available_accounts WHERE sold = 0");
    $stats['available_accounts'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $conn->close();
    
    return $stats;
}

function handle_regular_message($chatId, $userId, $text) {
    // لا ترسل رسالة افتراضية إذا كان المستخدم ينتظر لقطة شاشة
    $conn = new SQLite3('marketplace.db');
    $stmt = $conn->prepare("SELECT waiting_for_screenshot FROM user_sessions WHERE user_id = ?");
    $stmt->bindValue(1, $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $session = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($session && $session['waiting_for_screenshot'] == 1) {
        // إذا كان ينتظر لقطة شاشة، اطلب منه إرسال الصورة
        sendMessage($chatId, "📸 يرجى إرسال screenshot لإشعار الدفع بدلاً من الرسالة النصية");
    } else {
        sendMessage($chatId, "استخدم الأزرار أدناه للتنقل بين خيارات البوت 🎯");
    }
    $conn->close();
}

// دوال مساعدة للتواصل مع تليجرام API
function sendMessage($chatId, $text, $reply_markup = null) {
    global $BOT_TOKEN;
    
    $parameters = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $parameters['reply_markup'] = json_encode($reply_markup);
    }
    
    $result = telegramAPI('sendMessage', $parameters);
    
    if (!$result || !$result['ok']) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] SendMessage Error: " . json_encode($result) . "\n", FILE_APPEND);
    }
    
    return $result;
}

function editMessageText($chatId, $messageId, $text, $reply_markup = null) {
    global $BOT_TOKEN;
    
    $parameters = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $parameters['reply_markup'] = json_encode($reply_markup);
    }
    
    $result = telegramAPI('editMessageText', $parameters);
    
    if (!$result || !$result['ok']) {
        file_put_contents('bot_errors.log', "[" . date('Y-m-d H:i:s') . "] EditMessage Error: " . json_encode($result) . "\n", FILE_APPEND);
        // إذا فشل التعديل، أرسل رسالة جديدة
        sendMessage($chatId, $text, $reply_markup);
    }
    
    return $result;
}

// الرد للتحقق من أن الويب هوك يعمل
echo "OK";
?>