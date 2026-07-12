# AI Office — Master Plan

## 🏢 পণ্যের সারসংক্ষেপ
AI Office হলো বাংলাদেশের ড্রপশিপিং এন্ট্রেপ্রেনারদের জন্য তৈরি একটি সম্পূর্ণ অটোমেশন টুল। BusinessKoro.com প্ল্যাটফর্মের সাথে কাজ করে, AI (Groq) ব্যবহার করে পণ্য বিবরণ, প্রাইসিং, সোশ্যাল পোস্ট, SEO, কাস্টমার রিপ্লাই ইত্যাদি অটোমেট করে।

## 🎯 টার্গেট ইউজার
- বাংলাদেশের ছোট এন্ট্রেপ্রেনার
- বাজেট সীমিত (VPS/পেইড টুল কিনতে পারে না)
- দিনে ২-৩ ঘন্টা সময় দিতে পারে
- BusinessKoro.com দিয়ে রিসেল করে

## 🏗️ আর্কিটেকচার
- **ব্যাকএন্ড**: PHP 8.x (কোনো ফ্রেমওয়ার্ক নেই)
- **ডাটাবেস**: MySQL (PDO)
- **ফ্রন্টএন্ড**: Vanilla HTML/CSS/JS
- **AI**: Groq (ফ্রি টায়ার)
- **ই-কমার্স**: WooCommerce REST API
- **ইমেইল**: PHP mail() / SMTP (socket-based)

## 📂 ফাইল স্ট্রাকচার
```
├── index.php          — ৪-প্যানেল মূল অ্যাপ
├── login.php          — লগইন পেজ
├── install.php        — ওয়েব ইনস্টলার
├── config.sample.php  — কনফিগ টেমপ্লেট
├── setup.sql          — ডাটাবেস স্কিমা
├── lib/
│   ├── bootstrap.php  — অ্যাপ বুটস্ট্র্যাপ
│   ├── db.php         — ডাটাবেস ক্লাস
│   ├── auth.php       — অথেনটিকেশন
│   ├── groq.php       — Groq AI ক্লায়েন্ট
│   ├── woo.php        — WooCommerce API
│   ├── mailer.php     — ইমেইল পাঠানো
│   ├── agents.php     — ১০টি এজেন্ট
│   ├── cron.php       — ক্রন জব ম্যানেজার
│   └── demo.php       — ডেমো মোড সিড ডাটা
├── api/
│   ├── agent.php      — এজেন্ট API
│   ├── auth.php       — অথ API
│   ├── chat.php       — চ্যাট API
│   ├── state.php      — স্টেট API
│   ├── settings.php   — সেটিংস API
│   ├── cron.php       — ক্রন API
│   └── import.php     — ক্যাটালগ ইম্পোর্ট API
├── assets/
│   ├── style.css      — সম্পূর্ণ স্টাইল
│   ├── office.js      — Canvas অফিস ভিউ
│   └── app.js         — মূল অ্যাপ লজিক
├── .gitignore
├── README.md
└── MASTER_PLAN.md
```

## 🤖 ১০টি এজেন্ট
| # | এজেন্ট | কাজ |
|---|--------|------|
| 1 | Leader | অর্কেস্ট্রেশন, রিপোর্ট, পরামর্শ |
| 2 | Product Import | BusinessKoro info → Groq → WooCommerce |
| 3 | Price | মার্জিন-ভিত্তিক রিপ্রাইস |
| 4 | Inventory | Out-of-stock পণ্য unpublish |
| 5 | Cart Recovery | ৩-ধাপ বাংলা ইমেইল |
| 6 | Social | Facebook/Instagram পোস্ট |
| 7 | SEO | রিয়েল ক্রল + অডিট + স্কোর |
| 8 | Content | ভিডিও/ব্লগ স্ক্রিপ্ট |
| 9 | Customer Reply | রিপ্লাই ড্রাফট |
| 10 | Order Prep | অর্ডার ফরম্যাট (BusinessKoro কপি-পেস্ট) |

## 🔒 নিরাপত্তা
- সেশন-ভিত্তিক অথেনটিকেশন
- bcrypt পাসওয়ার্ড হ্যাশিং
- CSRF টোকেন
- সব সিক্রেট server-side (config.php)
- SQL injection প্রতিরোধ (PDO prepared statements)
- ক্রন টোকেন ভেরিফিকেশন

## 💰 লাইসেন্সিং হুক
- config.php-তে LICENSE_KEY
- checkLicense() ফাংশন bootstrap.php-তে
- রিসেলারের লাইসেন্স সার্ভার API-তে কানেক্ট করা যাবে
