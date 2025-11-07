from flask import Flask, request, jsonify
import json
import logging

# تهيئة Flask
app = Flask(__name__)

# إعدادات البوت
BOT_TOKEN = '8526176589:AAF-Tj1tXNVv-7FHpukPx7rBOmMmpB5H73Q'
ADMIN_ID = 7700286311

# إعداد التسجيل
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

@app.route('/webhook', methods=['POST'])
def webhook():
    """استقبال Webhook من التلجرام"""
    try:
        # الحصول على البيانات
        update = request.get_json()
        
        if not update:
            logger.warning("⚠️ لا توجد بيانات مستلمة")
            return jsonify({'status': 'no data'})
        
        # معالجة التحديث
        process_update(update)
        
        return jsonify({'status': 'success'})
    
    except Exception as e:
        logger.error(f"❌ خطأ في معالجة Webhook: {e}")
        return jsonify({'status': 'error'})

def process_update(update):
    """معالجة البيانات الواردة من التلجرام"""
    
    # التحقق من وجود رسالة
    if 'message' in update:
        message = update['message']
        chat_id = message['chat']['id']
        text = message.get('text', '')
        
        logger.info(f"📨 رسالة من {chat_id}: {text}")
        
        # معالجة الأوامر
        if text == '/start':
            send_message(chat_id, 'مرحباً! 👋 أنا بوت بيع الحسابات وأرقام التفعيل.')
            show_main_menu(chat_id)
        
        elif text == '🛒 شراء حسابات':
            show_accounts_menu(chat_id)
        
        elif text == '📱 أرقام التفعيل':
            show_numbers_menu(chat_id)
        
        else:
            send_message(chat_id, 'لم أفهم رسالتك. استخدم الأوامر من القائمة.')

def send_message(chat_id, text, reply_markup=None):
    """إرسال رسالة إلى المستخدم"""
    url = f'https://api.telegram.org/bot{BOT_TOKEN}/sendMessage'
    
    data = {
        'chat_id': chat_id,
        'text': text,
        'parse_mode': 'HTML'
    }
    
    if reply_markup:
        data['reply_markup'] = json.dumps(reply_markup)
    
    try:
        import requests
        response = requests.post(url, data=data)
        return response.json()
    except Exception as e:
        logger.error(f"❌ خطأ في إرسال الرسالة: {e}")

def show_main_menu(chat_id):
    """عرض القائمة الرئيسية"""
    keyboard = {
        'keyboard': [
            ['🛒 شراء حسابات', '📱 أرقام التفعيل'],
            ['📞 تواصل معنا', 'ℹ️ معلومات عنا']
        ],
        'resize_keyboard': True
    }
    
    send_message(chat_id, 'اختر من القائمة:', keyboard)

def show_accounts_menu(chat_id):
    """عرض قائمة الحسابات"""
    text = """
🛒 *قائمة الحسابات المتاحة:*

🔹 *فيسبوك*
• حساب عادي - 5$
• حساب بيع - 10$

🔹 *انستجرام*
• حساب عادي - 8$
• حساب متابعين - 12$

اختر نوع الحساب الذي تريد شراءه.
    """
    
    send_message(chat_id, text)

def show_numbers_menu(chat_id):
    """عرض قائمة أرقام التفعيل"""
    text = """
📱 *أرقام التفعيل المتاحة:*

📞 *واتساب*
• رقم عادي - 3$
• رقم مميز - 5$

📞 *فيسبوك*
• رقم عادي - 2$
• رقم مميز - 4$

اختر نوع الرقم الذي تريده.
    """
    
    send_message(chat_id, text)

@app.route('/')
def index():
    """الصفحة الرئيسية"""
    return "🤖 البوت يعمل بنجاح!"

@app.route('/set_webhook', methods=['GET'])
def set_webhook_route():
    """تعيين Webhook عبر المتصفح"""
    webhook_url = 'https://yourdomain.com/webhook'  # استبدل برابطك
    
    url = f'https://api.telegram.org/bot{BOT_TOKEN}/setWebhook'
    data = {'url': webhook_url}
    
    try:
        import requests
        response = requests.post(url, data=data)
        result = response.json()
        
        return f"""
        <h1>نتيجة تعيين Webhook</h1>
        <pre>{json.dumps(result, indent=2, ensure_ascii=False)}</pre>
        """
    
    except Exception as e:
        return f"❌ خطأ: {e}"

if __name__ == '__main__':
    # تشغيل السيرفر
    app.run(host='0.0.0.0', port=5000, debug=True)