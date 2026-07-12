# 🐛 বাগ রিপোর্ট — ৫ম অডিট

**AI Office — Dropshipping Automation**  
**তারিখ:** ১২ জুলাই ২০২৬  
**ব্রাঞ্চ:** `arena/019f56b3-bottele`  
**কমিট:** `4655848`

---

## 📊 সারসংক্ষেপ

| বিভাগ | সংখ্যা |
|--------|---------|
| 🔴 ক্রিটিক্যাল (সিস্টেম ভাঙা/সুরক্ষা ফাঁক) | ৭টি |
| 🟡 মাঝারি (ডাটা লিক/ত্রুটি) | ৭টি |
| 🟢 সামান্য (হার্ডেনিং/প্রতিরোধ) | ৫টি |
| **মোট** | **১৯টি** |

---

## 🔴 ক্রিটিক্যাল বাগ (৭টি)

### ১. `setAgentRunning(false)` এরর স্টেট 'idle' ওভাররাইট করে 🐛🐛🐛
**ফাইল:** `lib/agents.php`  
**সমস্যা:** ক্যাচ ব্লকে `setAgentState('error')` এর পর `setAgentRunning(false)` কল হতো, যা স্টেট আবার 'idle' করে দিতো। এজেন্ট কখনো 'error' স্টেট দেখাতো না।  
**সমাধান:** try ও catch ব্লক থেকে `setAgentRunning(false)` সরানো হয়েছে। `setAgentState()` ইতিমধ্যে স্টেট সেট করে।

### ২. `login()` ও `changePassword()` তে null PDO ক্র্যাশ 🐛🐛🐛
**ফাইল:** `lib/auth.php`  
**সমস্যা:** ডাটাবেস কানেকশন ফেইল করলে `$this->db->pdo` null হয়। `null->prepare()` কলে PHP 7.4+ তে TypeError আসে (Exception না, TypeError!) — try/catch ধরতো না।  
**সমাধান:** `if (!$this->db->isConnected())` গার্ড যোগ।

### ৩. ইনস্টলার step skip — খালি config.php তৈরি 🐛🐛
**ফাইল:** `install.php`  
**সমস্যা:** ইউজার সরাসরি POST step=4 পাঠাতে পারতো, step 2 (DB কানেকশন) ছাড়াই। ফলে খালি DB ভ্যালু দিয়ে config.php তৈরি হতো।  
**সমাধান:** `if (empty($_SESSION['install_db']))` গার্ড যোগ, সেশন ছাড়া step 4 চলবে না।

### ৪. লগইন CSRF সম্পূর্ণ অকার্যকর 🐛🐛🐛
**ফাইল:** `lib/auth.php`, `login.php`, `api/auth.php`  
**সমস্যা:** CSRF টোকেন শুধু সফল লগইনের পর `login()` মেথডে তৈরি হতো। লগইনের আগে টোকেন খালি থাকতো। `api/auth.php` তে `!empty($_SESSION['csrf_token'])` চেক থাকায় খালি টোকেনে CSRF বাইপাস হতো। `login.php` POST হ্যান্ডলারে কোনো CSRF চেকই ছিল না।  
**সমাধান:** Auth কনস্ট্রাক্টরে প্রি-লগইন CSRF টোকেন তৈরি, `login.php` POST তে CSRF যাচাই, `api/auth.php` থেকে `!empty()` বাইপাস সরানো।

### ৫. এজেন্ট রানার TOCTOU রেস কন্ডিশন 🐛🐛
**ফাইল:** `lib/db.php`  
**সমস্যা:** দুটি রিকোয়েস্ট একসাথে 'idle' দেখে একই এজেন্ট শুরু করতে পারতো। আগের `UPDATE ... WHERE state IN ('idle','error')` + `rowCount()` পদ্ধতি রো না থাকলে ফেইল করতো।  
**সমাধান:** `INSERT ... ON DUPLICATE KEY UPDATE` + `rowCount()` অ্যাটমিক পদ্ধতি। rowCount: ১=নতুন INSERT, ২=সফল UPDATE, ০=ইতিমধ্যে working।

### ৬. ইমেইল টেমপ্লেট XSS — HTML এস্কেপিং নেই 🐛🐛
**ফাইল:** `lib/mailer.php`  
**সমস্যা:** কার্ট রিকভারি ইমেইলে `$name`, `$store`, `$coupon` ভেরিয়েবল সরাসরি HTML এ বসানো হতো। ইউজার-কন্ট্রোলড ভ্যালু থেকে XSS হতে পারতো।  
**সমাধান:** `htmlspecialchars()` দিয়ে `$safeName`, `$safeStore`, `$safeCoupon` এস্কেপ।

### ৭. `setAgentRunningIfIdle()` তে মিসিং রো — এজেন্ট কখনো শুরু হতো না 🐛🐛
**ফাইল:** `lib/db.php`  
**সমস্যা:** `agent_state` টেবিলে রো না থাকলে `UPDATE` এ 0 রো আপডেট হতো, `rowCount()` = 0, ফলে `setAgentRunningIfIdle()` false রিটার্ন করতো। নতুন এজেন্ট কখনো শুরু হতো না।  
**সমাধান:** `INSERT ... ON DUPLICATE KEY UPDATE` পদ্ধতি — রো না থাকলে INSERT, থাকলে conditional UPDATE।

---

## 🟡 মাঝারি বাগ (৭টি)

### ৮. `api/auth.php` 'check' অ্যাকশন ইউজারনেম লিক করতো
**ফাইল:** `api/auth.php`  
**সমস্যা:** লগইন ছাড়াই `check` অ্যাকশনে username রিটার্ন হতো।  
**সমাধান:** `username` ফিল্ড 'check' রেসপন্স থেকে সরানো।

### ৯. সেটিংস POST এ কোনো ভ্যালিডেশন ছিল না
**ফাইল:** `api/settings.php`  
**সমস্যা:** `profit_margin` নেগেটিভ, `cart_step1_hours` এ ৯৯৯৯ — যেকোনো ভ্যালু সেভ হতো।  
**সমাধান:** min/max/type/maxlen ভ্যালিডেশন রুল যোগ।

### ১০. CSV ইম্পোর্ট UTF-8 BOM হ্যান্ডেল করতো না
**ফাইল:** `api/import.php`  
**সমস্যা:** Windows-এ তৈরি CSV তে BOM (`\xEF\xBB\xBF`) থাকে, যা প্রথম হেডার করাপ্ট করতো।  
**সমাধান:** `preg_replace('/^\xEF\xBB\xBF/', '', $h)` দিয়ে BOM সরানো।

### ১১. ক্যাটালগ ইম্পোর্টে সারি লিমিট ছিল না
**ফাইল:** `api/import.php`  
**সমস্যা:** ১০০০+ সারি ইম্পোর্ট করলে PHP টাইমআউট হতো।  
**সমাধান:** `$maxRows = 100` লিমিট যোগ, বাকি সারি স্কিপ।

### ১২. `bootstrap.php` রিডাইরেক্ট api/ থেকে ভাঙা ছিল
**ফাইল:** `lib/bootstrap.php`  
**সমস্যা:** `/api/../install.php` পাথ কিছু সার্ভারে কাজ করতো না।  
**সমাধান:** `preg_replace('#/api$#', '', $scriptDir)` দিয়ে সঠিক বেস পাথ হিসাব।

### ১৩. DB কানেকশন স্টেটাস সবসময় "সক্রিয় ✅" দেখাতো
**ফাইল:** `index.php`  
**সমস্যা:** ডাটাবেস ডাউন থাকলেও "সক্রিয় ✅" হার্ডকোড ছিল।  
**সমাধান:** `$db->isConnected()` দিয়ে ডাইনামিক ডিসপ্লে।

### ১৪. `createUser()` null PDO তে ক্র্যাশ
**ফাইল:** `lib/auth.php`  
**সমস্যা:** `login()` ও `changePassword()` ফিক্স করা হয়েছিল কিন্তু `createUser()` মিস হয়েছিল।  
**সমাধান:** `isConnected()` গার্ড যোগ।

---

## 🟢 সামান্য বাগ ও হার্ডেনিং (৫টি)

### ১৫. `cartRecovery()` 'add' অ্যাকশনে `isConnected()` চেক ছিল না
**ফাইল:** `lib/agents.php`  
**সমাধান:** `isConnected()` গার্ড যোগ।

### ১৬. `orderPrep()` 'mark_forwarded' এ `isConnected()` চেক ছিল না
**ফাইল:** `lib/agents.php`  
**সমাধান:** `isConnected()` গার্ড যোগ।

### ১৭. `Demo::seed()` তে null PDO চেক ছিল না
**ফাইল:** `lib/demo.php`  
**সমাধান:** `isConnected()` গার্ড যোগ।

### ১৮. `lib/` ডিরেক্টরিতে `.htaccess` ছিল না
**ফাইল:** `lib/.htaccess` (নতুন)  
**সমস্যা:** রুট `.htaccess` এ রিরাইট রুল আছে কিন্তু mod_rewrite বন্ধ থাকলে lib/ ফাইল ব্রাউজারে দেখা যেতো।  
**সমাধান:** `Order Allow,Deny / Deny from all` যোগ।

### ১৯. API রেসপন্সে ক্যাশ হেডার ছিল না
**ফাইল:** `api/state.php`, `api/settings.php`  
**সমস্যা:** CDN/ব্রাউজার সেটিংস ও স্টেট ক্যাশ করতে পারতো (সংবেদনশীল ডাটা)।  
**সমাধান:** `Cache-Control: no-store, no-cache, must-revalidate` + `Pragma: no-cache` যোগ।

---

## 📁 পরিবর্তিত ফাইল তালিকা (১৪টি)

| ফাইল | পরিবর্তন |
|------|----------|
| `api/auth.php` | CSRF বাইপাস সরানো, username লিক সরানো |
| `api/import.php` | BOM রিমুভাল, ১০০ সারি লিমিট |
| `api/settings.php` | ভ্যালিডেশন রুল, ক্যাশ হেডার |
| `api/state.php` | ক্যাশ হেডার |
| `index.php` | ডাইনামিক DB স্টেটাস |
| `install.php` | সেশন গার্ড (step skip রোধ) |
| `lib/.htaccess` | নতুন — Deny from all |
| `lib/agents.php` | isConnected() গার্ড, setAgentRunning সরানো |
| `lib/auth.php` | প্রি-লগইন CSRF টোকেন, isConnected() গার্ড |
| `lib/bootstrap.php` | api/ থেকে সঠিক রিডাইরেক্ট পাথ |
| `lib/db.php` | অ্যাটমিক setAgentRunningIfIdle() |
| `lib/demo.php` | isConnected() গার্ড |
| `lib/mailer.php` | HTML এস্কেপিং |
| `login.php` | CSRF যাচাই |

---

## 🏁 পূর্বের অডিট সারসংক্ষেপ

| অডিট | বাগ পাওয়া | স্ট্যাটাস |
|-------|-----------|----------|
| ১ম অডিট | ২৭টি | ✅ সমাধান |
| ২য় অডিট | ১৩টি | ✅ সমাধান |
| ৩য় অডিট | ১৭টি | ✅ সমাধান |
| ৪র্থ অডিট | ১৫টি | ✅ সমাধান |
| **৫ম অডিট** | **১৯টি** | ✅ সমাধান |
| **মোট** | **৯১টি** | **সব সমাধান** |

---

*রিপোর্ট তৈরি: ৫ম অডিট — AI Office ডিপ অডিট*
