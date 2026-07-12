<?php
/**
 * index.php — মূল ৪-প্যানেল অ্যাপ
 * AI Office — Dropshipping Automation
 */
require_once __DIR__ . '/lib/bootstrap.php';

// লগইন চেক
$auth->requireLogin(false);

$username  = htmlspecialchars($auth->username());
$csrfToken = $auth->csrfToken();
$demoMode  = defined('DEMO_MODE') && DEMO_MODE;
$groqDemo  = $groq->isDemo();
$wooDemo   = $woo->isDemo();
$dbConnected = $db->isConnected();
$license   = checkLicense();
$siteUrl   = defined('SITE_URL') ? SITE_URL : '';
$cronToken = $db->getSetting('cron_token', '');
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏢 AI Office — ড্রপশিপিং অটোমেশন</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <!-- টপবার -->
    <header class="topbar">
        <div class="topbar-left">
            <span class="logo">🏢 AI Office</span>
            <?php if ($demoMode): ?>
                <span class="badge badge-demo">ডেমো মোড</span>
            <?php endif; ?>
            <?php if (!$license['valid']): ?>
                <span class="badge badge-error">লাইসেন্স সমস্যা</span>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <span class="user-name">👤 <?= $username ?></span>
            <button onclick="logout()" class="btn btn-sm btn-outline">লগআউট</button>
        </div>
    </header>

    <!-- ট্যাব নেভিগেশন -->
    <nav class="tabs">
        <button class="tab active" data-tab="dashboard">📊 ড্যাশবোর্ড</button>
        <button class="tab" data-tab="worker">🏢 অফিস</button>
        <button class="tab" data-tab="inbox">📥 ইনবক্স</button>
        <button class="tab" data-tab="settings">⚙️ সেটিংস</button>
    </nav>

    <!-- কনটেন্ট এরিয়া -->
    <main class="content">

        <!-- ═══ ড্যাশবোর্ড ═══ -->
        <section id="panel-dashboard" class="panel active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-value" id="stat-orders">-</div>
                    <div class="stat-label">মোট অর্ডার</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-value" id="stat-pending">-</div>
                    <div class="stat-label">পেন্ডিং অর্ডার</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🛒</div>
                    <div class="stat-value" id="stat-carts">-</div>
                    <div class="stat-label">সক্রিয় কার্ট</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value" id="stat-recovered">-</div>
                    <div class="stat-label">রিকভার্ড কার্ট</div>
                </div>
            </div>

            <!-- এজেন্ট পারফরম্যান্স -->
            <div class="card">
                <h3>🤖 এজেন্ট পারফরম্যান্স</h3>
                <div class="agent-grid" id="agent-perf-grid">
                    <!-- JS দিয়ে পূরণ হবে -->
                </div>
            </div>

            <!-- রিসেন্ট লগ -->
            <div class="card">
                <h3>📋 সাম্প্রতিক লগ</h3>
                <div class="log-list" id="recent-logs">
                    <div class="empty-state">লোড হচ্ছে...</div>
                </div>
            </div>
        </section>

        <!-- ═══ অফিস (Worker) ═══ -->
        <section id="panel-worker" class="panel">
            <div class="office-header">
                <h3>🏢 AI অফিস — লাইভ মনিটর</h3>
                <div class="office-controls">
                    <button onclick="runAgent('leader')" class="btn btn-primary btn-sm">🚀 সব চেক করুন</button>
                </div>
            </div>
            <div class="canvas-container">
                <canvas id="office-canvas" width="1000" height="600"></canvas>
            </div>
            <div class="agent-desks" id="agent-desks">
                <!-- JS দিয়ে পূরণ হবে -->
            </div>
        </section>

        <!-- ═══ ইনবক্স ═══ -->
        <section id="panel-inbox" class="panel">
            <div class="chat-container">
                <div class="chat-messages" id="chat-messages">
                    <div class="empty-state">
                        <div class="empty-icon">💬</div>
                        <p>আপনার AI কোচকে কিছু জিজ্ঞাসা করুন!</p>
                        <p class="text-muted">যেমন: "কিভাবে প্রথম পণ্য ইম্পোর্ট করব?"</p>
                    </div>
                </div>
                <div class="chat-input-area">
                    <input type="text" id="chat-input" placeholder="আপনার মেসেজ লিখুন..."
                           onkeypress="if(event.key==='Enter')sendChat()">
                    <button onclick="sendChat()" class="btn btn-primary">পাঠান ➤</button>
                </div>
            </div>
        </section>

        <!-- ═══ সেটিংস ═══ -->
        <section id="panel-settings" class="panel">
            <div class="settings-grid">
                <!-- বিজনেস সেটিংস -->
                <div class="card">
                    <h3>💼 বিজনেস সেটিংস</h3>
                    <div class="form-group">
                        <label>🏪 স্টোরের নাম</label>
                        <input type="text" id="set-store_name" placeholder="আপনার স্টোরের নাম">
                    </div>
                    <div class="form-group">
                        <label>💰 লাভের মার্জিন (%)</label>
                        <input type="number" id="set-profit_margin" min="0" max="200" step="1">
                    </div>
                    <div class="form-group">
                        <label>💱 কারেন্সি</label>
                        <select id="set-currency">
                            <option value="BDT">BDT (৳)</option>
                            <option value="USD">USD ($)</option>
                        </select>
                    </div>
                </div>

                <!-- BusinessKoro সেটিংস -->
                <div class="card">
                    <h3>🏢 BusinessKoro সেটিংস</h3>
                    <div class="form-group">
                        <label>🆔 BK ID</label>
                        <input type="text" id="set-bk_id" placeholder="আপনার BusinessKoro ID">
                    </div>
                    <div class="form-group">
                        <label>📱 BK ফোন</label>
                        <input type="text" id="set-bk_phone" placeholder="BusinessKoro রেজিস্ট্রেশন ফোন">
                    </div>
                </div>

                <!-- কার্ট রিকভারি সেটিংস -->
                <div class="card">
                    <h3>🛒 কার্ট রিকভারি</h3>
                    <div class="form-group">
                        <label>📧 ধাপ ১ (ঘন্টা)</label>
                        <input type="number" id="set-cart_step1_hours" min="0" max="168" value="1">
                    </div>
                    <div class="form-group">
                        <label>📧 ধাপ ২ (ঘন্টা)</label>
                        <input type="number" id="set-cart_step2_hours" min="0" max="168" value="24">
                    </div>
                    <div class="form-group">
                        <label>📧 ধাপ ৩ (ঘন্টা)</label>
                        <input type="number" id="set-cart_step3_hours" min="0" max="336" value="72">
                    </div>
                    <div class="smtp-status" id="smtp-status">চেক হচ্ছে...</div>
                </div>

                <!-- সোশ্যাল ও SEO সেটিংস -->
                <div class="card">
                    <h3>📱 সোশ্যাল ও SEO</h3>
                    <div class="form-group">
                        <label>🌐 প্ল্যাটফর্ম</label>
                        <input type="text" id="set-social_platforms" placeholder="facebook,instagram">
                    </div>
                    <div class="form-group">
                        <label>🎯 SEO টার্গেট স্কোর</label>
                        <input type="number" id="set-seo_target_score" min="0" max="100" value="80">
                    </div>
                </div>

                <!-- এজেন্ট রানার -->
                <div class="card">
                    <h3>🤖 এজেন্ট রানার</h3>
                    <p class="text-muted">যেকোনো এজেন্ট ম্যানুয়ালি চালান:</p>
                    <div class="agent-runner-grid">
                        <button onclick="toggleAgentForm('product_import')" class="btn btn-outline btn-sm">📦 পণ্য ইম্পোর্ট</button>
                        <button onclick="runAgent('price')" class="btn btn-outline btn-sm">💰 দাম</button>
                        <button onclick="runAgent('inventory')" class="btn btn-outline btn-sm">📋 ইনভেন্টরি</button>
                        <button onclick="toggleAgentForm('cart_recovery')" class="btn btn-outline btn-sm">🛒 কার্ট রিকভারি</button>
                        <button onclick="toggleAgentForm('social')" class="btn btn-outline btn-sm">📱 সোশ্যাল</button>
                        <button onclick="toggleAgentForm('seo')" class="btn btn-outline btn-sm">🔍 SEO</button>
                        <button onclick="toggleAgentForm('content')" class="btn btn-outline btn-sm">📝 কনটেন্ট</button>
                        <button onclick="toggleAgentForm('customer_reply')" class="btn btn-outline btn-sm">💬 কাস্টমার রিপ্লাই</button>
                        <button onclick="runAgent('order_prep')" class="btn btn-outline btn-sm">📦 অর্ডার প্রেপ</button>
                    </div>

                    <!-- পণ্য ইম্পোর্ট ফর্ম -->
                    <div class="agent-form" id="form-product_import" style="display:none;">
                        <h4>📦 পণ্য ইম্পোর্ট</h4>
                        <div class="form-group">
                            <label>পণ্যের নাম *</label>
                            <input type="text" id="inp-pi-name" placeholder="যেমন: ওয়্যারলেস ইয়ারবাড">
                        </div>
                        <div class="form-group">
                            <label>হোলসেল মূল্য (৳) *</label>
                            <input type="number" id="inp-pi-wholesale" placeholder="500">
                        </div>
                        <div class="form-group">
                            <label>ক্যাটেগরি</label>
                            <input type="text" id="inp-pi-category" placeholder="ইলেকট্রনিক্স">
                        </div>
                        <div class="form-group">
                            <label>ছবির URL</label>
                            <input type="url" id="inp-pi-image" placeholder="https://...">
                        </div>
                        <button onclick="runProductImport()" class="btn btn-primary btn-sm">ইম্পোর্ট করুন</button>
                    </div>

                    <!-- কার্ট রিকভারি ফর্ম -->
                    <div class="agent-form" id="form-cart_recovery" style="display:none;">
                        <h4>🛒 কার্ট রিকভারি</h4>
                        <div class="form-group">
                            <label>গ্রাহকের ইমেইল *</label>
                            <input type="email" id="inp-cr-email" placeholder="customer@example.com">
                        </div>
                        <div class="form-group">
                            <label>গ্রাহকের নাম</label>
                            <input type="text" id="inp-cr-name" placeholder="রহিম">
                        </div>
                        <button onclick="runCartRecovery()" class="btn btn-primary btn-sm">কার্ট যোগ করুন</button>
                        <button onclick="runAgent('cart_recovery',{action:'send_pending'})" class="btn btn-outline btn-sm">পেন্ডিং ইমেইল পাঠান</button>
                    </div>

                    <!-- সোশ্যাল ফর্ম -->
                    <div class="agent-form" id="form-social" style="display:none;">
                        <h4>📱 সোশ্যাল পোস্ট</h4>
                        <div class="form-group">
                            <label>পণ্যের নাম</label>
                            <input type="text" id="inp-social-product" placeholder="আমার পণ্য">
                        </div>
                        <div class="form-group">
                            <label>দাম (৳)</label>
                            <input type="number" id="inp-social-price" placeholder="1200">
                        </div>
                        <button onclick="runSocial()" class="btn btn-primary btn-sm">পোস্ট তৈরি করুন</button>
                    </div>

                    <!-- SEO ফর্ম -->
                    <div class="agent-form" id="form-seo" style="display:none;">
                        <h4>🔍 SEO অডিট</h4>
                        <div class="form-group">
                            <label>অডিট করার URL</label>
                            <input type="url" id="inp-seo-url" placeholder="https://yourstore.com">
                        </div>
                        <button onclick="runSeo()" class="btn btn-primary btn-sm">অডিট চালান</button>
                    </div>

                    <!-- কনটেন্ট ফর্ম -->
                    <div class="agent-form" id="form-content" style="display:none;">
                        <h4>📝 কনটেন্ট</h4>
                        <div class="form-group">
                            <label>বিষয়</label>
                            <input type="text" id="inp-content-topic" placeholder="পণ্য পরিচিতি">
                        </div>
                        <div class="form-group">
                            <label>ধরন</label>
                            <select id="inp-content-type">
                                <option value="video">ভিডিও স্ক্রিপ্ট</option>
                                <option value="blog">ব্লগ পোস্ট</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>পণ্যের নাম</label>
                            <input type="text" id="inp-content-product" placeholder="">
                        </div>
                        <button onclick="runContent()" class="btn btn-primary btn-sm">কনটেন্ট তৈরি</button>
                    </div>

                    <!-- কাস্টমার রিপ্লাই ফর্ম -->
                    <div class="agent-form" id="form-customer_reply" style="display:none;">
                        <h4>💬 কাস্টমার রিপ্লাই</h4>
                        <div class="form-group">
                            <label>কাস্টমারের মেসেজ *</label>
                            <textarea id="inp-cr-message" rows="3" placeholder="কাস্টমার কি লিখেছে?"></textarea>
                        </div>
                        <div class="form-group">
                            <label>কাস্টমারের নাম</label>
                            <input type="text" id="inp-cr-cust-name" placeholder="">
                        </div>
                        <div class="form-group">
                            <label>অর্ডার ID</label>
                            <input type="text" id="inp-cr-order-id" placeholder="">
                        </div>
                        <button onclick="runCustomerReply()" class="btn btn-primary btn-sm">রিপ্লাই ড্রাফট</button>
                    </div>
                </div>

                <!-- BusinessKoro ক্যাটালগ ইম্পোর্ট -->
                <div class="card">
                    <h3>📥 ক্যাটালগ ইম্পোর্ট</h3>
                    <p class="text-muted">BusinessKoro থেকে CSV/JSON এক্সপোর্ট করে আপলোড করুন:</p>
                    <div class="form-group">
                        <label>📂 ফাইল আপলোড (CSV বা JSON)</label>
                        <input type="file" id="catalog-file" accept=".csv,.json" class="file-input">
                    </div>
                    <button onclick="importCatalog()" class="btn btn-primary btn-sm">ইম্পোর্ট করুন</button>
                    <div id="import-result" class="import-result"></div>
                    <details class="help-section">
                        <summary>📋 CSV ফরম্যাট দেখুন</summary>
                        <pre class="code-block">name,wholesale_price,category,image_url,sku
ওয়্যারলেস ইয়ারবাড,500,ইলেকট্রনিক্স,https://...,WB-001
স্মার্ট ওয়াচ,1200,ইলেকট্রনিক্স,https://...,SW-002</pre>
                    </details>
                </div>

                <!-- পাসওয়ার্ড পরিবর্তন -->
                <div class="card">
                    <h3>🔐 পাসওয়ার্ড পরিবর্তন</h3>
                    <div class="form-group">
                        <label>পুরানো পাসওয়ার্ড</label>
                        <input type="password" id="old-pass" placeholder="বর্তমান পাসওয়ার্ড">
                    </div>
                    <div class="form-group">
                        <label>নতুন পাসওয়ার্ড</label>
                        <input type="password" id="new-pass" placeholder="নতুন পাসওয়ার্ড (৬+ অক্ষর)">
                    </div>
                    <button onclick="changePassword()" class="btn btn-primary btn-sm">পরিবর্তন করুন</button>
                </div>

                <!-- ক্রন সেটআপ -->
                <div class="card">
                    <h3>⏰ ক্রন জব সেটআপ</h3>
                    <p class="text-muted">স্বয়ংক্রিয়ভাবে Price, Inventory, Cart Recovery চালাতে ক্রন সেটআপ করুন:</p>
                    <div class="form-group">
                        <label>ক্রন URL:</label>
                        <div class="code-block selectable" id="cron-url">
                            <?= htmlspecialchars($siteUrl) ?>/api/cron.php?token=<?= htmlspecialchars($cronToken) ?>&job=all
                        </div>
                    </div>
                    <p class="text-muted">cPanel ক্রন কমান্ড:</p>
                    <div class="code-block selectable">0 */6 * * * curl "<?= htmlspecialchars($siteUrl) ?>/api/cron.php?token=<?= htmlspecialchars($cronToken) ?>&job=all"</div>
                    <p class="text-muted">বা <a href="https://cron-job.org" target="_blank" rel="noopener">cron-job.org</a> থেকে ফ্রি সেটআপ করুন।</p>
                    <button onclick="testCron()" class="btn btn-outline btn-sm">🧪 ক্রন টেস্ট করুন</button>
                </div>

                <!-- কানেকশন স্ট্যাটাস -->
                <div class="card">
                    <h3>🔌 কানেকশন স্ট্যাটাস</h3>
                    <div class="connection-status">
                        <div class="conn-item">
                            <span class="conn-dot <?= $groqDemo ? 'conn-off' : 'conn-on' ?>"></span>
                            Groq AI: <?= $groqDemo ? 'ডেমো (কি নেই)' : 'সক্রিয় ✅' ?>
                        </div>
                        <div class="conn-item">
                            <span class="conn-dot <?= $wooDemo ? 'conn-off' : 'conn-on' ?>"></span>
                            WooCommerce: <?= $wooDemo ? 'ডেমো (কি নেই)' : 'সক্রিয় ✅' ?>
                        </div>
                        <div class="conn-item">
                            <span class="conn-dot <?= $dbConnected ? 'conn-on' : 'conn-off' ?>"></span>
                            ডাটাবেস: <?= $dbConnected ? 'সক্রিয় ✅' : 'বিচ্ছিন্ন ❌' ?>
                        </div>
                    </div>
                </div>

                <!-- সেভ বাটন -->
                <div class="card save-bar">
                    <button onclick="saveSettings()" class="btn btn-primary btn-block">💾 সেটিংস সেভ করুন</button>
                </div>
            </div>
        </section>
    </main>

    <!-- টোস্ট কন্টেইনার -->
    <div id="toast-container" class="toast-container"></div>

    <!-- এজেন্ট আউটপুট মডাল -->
    <div id="agent-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">ফলাফল</h3>
                <button onclick="closeModal()" class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modal-body"></div>
            <div class="modal-footer">
                <button onclick="copyModalContent()" class="btn btn-outline btn-sm">📋 কপি</button>
                <button onclick="closeModal()" class="btn btn-primary btn-sm">বন্ধ করুন</button>
            </div>
        </div>
    </div>

    <!-- হিডেন ডাটা -->
    <input type="hidden" id="csrf-token" value="<?= $csrfToken ?>">

    <script src="assets/office.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>
