# -*- coding: utf-8 -*-
"""
====================================================================
⛪ አጸደ ትጉሃን ሰንበት ትምህርት ቤት - ቴሌግራም ረዳት AI ቦት (Telegram Helper Bot)
====================================================================
ይህ ቦት ስለ ፈተና እና ውጤት መረጃ ሲስተሙ የሚጠየቁ ማናቸውንም ጥያቄዎች 
ለመምህራን፣ ለተማሪዎች፣ ለወላጆች እና ለአስተዳዳሪዎች በቅጽበት በአማርኛ ይመልሳል።
እንዲሁም አዲሱን የቴሌግራፍ መመሪያ ሊንክ እና አዝራሮችን ያካተተ ነው።

አጠቃቀም፦
1. ቴሌግራም ላይ @BotFather ጋር በመሄድ አዲስ ቦት ይፍጠሩ እና BOT_TOKEN ይቀበሉ።
2. ከስር ባለው `BOT_TOKEN = "..."` ቦታ ላይ ቶከንዎን ያስገቡ።
3. ስክሪፕቱን በ `python telegram_bot.py` ያስጀምሩት!
====================================================================
"""

import sys
import time
import json
import urllib.request
import urllib.parse

sys.stdout.reconfigure(encoding='utf-8')

# -------------------------------------------------------------
# ⚙️ የቦቱ ቅንብሮች (Configuration)
# -------------------------------------------------------------
# @BotFather የሚሰጥዎትን የቦት ቶከን እዚህ ያስገቡ፦
BOT_TOKEN = "YOUR_TELEGRAM_BOT_TOKEN_HERE"

# የተዘጋጀው የቀጥታ የቴሌግራፍ መመሪያ አድራሻ፦
TELEGRAPH_GUIDE_URL = "https://telegra.ph/የአጸደ-ትጉሃን-ሰንበት-ትቤት-ሲስተም-የተጠቃሚ-መመሪያ-የተሟላ-ማስተር-ዕትም-11-03-2"
CHURCH_PORTAL_URL = "https://atsede-result.onrender.com"

BASE_TELEGRAM_URL = f"https://api.telegram.org/bot{BOT_TOKEN}"

# -------------------------------------------------------------
# 🧠 የስርዓቱ የእውቀት ማዕከል (Knowledge Base & AI Answers)
# -------------------------------------------------------------
KNOWLEDGE_BASE = [
    {
        "keywords": ["ከበደኝ", "ከባድ", "slow", "heavy", "ፈጣን", "fast", "speed", "ዘገየ"],
        "answer": (
            "⚡ <b>ሲስተሙ እንዳይከብድዎትና እጅግ ፈጣን እንዲሆን፦</b>\n\n"
            "1. <b>አፑን ወደ ስልክዎ ይጫኑ፦</b> በብሮውዘር በየጊዜው ከመፈለግ አፑን ወደ ስልክዎ በመጫን በቀጥታ ከመነሻ ገጽ (Home Screen) ላይ በአንድ ንክኪ ይክፈቱት። ፋይሎቹ ስልክዎ ላይ ስለሚቀመጡ ለመክፈት ዳታ አይፈጅም!\n"
            "2. <b>'አስታውሰኝ (Remember Me)' ይጠቀሙ፦</b> ለ 30 ቀናት በስልክዎ ላይ ክፍት ሆኖ ይቆያል፤ በየቀኑ የተጠቃሚ ስምና የይለፍ ቃል መጻፍ አይጠበቅብዎትም።\n"
            "3. <b>ሙሉ በሙሉ Offline ይሰራል፦</b> ኔትወርክ ባይኖርም እንኳን ክትትል መውሰድ፣ ፈተና ማዘጋጀት እና ውጤት መሙላት ይችላሉ። ስልክዎ ኢንተርኔት ሲያገኝ በራሱ ይሰቅላል።\n"
            "4. <b>ስራዎን ሲጨርሱ 'Logout' አያድርጉ፦</b> ዝም ብለው አፑን ይዝጉት፤ Logout ካደረጉ ያለ ኢንተርኔት መልሶ መግባት አይቻልም።"
        )
    },
    {
        "keywords": ["ፈተና ዝግጅት", "ጥያቄ", "ፈተና ማዘጋጀት", "exam", "author", "ጥያቄዎች", "ምርጫ", "አዛምድ"],
        "answer": (
            "📝 <b>አዲሱ የመምህራን የፈተና ዝግጅት ማዕከል (Exam Authoring)፦</b>\n\n"
            "መምህራን ፈተናዎችን በቀላሉ በስልካቸው ወይም በኮምፒዩተር እንዲያዘጋጁ እና ለትምህርት ክፍል እንዲልኩ የተዘጋጀ ነው፦\n\n"
            "• <b>ብልህ ክፍልና ኮርስ፦</b> የተመደቡበት 1 ክፍል ብቻ ከሆነ በራሱ ይመረጣል (ተቆልቋይ አያስመርጥዎትም)፤ የትምህርት ዓይነትዎም በራሱ ይሞላል።\n"
            "• <b>5 የጥያቄ ዓይነቶች፦</b> ምርጫ፣ እውነት/ሐሰት፣ አዛምድ፣ ክፍት ጥያቄ እና ባዶ ቦታ ሙላ በአንድ ጠቅታ ማከል ይችላሉ።\n"
            "• <b>የቀጥታ ነጥብ መቆጣጠሪያ፦</b> ጠቅላላ ነጥቡ 100% መድረሱን በቀለም ያሳውቅዎታል።\n"
            "• <b>ራስ-ሰር ይቀመጣል (Auto-Save)፦</b> በስልክዎ ላይ በየሰኮንዱ በራሱ ይቀመጣል፤ ባትሪ ቢያልቅ እንኳን የጻፉት አይጠፋም።\n"
            "• <b>መላክ፦</b> ሲጨርሱ «🚀 ፈተናውን ለትምህርት ክፍል ላክ» የሚለውን ይጫኑ!"
        )
    },
    {
        "keywords": ["ፈተና ማተም", "ህትመት", "print", "አስተዳዳሪ", "ማጽደቅ", "approve"],
        "answer": (
            "🖨️ <b>የፈተናዎች ግምገማ፣ ማጽደቂያና ማተሚያ (Admin Portal)፦</b>\n\n"
            "የትምህርት ክፍል አስተዳዳሪዎች፦\n"
            "• መምህራን የላኳቸውን ፈተናዎች ገምግመው ማጽደቅ (Approve) ወይም ማስተካከያ (Feedback) መስጠት ይችላሉ።\n"
            "• የጸደቀውን ፈተና በሰንበት ትምህርት ቤቱ ይፋዊ አርማ እና ራስጌ በ A4 ወረቀት ለተማሪዎች ማተም ይችላሉ።\n"
            "• «🔑 የመልስ ቁልፍ አሳይ» የሚለውን በመጫን ለአራሚዎች የሚሆን የመልስ ወረቀት ማተም ይቻላል።"
        )
    },
    {
        "keywords": ["ዳርክ ሞድ", "dark", "ጨለማ ገጽታ", "night", "ማታ"],
        "answer": (
            "🌙 <b>የጨለማ ገጽታ (Dark Mode)፦</b>\n\n"
            "በምሽት ዓይን እንዳይዝል ሲስተሙ በኢትዮጵያ ሰዓት ከምሽቱ 12:00 ጀምሮ በራሱ ወደ Dark Mode ይቀየራል፤ ወይም ከላይ ባለው ቁልፍ እንደ ፍላጎትዎ ማብራትና ማጥፋት ይችላሉ።"
        )
    },
    {
        "keywords": ["ኦፍላይን", "ኢንተርኔት", "offline", "ያለ ኢንተርኔት", "ዳታ", "ዋይፋይ"],
        "answer": (
            "📡 <b>ያለ ኢንተርኔት (Offline) አጠቃቀም፦</b>\n\n"
            "አዎ! ሲስተሙ ሙሉ በሙሉ ያለ ኢንተርኔት (Offline) እንዲሰራ ሆኖ የተዘጋጀ ነው።\n\n"
            "• ኢንተርኔት በማይኖርበት ጊዜ ከላይ ቀይ <b>«📡 Offline»</b> ባነር ይታያል።\n"
            "• መምህራን ክትትል ቢወስዱ ወይም ውጤት ቢሞሉ መረጃው በስልካቸው IndexedDB ይቀመጣል።\n"
            "• ስልክዎ ኢንተርኔት ሲያገኝ በራስ-ሰር ወደ ዋናው ዳታቤዝ ይሰቀላል (Auto Sync)።\n\n"
            "⚠️ <b>ማሳሰቢያ፦</b> ኦፍላይን ለመጠቀም ስራዎን ሲጨርሱ 'Logout' እንዳያደርጉ፤ ዝም ብለው አፑን መዝጋት ብቻ በቂ ነው!"
        )
    },
    {
        "keywords": ["ጫን", "መጫን", "install", "አፕ", "app", "ስልክ", "ዳውንሎድ", "download"],
        "answer": (
            "📱 <b>አፑን ወደ ስልክዎ ለመጫን (PWA Installation)፦</b>\n\n"
            "• <b>በ Android (Chrome)፦</b> ሲስተሙን ሲከፍቱ ከስር «📱 አፕሊኬሽን ወደ ስልክዎ ይጫኑ! ⬇️ ጫን» የሚለውን ይጫኑ ወይም ከላይ 3 ነጥቦችን (⋮) ነክተው 'Install App' ይምረጡ።\n"
            "• <b>በ iPhone / iPad (Safari)፦</b> ከስር የ Share ምልክት (⎋) ይጫኑ፤ ከዚያም 'Add to Home Screen' የሚለውን ይምረጡ።\n\n"
            "አንዴ ከተጫነ በኋላ ያለ ኢንተርኔት ከስልክዎ መነሻ ገጽ ላይ በቀጥታ ይሰራል!"
        )
    },
    {
        "keywords": ["ተማሪ", "ውጤት", "ፈተና", "student", "pin", "ሚስጥር ቁጥር", "ወላጅ"],
        "answer": (
            "🎓 <b>የተማሪዎች እና የወላጆች ውጤት መመልከቻ፦</b>\n\n"
            "1. በመግቢያው ገጽ ስር <b>«🎓 የተማሪ መግቢያ / Student Portal»</b> የሚለውን ይጫኑ።\n"
            "2. የተማሪውን ስም ፈልገው ይምረጡ።\n"
            "3. በትምህርት ክፍሉ የተሰጠውን ባለ 4-አሃዝ <b>ሚስጥር ቁጥር (PIN)</b> ያስገቡ።\n"
            "4. የሴሚስተሩን ሙሉ ውጤት፣ አማካይ፣ ደረጃ እና የሰንበት ት/ቤት መገኘት (የአቴንዳንስ ካላንደር) ማየት ይችላሉ።"
        )
    },
    {
        "keywords": ["መምህር", "መምህራን", "teacher", "ክትትል", "አቴንዳንስ", "attendance"],
        "answer": (
            "👨‍🏫 <b>የመምህራን የክትትል መመዝገቢያ መመሪያ፦</b>\n\n"
            "1. በተጠቃሚ ስምዎ እና በይለፍ ቃልዎ ይግቡ።\n"
            "2. ዳሽቦርድ ላይ <b>«📅 ክትትል መመዝገቢያ»</b> የሚለውን ይምረጡ።\n"
            "3. የሚያስተምሩትን ክፍል መርጠው፦\n"
            "   • ✅ ተገኝቷል (P)\n"
            "   • ❌ አልተገኘም (A)\n"
            "   • 📝 በፈቃድ (Per)\n"
            "   • ⏰ አርፍዷል (L)\n"
            "4. ከስር 'ክትትል መዝግብ' የሚለውን ይጫኑ።"
        )
    },
    {
        "keywords": ["ይለፍ ቃል", "password", "ረሳሁ", "መቀየር", "login", "መግቢያ"],
        "answer": (
            "🔒 <b>የይለፍ ቃል እና መግቢያ መረጃ፦</b>\n\n"
            "• መምህራን ለመጀመሪያ ጊዜ ሲገቡ ሲስተሙ የይለፍ ቃልዎን እንዲቀይሩ ይጠይቅዎታል።\n"
            "• የይለፍ ቃልዎን ከረሱ የትምህርት ክፍሉን አስተዳዳሪ በማነጋገር ማስቀየር (Reset ማድረግ) ይችላሉ።\n"
            "• የተማሪ ሚስጥር ቁጥር (PIN) ከጠፋ አስተዳዳሪው ከተማሪዎች ዝርዝር ውስጥ ዳግም ሊሰጥዎት ይችላል።"
        )
    },
    {
        "keywords": ["እቅድ", "የትምህርት እቅድ", "lesson", "plan"],
        "answer": (
            "📚 <b>የትምህርት እቅድ (Lesson Plan Editor)፦</b>\n\n"
            "መምህራን በዳሽቦርዳቸው ላይ 'የትምህርት እቅድ ማዘጋጃ' የሚለውን በመጫን የትምህርቱን ርዕስ፣ አላማ እና ማጠቃለያ ሞልተው ለትምህርት ክፍሉ በሲስተሙ በኩል ማቅረብ ይችላሉ።"
        )
    },
    {
        "keywords": ["ካላንደር", "በዓላት", "calendar", "ቀን መቁጠሪያ", "አጽዋማት"],
        "answer": (
            "⛪ <b>የቤተክርስቲያን የቀን መቁጠሪያ እና በዓላት፦</b>\n\n"
            "በሲስተሙ ውስጥ የኢትዮጵያ ኦርቶዶክስ ተዋሕዶ የቀን መቁጠሪያ፣ የአጽዋማት እና የበዓላት ቀናት እንዲሁም የሰንበት ትምህርት ቤቱ መርሃ ግብሮች ተካተው ይገኛሉ። ያለ ኢንተርኔትም ጭምር ይታያል!"
        )
    }
]

def find_ai_answer(user_text):
    text = user_text.lower().strip()
    
    # Match against knowledge base
    for item in KNOWLEDGE_BASE:
        for kw in item["keywords"]:
            if kw in text:
                return item["answer"]
                
    # Default fallback answer
    return (
        "⛪ <b>ሰላም እንደምን አደሩ / ዋሉ!</b>\n\n"
        "ስለ ሲስተሙ የሚፈልጉትን ጥያቄ በአጭሩ ሊጠይቁኝ ይችላሉ፦\n"
        "• <i>«አፑን በስልኬ እንዴት ልጫን?»</i>\n"
        "• <i>«ያለ ኢንተርኔት (Offline) ይሰራል ወይ?»</i>\n"
        "• <i>«የተማሪ ውጤት እንዴት ይታያል?»</i>\n"
        "• <i>«ክትትል እንዴት ይመዘገባል?»</i>\n\n"
        f"📖 ሙሉውን የተጠቃሚ መመሪያ በቴሌግራፍ ለማንበብ፦ <a href='{TELEGRAPH_GUIDE_URL}'>እዚህ ይጫኑ</a>\n\n"
        f"⛪ <b>አጸደ ትጉሃን ሰንበት ትምህርት ቤት የትምህርት ክፍል</b>\n"
        f"🌐 <a href='{CHURCH_PORTAL_URL}'>የሲስተሙ የቀጥታ ፖርታል ይክፈቱ</a>"
    )

def send_message(chat_id, text, reply_markup=None):
    payload = {
        "chat_id": chat_id,
        "text": text,
        "parse_mode": "HTML",
        "disable_web_page_preview": False
    }
    if reply_markup:
        payload["reply_markup"] = reply_markup

    data = json.dumps(payload).encode('utf-8')
    req = urllib.request.Request(
        f"{BASE_TELEGRAM_URL}/sendMessage",
        data=data,
        headers={"Content-Type": "application/json"}
    )
    try:
        with urllib.request.urlopen(req) as resp:
            return json.loads(resp.read().decode('utf-8'))
    except Exception as e:
        print(f"Error sending message: {e}")
        return None

def get_updates(offset=None):
    url = f"{BASE_TELEGRAM_URL}/getUpdates?timeout=20"
    if offset:
        url += f"&offset={offset}"
    try:
        with urllib.request.urlopen(url, timeout=25) as resp:
            return json.loads(resp.read().decode('utf-8'))
    except Exception as e:
        return None

def main():
    if BOT_TOKEN == "YOUR_TELEGRAM_BOT_TOKEN_HERE":
        print("="*60)
        print("⚠️ ማሳሰቢያ፦ እባክዎ BOT_TOKEN ያስገቡ!")
        print("1. በቴሌግራም @BotFather ጋር በመሄድ አዲስ ቦት ይፍጠሩ።")
        print("2. የሚሰጥዎትን ቶከን በ telegram_bot.py ውስጥ BOT_TOKEN = '...' ላይ ያስቀምጡ።")
        print("="*60)
        return

    print("🚀 የአጸደ ትጉሃን ቴሌግራም ረዳት ቦት ስራ ጀምሯል...")
    offset = None

    main_keyboard = {
        "inline_keyboard": [
            [
                {"text": "📖 ሙሉ የቴሌግራፍ መመሪያ ክፈት", "url": TELEGRAPH_GUIDE_URL}
            ],
            [
                {"text": "📱 አፑን ወደ ስልክ መጫን", "callback_data": "faq_install"},
                {"text": "📡 ያለ ኢንተርኔት (Offline)", "callback_data": "faq_offline"}
            ],
            [
                {"text": "👨‍🏫 የመምህራን መመሪያ", "callback_data": "faq_teacher"},
                {"text": "🎓 የተማሪዎች መመሪያ", "callback_data": "faq_student"}
            ],
            [
                {"text": "⛪ የትምህርት ክፍል ፖርታል ክፈት", "url": CHURCH_PORTAL_URL}
            ]
        ]
    }

    while True:
        updates = get_updates(offset)
        if updates and updates.get("ok"):
            for result in updates.get("result", []):
                offset = result["update_id"] + 1

                # Handle normal text messages
                if "message" in result:
                    msg = result["message"]
                    chat_id = msg["chat"]["id"]
                    text = msg.get("text", "")

                    if text.startswith("/start"):
                        welcome_text = (
                            "⛪ <b>እንኳን ወደ አጸደ ትጉሃን ሰንበት ት/ቤት ረዳት AI ቦት በሰላም መጡ!</b>\n\n"
                            "ይህ ቦት ስለ ሲስተሙ አጠቃቀም፣ ስለ ፈተና እና ውጤት መመዝገቢያ፣ "
                            "ስለ ክትትል (Attendance) እና ያለ ኢንተርኔት (Offline) አሰራር የሚጠይቁትን ማንኛውንም ጥያቄ ይመልስልዎታል።\n\n"
                            "ከታች ያሉትን አዝራሮች በመጫን ወይም በቀጥታ ጥያቄዎን በመጻፍ ይጠይቁኝ!"
                        )
                        send_message(chat_id, welcome_text, reply_markup=main_keyboard)
                    else:
                        reply = find_ai_answer(text)
                        send_message(chat_id, reply, reply_markup=main_keyboard)

                # Handle inline button callbacks
                elif "callback_query" in result:
                    cb = result["callback_query"]
                    chat_id = cb["message"]["chat"]["id"]
                    data = cb.get("data", "")

                    if data == "faq_install":
                        ans = find_ai_answer("አፕ ጫን")
                    elif data == "faq_offline":
                        ans = find_ai_answer("ኦፍላይን")
                    elif data == "faq_teacher":
                        ans = find_ai_answer("መምህራን ክትትል")
                    elif data == "faq_student":
                        ans = find_ai_answer("ተማሪ ውጤት")
                    else:
                        ans = "መረጃው አልተገኘም።"

                    send_message(chat_id, ans, reply_markup=main_keyboard)

        time.sleep(1)

if __name__ == "__main__":
    main()
