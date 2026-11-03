# -*- coding: utf-8 -*-
"""
publish_telegraph.py - Generates and publishes the comprehensive, modern 
Atsede Teguhan Sunday School System Guide directly to Telegraph (telegra.ph).
"""
import sys
import json
import urllib.request
import urllib.parse

sys.stdout.reconfigure(encoding='utf-8')

# 1. Create Telegraph Account
account_url = "https://api.telegra.ph/createAccount?short_name=Atsede&author_name=" + urllib.parse.quote("አጸደ ትጉሃን ሰንበት ት/ቤት")
req = urllib.request.Request(account_url, headers={'User-Agent': 'Mozilla/5.0'})
with urllib.request.urlopen(req) as resp:
    acc_data = json.loads(resp.read().decode('utf-8'))

if not acc_data.get('ok'):
    print("Account creation failed:", acc_data)
    sys.exit(1)

token = acc_data['result']['access_token']
print(f"Telegraph Access Token obtained: {token[:10]}...")

# 2. Build the Modern Guide Nodes
nodes = [
    {
        "tag": "blockquote",
        "children": [
            "⛪ እንኳን ወደ አጸደ ትጉሃን ሰንበት ትምህርት ቤት የፈተና ዝግጅት፣ የውጤት መመዝገቢያ እና የተማሪዎች መረጃ ሲስተም በሰላም መጡ! ይህ መመሪያ ለመምህራን፣ ለተማሪዎች፣ ለወላጆች እና ለአስተዳዳሪዎች የተዘጋጀ ዘመናዊ የተሟላ ማብራሪያ ነው።"
        ]
    },

    # SPEED & LIGHT-WEIGHT SECTION
    {
        "tag": "h3",
        "children": ["⚡ ሲስተሙ እንዳይከብድዎትና እጅግ ፈጣን እንዲሆን የሚረዱ 4 ቁልፍ ምክሮች"]
    },
    {
        "tag": "p",
        "children": [
            "አንዳንድ መምህራን ወይም ተጠቃሚዎች «ሲስተሙ በስልኬ ላይ ከበደኝ/ዘገየብኝ» የሚሉበት ምክንያት አፑን በስልካቸው ላይ ስላልጫኑት ወይም በየጊዜው ብሮውዘር ላይ ፈልገው ስለሚከፍቱት ነው። ሲስተሙ ልክ እንደ WhatsApp በቅጽበት እንዲከፍት እና ምንም ዳታ እንዳይፈጅ የሚከተሉትን 4 ነገሮች ይተግብሩ፦"
        ]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["1. አፑን ወደ ስልክዎ ይጫኑ (Install App)፦ "]},
                    "በብሮውዘር በየጊዜው ከመፈለግ አፑን ወደ ስልክዎ በመጫን በቀጥታ ከመነሻ ገጽ (Home Screen) ላይ በአንድ ንክኪ ይክፈቱት። አንዴ ከተጫነ በኋላ ፋይሎቹ በሙሉ በስልክዎ ላይ ስለሚቀመጡ ለመክፈት ምንም ኢንተርኔት አይጠብቅም!"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["2. 'አስታውሰኝ (Remember Me)' የሚለውን ይጠቀሙ፦ "]},
                    "ሲገቡ 'የይለፍ ቃል አስታውስ' የሚለውን ምልክት ካደረጉ ለ 30 ቀናት በስልክዎ ላይ ዝግጁ ሆኖ ይቆያል፤ በየቀኑ የተጠቃሚ ስምና የይለፍ ቃል መጻፍ አይጠበቅብዎትም።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["3. ሙሉ በሙሉ ያለ ኢንተርኔት (100% Offline) ይሰራል፦ "]},
                    "ኔትወርክ ባይኖርም ወይም ቢቋረጥም ክትትል መውሰድ፣ ፈተና ማዘጋጀት እና ውጤት መሙላት ይችላሉ። ስልክዎ ኢንተርኔት ሲያገኝ በራሱ ወደ ዋናው ዳታቤዝ ይሰቅለዋል (Auto-Sync)።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["4. ስራዎን ሲጨርሱ 'Logout' አያድርጉ፦ "]},
                    "ስራዎን ሲጨርሱ ዝም ብለው አፑን ይዝጉት፤ Logout ካደረጉ የመግቢያ መረጃው ስለሚጠፋ ያለ ኢንተርኔት መልሶ መግባት አይቻልም።"
                ]
            }
        ]
    },
    {"tag": "hr"},

    # PART 1: APP INSTALLATION
    {
        "tag": "h3",
        "children": ["📱 1. ሲስተሙን ወደ ሞባይል ስልክ መጫን (PWA App Installation)"]
    },
    {
        "tag": "p",
        "children": [
            "ሲስተማችን ልክ እንደ Play Store / App Store አፕሊኬሽን በቀጥታ በስልክዎ ላይ ተጭኖ የሚሰራ ዘመናዊ PWA (Progressive Web App) ነው። ወደ ስልክዎ ለመጫን የሚከተለውን ያድርጉ፦"
        ]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["በ Android ስልክ (Google Chrome)፦ "]},
                    "የሲስተሙን ሊንክ ሲከፍቱ ከስር «📱 አፕሊኬሽን ወደ ስልክዎ ይጫኑ! ⬇️ ጫን» የሚል ቢጫ ባነር ያያሉ። 'ጫን' የሚለውን ይጫኑ። ወይም በ Chrome ከላይ በቀኝ በኩል ያሉትን 3 ነጥቦች (⋮) ነክተው «Install App» ወይም «Add to Home screen» ይምረጡ።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["በ iPhone / iPad (Safari)፦ "]},
                    "በ Safari ሲከፍቱ ከስር ያለውን የ Share ምልክት (⎋) ይጫኑ፤ ከዚያም ወደ ታች ዝቅ ብለው «Add to Home Screen» (ወደ መነሻ ገጽ አክል) የሚለውን ይምረጡ።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["በኮምፒዩተር (Chrome / Edge)፦ "]},
                    "ከላይ በአድራሻ መጻፊያው (URL bar) በቀኝ በኩል የሚታየውን የኮምፒዩተር ምልክት (Install Icon) በመጫን በኮምፒዩተርዎ ላይ መጫን ይችላሉ።"
                ]
            }
        ]
    },
    {"tag": "hr"},

    # PART 2: TEACHERS GUIDE & EXAM AUTHORING
    {
        "tag": "h3",
        "children": ["👨‍🏫 2. የመምህራን የተሟላ አጠቃቀም መመሪያ (Teachers Guide)"]
    },
    {
        "tag": "h4",
        "children": ["ሀ) ፈጣን መግቢያ እና የይለፍ ቃል"]
    },
    {
        "tag": "p",
        "children": [
            "1. በተጠቃሚ ስምዎ እና በይለፍ ቃልዎ ይግቡ። ለመጀመሪያ ጊዜ ሲገቡ ሲስተሙ የደህንነት መጠበቂያ አዲስ የይለፍ ቃል እንዲመርጡ ይጠይቅዎታል።"
        ]
    },
    {
        "tag": "h4",
        "children": ["ለ) የተማሪዎችን ክትትል መመዝገብ (Attendance Taker)"]
    },
    {
        "tag": "ul",
        "children": [
            {"tag": "li", "children": ["በዳሽቦርድዎ ላይ «📅 ክትትል መመዝገቢያ» የሚለውን ይጫኑ፤"]},
            {"tag": "li", "children": ["የሚያስተምሩትን ክፍል ይምረጡ፤"]},
            {"tag": "li", "children": ["የተማሪዎቹ ዝርዝር ሲመጣ ከስሙ ጎን ካሉት 4 አማራጮች ይምረጡ፦ ✅ ተገኝቷል (P) | ❌ አልተገኘም (A) | 📝 በፈቃድ (Per) | ⏰ አርፍዷል (L)፤"]},
            {"tag": "li", "children": ["ከስር «💾 ክትትል መዝግብ» የሚለውን በመጫን ያስቀምጡ። ኢንተርኔት ባይኖርም በስልክዎ ይቀመጣል!"]}
        ]
    },
    {
        "tag": "h4",
        "children": ["ሐ) አዲሱ የፈተና ዝግጅት ማዕከል (Exam Authoring Hub - አዲስ!)"]
    },
    {
        "tag": "p",
        "children": [
            "መምህራን ፈተናዎችን በቀላሉ በስልካቸው ወይም በኮምፒዩተር እንዲያዘጋጁ እና ለትምህርት ክፍል እንዲልኩ የተዘጋጀ እጅግ ዘመናዊ ገጽ ነው፦"
        ]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["ብልህ የክፍልና ኮርስ መረጣ (Smart Auto-Select)፦ "]},
                    "የተመደቡበት 1 ክፍል ብቻ ከሆነ ሲስተሙ በራሱ ይመርጠዋል (ተቆልቋይ አያስመርጥዎትም)፤ 2 እና ከዚያ በላይ ክፍሎች ሲኖሩዎት ብቻ ተቆልቋዩ ወጥቶ ክፍል እንዲመርጡ ይጠይቃል። የትምህርት ዓይነትዎንም ሲስተሙ አስቀድሞ ስላወቀው በእጅ መጻፍ አይጠበቅብዎትም!"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["የትምህርት ዘመን እና ርዕስ፦ "]},
                    "የትምህርት ዘመኑን ሲስተሙ በራሱ ያውቀዋል፤ እንዲሁም ይፋዊው የፈተና ርዕስ በሰንበት ት/ቤቱ ህግ መሰረት ንጹህ ሆኖ በራሱ ይዘጋጃል።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["5 አይነት የፈተና ጥያቄዎች በአንድ ጠቅታ፦ "]},
                    "🔘 ምርጫ (Choice) | ⚖️ እውነት ወይም ሐሰት | 🔄 አዛምድ (Matching) | ✍️ ክፍት ጥያቄ (Workout) | 🔲 ባዶ ቦታ ሙላ።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["የቀጥታ ነጥብ መቆጣጠሪያ (Live Marks Meter)፦ "]},
                    "ጥያቄዎችን እያዘጋጁ ሲሄዱ ነጥቡ 100% መድረሱን በቀለም ያሳውቅዎታል (100% ሲሆን አረንጓዴ፣ ሲያንስ ወይም ከ 100% በላይ ሲሆን ያሳስባል)።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["ፈተናው በጭራሽ አይጠፋም (Auto-Save & Offline)፦ "]},
                    "በስልክዎ ላይ በየሰኮንዱ በራሱ ይቀመጣል። ባትሪ ቢያልቅ ወይም ገጹ ቢዘጋ እንኳን ተመልሰው ሲገቡ የጻፉት እንዳለ ይጠብቅዎታል። እንዲሁም በ JSON ፋይል ወደ ስልክዎ ማውረድ (Export) እና መጫን (Import) ይችላሉ!"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["የራስጌ ቅድመ-ዕይታ እና መላክ፦ "]},
                    "«👁️ የራስጌ ቅድመ-ዕይታ» በመጫን ፈተናው ሲታተም ምን እንደሚመስል ማየት እና ሲጨርሱ «🚀 ፈተናውን ለትምህርት ክፍል ላክ» የሚለውን መጫን ብቻ በቂ ነው!"
                ]
            }
        ]
    },
    {
        "tag": "h4",
        "children": ["መ) የፈተና ውጤት ማስገባት (Marks Entry)"]
    },
    {
        "tag": "p",
        "children": [
            "በዳሽቦርድዎ ላይ «📊 ውጤት መሙያ» በመግባት ክፍል እና የፈተናውን ዓይነት (የክፍል ስራ፣ አጋማሽ፣ ማጠቃለያ) መርጠው ለእያንዳንዱ ተማሪ ውጤት ያስገቡ። አማካይና ደረጃው በራሱ ይሰላል።"
        ]
    },
    {"tag": "hr"},

    # PART 3: ADMIN PORTAL
    {
        "tag": "h3",
        "children": ["🛠️ 3. የትምህርት ክፍል አስተዳዳሪ መመሪያ (Admin Portal)"]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["የፈተናዎች ግምገማና ማጽደቂያ፦ "]},
                    "መምህራን ያዘጋጇቸውን ፈተናዎች መገምገም፣ አስተያየት (Feedback) በመስጠት ማስተካከያ መጠየቅ ወይም ማጽደቅ (Approve ማድረግ)።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["ይፋዊ የፈተና ወረቀት ማተሚያ (Print-Ready A4)፦ "]},
                    "የጸደቀውን ፈተና በሰንበት ትምህርት ቤቱ ይፋዊ አርማ፣ የተማሪ ስም፣ ክፍል፣ የክርስትና ስም እና ቡናማ መለያ መስመር በጠበቀ ደረጃ በአንድ ጠቅታ ለተማሪዎች ማተም።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["የመምህሩ የመልስ ቁልፍ (Answer Key)፦ "]},
                    "በህትመት ገጹ ላይ «🔑 የመልስ ቁልፍ አሳይ» የሚለውን በመጫን ለአራሚዎች የሚሆን የመልስ ወረቀት በቀላሉ ማተም።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["ውጤት ይፋ ማድረግ (Publish Results)፦ "]},
                    "ሴሚስተሩ ሲጠናቀቅ የተማሪዎችን ውጤት ለወላጆችና ለተማሪዎች ይፋ ማድረግ።"
                ]
            }
        ]
    },
    {"tag": "hr"},

    # PART 4: STUDENTS & PARENTS PORTAL
    {
        "tag": "h3",
        "children": ["🎓 4. የተማሪዎች እና የወላጆች መመሪያ (Students & Parents Portal)"]
    },
    {
        "tag": "p",
        "children": [
            "ተማሪዎች እና ወላጆች ውጤትና የሰንበት ት/ቤት መገኘትን በሞባይል ስልካቸው ለማየት፦"
        ]
    },
    {
        "tag": "ul",
        "children": [
            {"tag": "li", "children": ["በመግቢያው ገጽ ስር «🎓 የተማሪ መግቢያ / Student Portal» የሚለውን ይጫኑ፤"]},
            {"tag": "li", "children": ["የተማሪውን ስም ፈልገው ይምረጡ፤"]},
            {"tag": "li", "children": ["በትምህርት ክፍሉ የተሰጠውን ባለ 4-አሃዝ ሚስጥር ቁጥር (PIN) ያስገቡ፤"]},
            {"tag": "li", "children": ["«ወደ ተማሪ ገጽ ይግቡ» የሚለውን ሲጫኑ ሙሉ ውጤት፣ አማካይ፣ ደረጃ እና የአቴንዳንስ ካላንደር ያገኛሉ።"]}
        ]
    },
    {"tag": "hr"},

    # PART 5: DARK MODE & NOTIFICATIONS
    {
        "tag": "h3",
        "children": ["🌙 5. የጨለማ ገጽታ (Dark Mode) እና የበዓላት ማሳወቂያዎች"]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["ዘመናዊ የጨለማ ገጽታ (Dark Mode)፦ "]},
                    "በምሽት ዓይን እንዳይዝል ሲስተሙ በኢትዮጵያ ሰዓት ከምሽቱ 12:00 ጀምሮ በራሱ ወደ Dark Mode ይቀየራል፤ ወይም ከላይ ባለው ቁልፍ እንደ ፍላጎትዎ ማብራትና ማጥፋት ይችላሉ።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["የሰንበት ት/ቤት ዜናዎች እና የበዓላት ማሳወቂያዎች፦ "]},
                    "በካሌንደሩ ላይ የሰንበት ትምህርት ቤቱ እውነተኛ ዜናዎችና የፈተና መርሃ ግብሮች ይታያሉ። እንዲሁም የበዓላት ቀናት ሲደርሱ ወደ ስልክዎ አውቶማቲክ ማሳወቂያ (Web Push Notification) ይደርስዎታል።"
                ]
            }
        ]
    },
    {"tag": "hr"},

    # CONTACT & SUPPORT
    {
        "tag": "blockquote",
        "children": [
            "📞 የቴክኒክ ድጋፍ እና እገዛ ካስፈለገዎት፦\n"
            "የሲስተም አበልጻጊ፦ ተስፋ (Tesfa)\n"
            "ስልክ፦ 0943854325 | ቴሌግራም፦ @shetesfa\n"
            "የሲስተም አድራሻ፦ https://atsede-result.onrender.com\n"
            "⛪ አጸደ ትጉሃን ሰንበት ትምህርት ቤት"
        ]
    }
]

# 3. Create Page via Telegraph API
create_url = "https://api.telegra.ph/createPage"
payload = {
    "access_token": token,
    "title": "የአጸደ ትጉሃን ሰንበት ት/ቤት ሲስተም አጠቃቀም መመሪያ (ዘመናዊ ዕትም)",
    "author_name": "አጸደ ትጉሃን ሰንበት ት/ቤት",
    "author_url": "https://atsede-result.onrender.com",
    "content": json.dumps(nodes, ensure_ascii=False),
    "return_content": False
}

data = urllib.parse.urlencode(payload).encode('utf-8')
req = urllib.request.Request(create_url, data=data, headers={'User-Agent': 'Mozilla/5.0'})
try:
    with urllib.request.urlopen(req) as resp:
        res_data = json.loads(resp.read().decode('utf-8'))
        if res_data.get('ok'):
            page_url = res_data['result']['url']
            print("="*60)
            print("🎉 አዲሱ የቴሌግራፍ መመሪያ በተሳካ ሁኔታ ተለጥፏል!")
            print(f"🔗 የቀጥታ አድራሻ (Live URL)፦ {page_url}")
            print("="*60)
            
            # Save token and url to config
            with open('telegraph_config.json', 'w', encoding='utf-8') as f:
                json.dump({
                    "token": token,
                    "url": page_url,
                    "path": res_data['result']['path']
                }, f, ensure_ascii=False, indent=2)
        else:
            print("Telegraph createPage error:", res_data)
except Exception as e:
    print("Request exception:", e)
