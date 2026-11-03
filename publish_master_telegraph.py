# -*- coding: utf-8 -*-
"""
publish_master_telegraph.py - Master Telegraph Publisher:
1. Designed specifically for TEACHERS (Admin section removed).
2. Includes detailed step-by-step Exam Writing Guide (የፈተና አጻጻፍ መመሪያ).
3. Speed tips placed at the very top.
4. Preserves all authentic original user sections and screenshots.
5. Adds developer info: Tesfa / @shetesfa in the technical support contact.
6. Edits the existing page if token/path exist, or creates a new page.
"""
import sys
import os
import json
import urllib.request
import urllib.parse

sys.stdout.reconfigure(encoding='utf-8')

# 1. Load original Telegraph content
with open('user_original_telegraph.json', 'r', encoding='utf-8') as f:
    orig_data = json.load(f)

orig_nodes = orig_data['result']['content']
print(f"Loaded {len(orig_nodes)} original nodes from user's guide.")

# 2. Speed / Light-weight intro nodes placed at the top (FIRST!)
speed_nodes = [
    {
        "tag": "blockquote",
        "children": [
            "⚡ ሲስተሙ እንዳይከብድዎትና እጅግ ፈጣን እንዲሆን የሚረዱ 4 ቁልፍ ምክሮች፦\n"
            "አንዳንድ መምህራን «ሲስተሙ በስልኬ ላይ ከበደኝ/ዘገየብኝ» የሚሉት አፑን በስልካቸው ላይ ስላልጫኑት ወይም በየጊዜው በብሮውዘር ላይ ፈልገው ስለሚከፍቱት ነው። ሲስተሙ ልክ እንደ WhatsApp በቅጽበት እንዲከፍት እና ምንም ዳታ እንዳይፈጅ የሚከተሉትን 4 ነገሮች ይተግብሩ፦\n"
            "1. አፑን ወደ ስልክዎ ይጫኑ (Install App)፦ ከመነሻ ገጽ (Home Screen) ላይ በአንድ ንክኪ ይከፍታል፤ ፋይሎቹ ስልክዎ ላይ ስለሚቀመጡ ለመክፈት ምንም ኢንተርኔት አይጠብቅም!\n"
            "2. 'አስታውሰኝ (Remember Me)' ይጠቀሙ፦ ለ 30 ቀናት በስልክዎ ላይ ክፍት ሆኖ ይቆያል፤ በየቀኑ የተጠቃሚ ስምና የይለፍ ቃል መጻፍ አይጠበቅብዎትም።\n"
            "3. 100% ያለ ኢንተርኔት (Offline) ይሰራል፦ ኔትወርክ ባይኖርም እንኳን ክትትል መውሰድ፣ ፈተና ማዘጋጀትና ውጤት መሙላት ይችላሉ። ስልክዎ ኢንተርኔት ሲያገኝ በራሱ ይሰቅላል (Auto-Sync)።\n"
            "4. ስራዎን ሲጨርሱ 'Logout' አያድርጉ፦ ዝም ብለው አፑን ይዝጉት፤ Logout ካደረጉ የመግቢያ መረጃው ስለሚጠፋ ያለ ኢንተርኔት መልሶ መግባት አይቻልም።"
        ]
    },
    {"tag": "hr"}
]

# 3. Comprehensive Exam Writing and Modern Features for Teachers ONLY (No Admin section)
teacher_exam_nodes = [
    {"tag": "hr"},
    {
        "tag": "h3",
        "children": ["📝 ክፍል 6፦ አዲሱ የፈተና ዝግጅትና አጻጻፍ መመሪያ (Exam Writing Guide)"]
    },
    {
        "tag": "p",
        "children": [
            "መምህራን የፈተና ጥያቄዎችን በቀላሉ በስልካቸው ወይም በኮምፒዩተር ደረጃውን በጠበቀ መልኩ የሚያዘጋጁበትና ለትምህርት ክፍል የሚልኩበት አሰራር፦"
        ]
    },
    {
        "tag": "h4",
        "children": ["ሀ) የፈተናው አጠቃላይ መረጃ አሞላል፦"]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["1. ክፍል መምረጥ፦ "]},
                    "የተመደቡበት 1 ክፍል ብቻ ከሆነ ሲስተሙ በራሱ ይመርጠዋል (ምንም ጥያቄ አያስመርጥዎትም)፤ 2 እና ከዚያ በላይ ክፍሎች ሲኖሩዎት ብቻ ተቆልቋዩ ወጥቶ ክፍል እንዲመርጡ ይጠይቅዎታል።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["2. የትምህርት ዓይነት (Course)፦ "]},
                    "የሚያስተምሩትን ትምህርት ሲስተሙ አስቀድሞ ስላወቀው በራሱ ይሞላዋል፤ መምህሩ በእጅ መጻፍ አይጠበቅበትም!"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["3. መንፈቀ ዓመት እና የፈተና ዓይነት፦ "]},
                    "መንፈቀ ዓመቱን (1ኛ ወይም 2ኛ መንፈቀ ዓመት) እና የፈተናውን ዓይነት (የአጋማሽ ፈተና 20%፣ የማጠቃለያ ፈተና 30% ወይም የክፍል ፈተና) ይምረጡ።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["4. የሚፈጀው ጊዜ እና መመሪያ፦ "]},
                    "ፈተናው የሚፈጀውን ጊዜ በደቂቃ (ለምሳሌ 60 ደቂቃ) ያስገቡ፤ እንዲሁም ለተማሪዎች የሚሰጥ አጠቃላይ መመሪያ ካለ በመመሪያ ሳጥኑ ውስጥ ይጻፉ።"
                ]
            }
        ]
    },
    {
        "tag": "h4",
        "children": ["ለ) 5ቱ የፈተና ጥያቄ ዓይነቶች አጻጻፍ (Step-by-Step Question Writing)፦"]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["1. 🔘 ምርጫ (Multiple Choice)፦ "]},
                    "የጥያቄውን ጽሑፍ ይጻፉ → አራቱን አማራጮች (ሀ፣ ለ፣ ሐ፣ መ) ያስገቡ → ከስር «ትክክለኛ መልስ» በሚለው ሳጥን ላይ ትክክለኛውን ፊደል (ሀ፣ ለ፣ ሐ ወይም መ) ይምረጡ → ነጥብ ይመድቡ (ለምሳሌ 2 ነጥብ)።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["2. ⚖️ እውነት ወይም ሐሰት (True or False)፦ "]},
                    "አረፍተ ነገሩን ይጻፉ → ከስር ትክክለኛውን መልስ «እውነት» ወይም «ሐሰት» የሚለውን ይምረጡ → ነጥብ ይመድቡ (ለምሳሌ 1 ነጥብ)። በወረቀቱ ላይ ተማሪው መልሱን የሚጽፍበት መስመር በራሱ ይዘጋጃል።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["3. 🔄 አዛምድ (Matching)፦ "]},
                    "በግራ በኩል በ 'ሀ' ረድፍ ያሉትን ቃላት ይጻፉ፤ በቀኝ በኩል በ 'ለ' ረድፍ የሚዛመዱትን ቃላት ያስገቡ። በፈተና ወረቀቱ ላይ ተማሪው የሚያዛምድበት ክፍት ቅንፍና መስመር በራሱ ይዘጋጃል!"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["4. ✍️ ክፍት ጥያቄ (Workout / Essay)፦ "]},
                    "ጥያቄውን ይጻፉ → «ለተማሪው የሚተው የመስመር ብዛት» የሚለውን ይወስኑ (ለምሳሌ 4 ወይም 5 መስመሮች)፤ በፈተና ወረቀቱ ላይ ለተማሪው መጻፊያ ነጥብ-ነጥብ መስመሮች በራሳቸው ይወጣሉ! → ለአራሚው መምህር የሚሆን «የመልስ ፍንጭ/ቁልፍ» ያስገቡ (ይህ ለተማሪው አይታይም፣ ለመልስ ቁልፍ ማተሚያ ብቻ ያገለግላል)።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["5. 🔲 ባዶ ቦታ ሙላ (Fill in the Blanks)፦ "]},
                    "ጥያቄውን ባዶ ቦታ ትተው ይጻፉ → ትክክለኛውን የሚሞላ ቃል ያስገቡ።"
                ]
            }
        ]
    },
    {
        "tag": "h4",
        "children": ["ሐ) የፈተናው ልዩ ዘመናዊ መገልገያዎች፦"]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• የቀጥታ ነጥብ መቆጣጠሪያ (Live 100% Meter)፦ "]},
                    "እያንዳንዱ ጥያቄ ነጥብ ሲሰጠው ድምሩን እያሰላ ያሳያል፤ ልክ 100% ሲሞላ አረንጓዴ «✅ ልክ 100% ተሞልቷል!» ይላል። ከ 100% ከበለጠ ወይም ካነሰ ወዲያውኑ ያሳውቅዎታል።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• ጥያቄዎችን ማስተካከልና ማዘዋወር፦ "]},
                    "«⬆️» እና «⬇️» አዝራሮችን በመጫን የጥያቄዎችን ቅደም ተከተል መቀያየር፣ ወይም «🗑️» በመጫን ጥያቄን ማጥፋት ይችላሉ።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• ፈተናው በጭራሽ አይጠፋም (Auto-Save & Offline)፦ "]},
                    "በስልክዎ ላይ በየሰኮንዱ በራሱ ይቀመጣል። ባትሪ ቢያልቅ ወይም ገጹ ቢዘጋ እንኳን ተመልሰው ሲገቡ የጻፉት እንዳለ ይጠብቅዎታል። እንዲሁም በ JSON ፋይል ወደ ስልክዎ ማውረድ (Export) እና መጫን (Import) ይችላሉ!"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• የራስጌ ቅድመ-ዕይታ እና ለትምህርት ክፍል መላክ፦ "]},
                    "«👁️ የራስጌ ቅድመ-ዕይታ» በመጫን ፈተናው በሰንበት ት/ቤቱ ይፋዊ ራስጌ ምን እንደሚመስል ማየት እና ሲጨርሱ «🚀 ፈተናውን ለትምህርት ክፍል ላክ» የሚለውን መጫን ብቻ በቂ ነው!"
                ]
            }
        ]
    },
    {
        "tag": "h3",
        "children": ["🌙 ክፍል 7፦ የጨለማ ገጽታ (Dark Mode) እና የበዓላት ማሳወቂያዎች"]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• ዘመናዊ የጨለማ ገጽታ (Dark Mode)፦ "]},
                    "በምሽት ዓይን እንዳይዝል ሲስተሙ በኢትዮጵያ ሰዓት ከምሽቱ 12:00 ጀምሮ በራሱ ወደ Dark Mode ይቀየራል፤ ወይም ከላይ ባለው ቁልፍ እንደ ፍላጎትዎ ማብራትና ማጥፋት ይችላሉ።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• የሰንበት ት/ቤት ዜናዎች እና የበዓላት ማሳወቂያዎች፦ "]},
                    "በካሌንደሩ ላይ የሰንበት ትምህርት ቤቱ እውነተኛ ዜናዎችና የፈተና መርሃ ግብሮች ይታያሉ። እንዲሁም የበዓላት ቀናት ሲደርሱ ወደ ስልክዎ አውቶማቲክ ማሳወቂያ (Web Push Notification) ይደርስዎታል።"
                ]
            }
        ]
    }
]

# 4. Clean original nodes: remove old contact footer nodes
cleaned_orig_nodes = []
for node in orig_nodes:
    node_str = json.dumps(node, ensure_ascii=False)
    # Remove old contact/footer blocks
    if 'ለበለጠ መረጃ' in node_str or '0943854325' in node_str:
        print("Filtered out old contact node:", node_str[:80])
        continue
    cleaned_orig_nodes.append(node)

# Build Master Nodes
master_nodes = []
if len(cleaned_orig_nodes) > 0 and cleaned_orig_nodes[0].get('tag') == 'blockquote':
    master_nodes.append(cleaned_orig_nodes[0]) # Welcome blockquote
    master_nodes.extend(speed_nodes)            # Speed Tips FIRST
    master_nodes.extend(cleaned_orig_nodes[1:]) # Rest of original sections
else:
    master_nodes.extend(speed_nodes)
    master_nodes.extend(cleaned_orig_nodes)

master_nodes.extend(teacher_exam_nodes)

# Add Developer Tesfa and Church Contact footer
master_nodes.extend([
    {"tag": "hr"},
    {
        "tag": "blockquote",
        "children": [
            "⛪ አጸደ ትጉሃን ሰንበት ትምህርት ቤት የትምህርት ክፍል\n"
            "የሲስተም የቀጥታ አድራሻ፦ https://atsede-result.onrender.com\n\n"
            "📞 የቴክኒክ ድጋፍ እና አበልጻጊ (Technical Support & Developer)፦\n"
            "ተስፋ (Tesfa)\n"
            "ቴሌግራም፦ @shetesfa"
        ]
    }
])

print(f"Total Master Nodes to publish: {len(master_nodes)}")

# 5. Check if we have existing config to edit the page directly
existing_token = None
existing_path = None
if os.path.exists('master_telegraph_config.json'):
    try:
        with open('master_telegraph_config.json', 'r', encoding='utf-8') as f:
            cfg = json.load(f)
            existing_token = cfg.get('token')
            existing_path = cfg.get('path')
    except Exception as e:
        print(f"Could not load master config: {e}")

page_updated = False
page_url = None

if existing_token and existing_path:
    print(f"Attempting to edit existing page: {existing_path}...")
    edit_url = "https://api.telegra.ph/editPage"
    edit_payload = {
        "access_token": existing_token,
        "path": existing_path,
        "title": "የአጸደ ትጉሃን ሰንበት ት/ቤት ሲስተም የተጠቃሚ መመሪያ (የተሟላ ማስተር ዕትም)",
        "author_name": "አጸደ ትጉሃን | ተስፋ (@shetesfa)",
        "author_url": "https://t.me/shetesfa",
        "content": json.dumps(master_nodes, ensure_ascii=False),
        "return_content": False
    }
    try:
        data = urllib.parse.urlencode(edit_payload).encode('utf-8')
        req = urllib.request.Request(edit_url, data=data, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req) as resp:
            res_data = json.loads(resp.read().decode('utf-8'))
            if res_data.get('ok'):
                page_url = res_data['result']['url']
                page_updated = True
                print("="*70)
                print("🎉 ነባሩ የቴሌግራፍ ገጽ በተሳካ ሁኔታ ተሻሽሏል (Updated via editPage)!")
                print(f"🔗 የቀጥታ አድራሻ፦ {page_url}")
                print("="*70)
            else:
                print("editPage response not ok:", res_data)
    except Exception as e:
        print(f"Failed to editPage: {e}")

# If edit failed or didn't exist, create a new page
if not page_updated:
    print("Creating a new Telegraph page...")
    account_url = "https://api.telegra.ph/createAccount?short_name=AtsedeMaster&author_name=" + urllib.parse.quote("አጸደ ትጉሃን | ተስፋ")
    req = urllib.request.Request(account_url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req) as resp:
        acc_data = json.loads(resp.read().decode('utf-8'))

    token = acc_data['result']['access_token']
    print("Obtained new Telegraph token.")

    create_url = "https://api.telegra.ph/createPage"
    payload = {
        "access_token": token,
        "title": "የአጸደ ትጉሃን ሰንበት ት/ቤት ሲስተም የተጠቃሚ መመሪያ (የተሟላ ማስተር ዕትም)",
        "author_name": "አጸደ ትጉሃን | ተስፋ (@shetesfa)",
        "author_url": "https://t.me/shetesfa",
        "content": json.dumps(master_nodes, ensure_ascii=False),
        "return_content": False
    }

    data = urllib.parse.urlencode(payload).encode('utf-8')
    req = urllib.request.Request(create_url, data=data, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req) as resp:
        res_data = json.loads(resp.read().decode('utf-8'))
        if res_data.get('ok'):
            page_url = res_data['result']['url']
            path = res_data['result']['path']
            print("="*70)
            print("🎉 አዲስ ማስተር የቴሌግራፍ መመሪያ በተሳካ ሁኔታ ታትሟል!")
            print(f"🔗 የቀጥታ አድራሻ (Live Master URL)፦ {page_url}")
            print("="*70)

            with open('master_telegraph_config.json', 'w', encoding='utf-8') as f:
                json.dump({
                    "token": token,
                    "url": page_url,
                    "path": path
                }, f, ensure_ascii=False, indent=2)
        else:
            print("Telegraph createPage failed:", res_data)
