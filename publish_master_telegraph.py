# -*- coding: utf-8 -*-
"""
publish_master_telegraph.py - Combines the user's authentic original Telegraph guide
(with all its sections and authentic screenshots) with the new Speed Tips, Exam Authoring,
Official Exam Printing, Calendar News, and Dark Mode features, then publishes to Telegraph.
"""
import sys
import json
import urllib.request
import urllib.parse

sys.stdout.reconfigure(encoding='utf-8')

# 1. Load original Telegraph content
with open('user_original_telegraph.json', 'r', encoding='utf-8') as f:
    orig_data = json.load(f)

orig_nodes = orig_data['result']['content']
print(f"Loaded {len(orig_nodes)} original nodes from user's guide.")

# 2. Prepare Speed / Light-weight intro nodes to be placed at the very top (FIRST!)
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

# 3. Prepare the new modern features (Exam Authoring, Admin Exam Printing, Dark Mode)
exam_and_modern_nodes = [
    {"tag": "hr"},
    {
        "tag": "h3",
        "children": ["📝 ክፍል 6፦ አዲሱ የፈተና ዝግጅት ማዕከል (Exam Authoring Hub - አዲስ!)"]
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
                    {"tag": "strong", "children": ["• ብልህ የክፍልና ኮርስ መረጣ (Smart Auto-Select)፦ "]},
                    "የተመደቡበት 1 ክፍል ብቻ ከሆነ ሲስተሙ በራሱ ይመርጠዋል (ተቆልቋይ አያስመርጥዎትም)፤ 2 እና ከዚያ በላይ ክፍሎች ሲኖሩዎት ብቻ ተቆልቋዩ ወጥቶ ክፍል እንዲመርጡ ይጠይቃል። የትምህርት ዓይነትዎንም ሲስተሙ አስቀድሞ ስላወቀው በእጅ መጻፍ አይጠበቅብዎትም!"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• የትምህርት ዘመን እና ርዕስ፦ "]},
                    "የትምህርት ዘመኑን ሲስተሙ በራሱ ያውቀዋል፤ እንዲሁም ይፋዊው የፈተና ርዕስ በሰንበት ት/ቤቱ ህግ መሰረት ንጹህ ሆኖ ያለምንም የእንግሊዝኛ ቅንፍ በራሱ ይዘጋጃል።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• 5 አይነት የፈተና ጥያቄዎች በአንድ ጠቅታ፦ "]},
                    "🔘 ምርጫ (Choice) | ⚖️ እውነት ወይም ሐሰት | 🔄 አዛምድ (Matching) | ✍️ ክፍት ጥያቄ (Workout) | 🔲 ባዶ ቦታ ሙላ።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• የቀጥታ ነጥብ መቆጣጠሪያ (Live Marks Meter)፦ "]},
                    "ጥያቄዎችን እያዘጋጁ ሲሄዱ ነጥቡ 100% መድረሱን በቀለም ያሳውቅዎታል (100% ሲሆን አረንጓዴ፣ ሲያንስ ወይም ከ 100% በላይ ሲሆን ያሳስባል)።"
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
                    {"tag": "strong", "children": ["• የራስጌ ቅድመ-ዕይታ እና መላክ፦ "]},
                    "«👁️ የራስጌ ቅድመ-ዕይታ» በመጫን ፈተናው ሲታተም ምን እንደሚመስል ማየት እና ሲጨርሱ «🚀 ፈተናውን ለትምህርት ክፍል ላክ» የሚለውን መጫን ብቻ በቂ ነው!"
                ]
            }
        ]
    },
    {
        "tag": "h3",
        "children": ["🖨️ ክፍል 7፦ የትምህርት ክፍል የፈተናዎች ግምገማና ይፋዊ ማተሚያ (Admin Exam Portal)"]
    },
    {
        "tag": "ul",
        "children": [
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• የፈተናዎች ግምገማና ማጽደቂያ፦ "]},
                    "መምህራን ያዘጋጇቸውን ፈተናዎች መገምገም፣ አስተያየት (Feedback) በመስጠት ማስተካከያ መጠየቅ ወይም ማጽደቅ (Approve ማድረግ)።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• ይፋዊ የፈተና ወረቀት ማተሚያ (Print-Ready A4)፦ "]},
                    "የጸደቀውን ፈተና በሰንበት ትምህርት ቤቱ ይፋዊ አርማ፣ የተማሪ ስም፣ ክፍል፣ የክርስትና ስም፣ ቡናማ መለያ መስመር እና ይፋዊ ርዕስ በጠበቀ ደረጃ በአንድ ጠቅታ ለተማሪዎች ማተም።"
                ]
            },
            {
                "tag": "li",
                "children": [
                    {"tag": "strong", "children": ["• የመምህሩ የመልስ ቁልፍ (Answer Key)፦ "]},
                    "በህትመት ገጹ ላይ «🔑 የመልስ ቁልፍ አሳይ» የሚለውን በመጫን ለአራሚዎች የሚሆን የመልስ ወረቀት በቀላሉ ማተም።"
                ]
            }
        ]
    },
    {
        "tag": "h3",
        "children": ["🌙 ክፍል 8፦ የጨለማ ገጽታ (Dark Mode) እና የበዓላት ማሳወቂያዎች"]
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

# 4. Filter or combine: insert speed_nodes right after the first welcome blockquote
master_nodes = []
if len(orig_nodes) > 0 and orig_nodes[0].get('tag') == 'blockquote':
    master_nodes.append(orig_nodes[0]) # Welcome blockquote
    master_nodes.extend(speed_nodes)   # Speed Tips placed FIRST!
    remaining_orig = orig_nodes[1:]
else:
    master_nodes.extend(speed_nodes)
    remaining_orig = orig_nodes

# Exclude original contact blockquote at bottom if present, we'll append updated contact
cleaned_remaining = []
for node in remaining_orig:
    if node.get('tag') == 'blockquote' and any('0943854325' in str(c) for c in node.get('children', [])):
        continue
    cleaned_remaining.append(node)

master_nodes.extend(cleaned_remaining)
master_nodes.extend(exam_and_modern_nodes)

# Updated Contact footer
master_nodes.extend([
    {"tag": "hr"},
    {
        "tag": "blockquote",
        "children": [
            "📞 የቴክኒክ ድጋፍ እና እገዛ ካስፈለገዎት፦\n"
            "የሲስተም አበልጻጊ፦ ተስፋ (Tesfa)\n"
            "ስልክ፦ 0943854325 | ቴሌግራም፦ @shetesfa\n"
            "የሲስተም የቀጥታ አድራሻ፦ https://atsede-result.onrender.com\n"
            "⛪ አጸደ ትጉሃን ሰንበት ትምህርት ቤት"
        ]
    }
])

print(f"Total Master Nodes to publish: {len(master_nodes)}")

# 5. Create Telegraph Account and Publish
account_url = "https://api.telegra.ph/createAccount?short_name=AtsedeMaster&author_name=" + urllib.parse.quote("አጸደ ትጉሃን ሰንበት ት/ቤት")
req = urllib.request.Request(account_url, headers={'User-Agent': 'Mozilla/5.0'})
with urllib.request.urlopen(req) as resp:
    acc_data = json.loads(resp.read().decode('utf-8'))

token = acc_data['result']['access_token']
print("Obtained Telegraph token.")

create_url = "https://api.telegra.ph/createPage"
payload = {
    "access_token": token,
    "title": "የአጸደ ትጉሃን ሰንበት ት/ቤት ሲስተም የተጠቃሚ መመሪያ (የተሟላ ማስተር ዕትም)",
    "author_name": "አጸደ ትጉሃን ሰንበት ት/ቤት",
    "author_url": "https://atsede-result.onrender.com",
    "content": json.dumps(master_nodes, ensure_ascii=False),
    "return_content": False
}

data = urllib.parse.urlencode(payload).encode('utf-8')
req = urllib.request.Request(create_url, data=data, headers={'User-Agent': 'Mozilla/5.0'})
with urllib.request.urlopen(req) as resp:
    res_data = json.loads(resp.read().decode('utf-8'))
    if res_data.get('ok'):
        page_url = res_data['result']['url']
        print("="*70)
        print("🎉 ማስተር የቴሌግራፍ መመሪያው በተሳካ ሁኔታ ታትሞ ተለጥፏል!")
        print(f"🔗 የቀጥታ አድራሻ (Live Master URL)፦ {page_url}")
        print("="*70)

        with open('master_telegraph_config.json', 'w', encoding='utf-8') as f:
            json.dump({
                "token": token,
                "url": page_url,
                "path": res_data['result']['path']
            }, f, ensure_ascii=False, indent=2)
    else:
        print("Telegraph createPage failed:", res_data)
