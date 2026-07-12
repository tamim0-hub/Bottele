/**
 * assets/app.js — মূল অ্যাপ লজিক
 * নেভিগেশন, ড্যাশবোর্ড, চ্যাট, সেটিংস, এজেন্ট রানার
 */

(function() {
    'use strict';

    // ── কনস্ট্যান্ট ──────────────────────────────────────
    const POLL_INTERVAL = 5000; // ৫ সেকেন্ড
    const TOAST_DURATION = 4000;

    // ── স্টেট ──────────────────────────────────────────────
    let currentTab = 'dashboard';
    let chatLoading = false;
    let agentRunning = {};

    // ── ইনিশিয়ালাইজ ──────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        initTabs();
        loadState();
        loadSettings();

        // পোলিং — প্রতি ৫ সেকেন্ডে স্টেট রিফ্রেশ
        setInterval(loadState, POLL_INTERVAL);
    });

    // ── ট্যাব নেভিগেশন ───────────────────────────────────
    function initTabs() {
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                switchTab(this.dataset.tab);
            });
        });
    }

    function switchTab(tabName) {
        currentTab = tabName;

        // ট্যাব বাটন আপডেট
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`.tab[data-tab="${tabName}"]`)?.classList.add('active');

        // প্যানেল আপডেট
        document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
        document.getElementById(`panel-${tabName}`)?.classList.add('active');
    }

    // গ্লোবাল (অফিস ক্যানভাস থেকে কল হয়)
    window.switchTab = switchTab;

    // ── এজেন্ট ফর্ম টগল ────────────────────────────────────
    window.toggleAgentForm = function(agent) {
        const formId = 'form-' + agent;
        const formEl = document.getElementById(formId);
        if (!formEl) {
            // ফর্ম নেই — সরাসরি রান করুন
            runAgent(agent);
            return;
        }
        // অন্য সব ফর্ম লুকান
        document.querySelectorAll('.agent-form').forEach(f => {
            if (f.id !== formId) f.style.display = 'none';
        });
        // এই ফর্ম টগল
        formEl.style.display = formEl.style.display === 'none' ? 'block' : 'none';
    };

    // ── স্টেট লোড ─────────────────────────────────────────
    async function loadState() {
        try {
            const res = await fetch('api/state.php');
            if (res.status === 401) { window.location.href = 'login.php'; return; }
            const data = await res.json();

            if (!data.success) return;

            updateDashboard(data);
            if (window.updateOfficeStates) window.updateOfficeStates(data.states);
        } catch (e) {
            // সাইলেন্ট — নেটওয়ার্ক সমস্যা হতে পারে
        }
    }

    // এজেন্ট ম্যাপিং (ডুপ্লিকেট এড়াতে একবার ডিফাইন)
    const AGENT_EMOJI = {
        leader: '👔', product_import: '📦', price: '💰', inventory: '📋',
        cart_recovery: '🛒', social: '📱', seo: '🔍', content: '📝',
        customer_reply: '💬', order_prep: '📦'
    };
    const AGENT_NAME = {
        leader: 'লিডার', product_import: 'পণ্য ইম্পোর্ট', price: 'দাম', inventory: 'ইনভেন্টরি',
        cart_recovery: 'কার্ট রিকভারি', social: 'সোশ্যাল', seo: 'SEO', content: 'কনটেন্ট',
        customer_reply: 'কাস্টমার রিপ্লাই', order_prep: 'অর্ডার প্রেপ'
    };

    // ── ড্যাশবোর্ড আপডেট ─────────────────────────────────
    function updateDashboard(data) {
        // স্ট্যাট কার্ড
        const stats = data.stats || {};
        document.getElementById('stat-orders').textContent = stats.total_orders ?? '-';
        document.getElementById('stat-pending').textContent = stats.pending_orders ?? '-';
        document.getElementById('stat-carts').textContent = stats.active_carts ?? '-';
        document.getElementById('stat-recovered').textContent = stats.recovered_carts ?? '-';

        // এজেন্ট পারফরম্যান্স গ্রিড
        const agentGrid = document.getElementById('agent-perf-grid');
        if (agentGrid && data.states) {
            agentGrid.innerHTML = data.states.map(s => {
                const stateClass = s.state === 'working' ? 'state-working' : s.state === 'error' ? 'state-error' : 'state-idle';
                const stateLabel = s.state === 'working' ? '🔄 কাজ চলছে' : s.state === 'error' ? '❌ ত্রুটি' : '✅ আইডল';
                const safeAgent = escapeHtml(s.agent);
                return '<div class="agent-card ' + stateClass + '">' +
                    '<div class="agent-icon">' + (AGENT_EMOJI[s.agent] || '🤖') + '</div>' +
                    '<div class="agent-info">' +
                    '<div class="agent-name">' + (AGENT_NAME[s.agent] || safeAgent) + '</div>' +
                    '<div class="agent-meta">' + stateLabel + ' • রান: ' + (s.run_count || 0) + '</div>' +
                    '</div></div>';
            }).join('');
        }

        // রিসেন্ট লগ
        const logList = document.getElementById('recent-logs');
        if (logList && data.logs) {
            if (data.logs.length === 0) {
                logList.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>কোনো লগ নেই। এজেন্ট চালান!</p></div>';
            } else {
                logList.innerHTML = data.logs.map(l => {
                    const time = l.created_at ? new Date(l.created_at).toLocaleString('bn-BD') : '';
                    return '<div class="log-item">' +
                        '<span class="log-time">' + time + '</span>' +
                        '<span class="log-agent">' + escapeHtml(l.agent) + '</span>' +
                        '<span class="log-msg">' + escapeHtml(l.input_summary || '') + '</span>' +
                        '<span class="log-status ' + escapeHtml(l.status) + '">' + (l.status === 'success' ? '✅' : '❌') + '</span>' +
                        '</div>';
                }).join('');
            }
        }

        // অফিস ডেস্ক আপডেট
        const desksEl = document.getElementById('agent-desks');
        if (desksEl && data.states) {
            desksEl.innerHTML = data.states.map(s => {
                const cls = s.state === 'working' ? 'working' : s.state === 'error' ? 'error' : '';
                const statusLabel = s.state === 'working' ? '🔄 কাজ চলছে' : s.state === 'error' ? '❌ ত্রুটি' : '✅ আইডল';
                const lastRun = s.last_run ? timeAgo(s.last_run) : 'কখনো না';
                // সুরক্ষিত onclick — escapeHtml দিয়ে agent name সেনিটাইজ
                const safeAgent = escapeHtml(s.agent);
                return '<div class="desk-card ' + cls + '" data-agent="' + safeAgent + '">' +
                    '<div class="desk-emoji">' + (AGENT_EMOJI[s.agent] || '🤖') + '</div>' +
                    '<div class="desk-name">' + (AGENT_NAME[s.agent] || safeAgent) + '</div>' +
                    '<div class="desk-status">' + statusLabel + '</div>' +
                    '<div class="desk-last-run">শেষ: ' + lastRun + '</div>' +
                    '</div>';
            }).join('');

            // desk কার্ডে click event (onclick attribute এর বদলে — XSS-safe)
            desksEl.querySelectorAll('.desk-card[data-agent]').forEach(card => {
                card.addEventListener('click', function() {
                    runAgent(this.dataset.agent);
                });
            });
        }
    }

    // ── এজেন্ট রানার ──────────────────────────────────────
    window.runAgent = async function(agent, input = {}) {
        if (agentRunning[agent]) {
            showToast('এই এজেন্ট ইতিমধ্যে চলছে...', 'warning');
            return;
        }

        agentRunning[agent] = true;
        showToast(`${agent} এজেন্ট চলছে...`, 'info');

        try {
            const res = await fetch('api/agent.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: JSON.stringify({ agent, input }),
            });

            if (res.status === 401) { window.location.href = 'login.php'; return; }

            const data = await res.json();

            if (data.success) {
                showModal(`${agent} ফলাফল`, data.output);
                loadState(); // স্টেট রিফ্রেশ
            } else {
                showToast(data.error || 'এজেন্ট চালাতে সমস্যা হয়েছে।', 'error');
            }
        } catch (e) {
            showToast('নেটওয়ার্ক ত্রুটি: ' + e.message, 'error');
        } finally {
            agentRunning[agent] = false;
        }
    };

    // স্পেসিফিক এজেন্ট রানার
    window.runProductImport = function() {
        const name = document.getElementById('inp-pi-name')?.value;
        const wholesale = parseFloat(document.getElementById('inp-pi-wholesale')?.value || 0);
        const category = document.getElementById('inp-pi-category')?.value || '';
        const image = document.getElementById('inp-pi-image')?.value || '';
        if (!name) { showToast('পণ্যের নাম দিন!', 'error'); return; }
        runAgent('product_import', { name, wholesale, category, image_url: image });
    };

    window.runCartRecovery = function() {
        const email = document.getElementById('inp-cr-email')?.value;
        const name = document.getElementById('inp-cr-name')?.value || '';
        if (!email) { showToast('ইমেইল দিন!', 'error'); return; }
        runAgent('cart_recovery', { action: 'add', email, name, cart_data: '{}' });
    };

    window.runSocial = function() {
        const product = document.getElementById('inp-social-product')?.value || '';
        const price = document.getElementById('inp-social-price')?.value || '';
        runAgent('social', { product_name: product, price });
    };

    window.runSeo = function() {
        const url = document.getElementById('inp-seo-url')?.value || '';
        runAgent('seo', { url });
    };

    window.runContent = function() {
        const topic = document.getElementById('inp-content-topic')?.value || 'পণ্য পরিচিতি';
        const type = document.getElementById('inp-content-type')?.value || 'video';
        const product = document.getElementById('inp-content-product')?.value || '';
        runAgent('content', { topic, type, product_name: product });
    };

    window.runCustomerReply = function() {
        const message = document.getElementById('inp-cr-message')?.value;
        const name = document.getElementById('inp-cr-cust-name')?.value || '';
        const orderId = document.getElementById('inp-cr-order-id')?.value || '';
        if (!message) { showToast('কাস্টমারের মেসেজ দিন!', 'error'); return; }
        runAgent('customer_reply', { message, customer_name: name, order_id: orderId });
    };

    // ── চ্যাট ──────────────────────────────────────────────
    window.sendChat = async function() {
        const input = document.getElementById('chat-input');
        const message = input?.value?.trim();
        if (!message || chatLoading) return;

        chatLoading = true;
        input.value = '';

        // ইউজার মেসেজ দেখান
        appendChatMsg('user', message);

        try {
            const res = await fetch('api/chat.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: JSON.stringify({ message }),
            });

            if (res.status === 401) { window.location.href = 'login.php'; return; }

            const data = await res.json();
            if (data.success) {
                appendChatMsg('assistant', data.reply);
            } else {
                appendChatMsg('assistant', '❌ ত্রুটি: ' + (data.error || 'উত্তর পাওয়া যায়নি'));
            }
        } catch (e) {
            appendChatMsg('assistant', '❌ নেটওয়ার্ক ত্রুটি');
        } finally {
            chatLoading = false;
        }
    };

    function appendChatMsg(role, content) {
        const container = document.getElementById('chat-messages');
        if (!container) return;

        // empty state সরান
        const empty = container.querySelector('.empty-state');
        if (empty) empty.remove();

        const div = document.createElement('div');
        div.className = 'chat-msg ' + role;
        div.textContent = content;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    // ── সেটিংস ─────────────────────────────────────────────
    async function loadSettings() {
        try {
            const res = await fetch('api/settings.php');
            if (res.status === 401) { window.location.href = 'login.php'; return; }
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success) return;

            const settings = data.settings || {};
            const keys = [
                'store_name', 'profit_margin', 'currency', 'bk_id', 'bk_phone',
                'cart_step1_hours', 'cart_step2_hours', 'cart_step3_hours',
                'social_platforms', 'seo_target_score'
            ];

            keys.forEach(key => {
                const el = document.getElementById('set-' + key);
                if (el && settings[key] !== undefined) {
                    el.value = settings[key];
                }
            });
        } catch (e) {
            // সাইলেন্ট
        }
    }

    window.saveSettings = async function() {
        const keys = [
            'store_name', 'profit_margin', 'currency', 'bk_id', 'bk_phone',
            'cart_step1_hours', 'cart_step2_hours', 'cart_step3_hours',
            'social_platforms', 'seo_target_score'
        ];

        const data = {};
        keys.forEach(key => {
            const el = document.getElementById('set-' + key);
            if (el) data[key] = el.value;
        });

        try {
            const res = await fetch('api/settings.php', {
                method: 'POST',
                headers: csrfHeaders(),
                body: JSON.stringify(data),
            });

            const result = await res.json();
            if (result.success) {
                showToast('✅ সেটিংস সেভ হয়েছে!', 'success');
            } else {
                showToast('❌ সেভ ব্যর্থ: ' + (result.error || ''), 'error');
            }
        } catch (e) {
            showToast('নেটওয়ার্ক ত্রুটি', 'error');
        }
    };

    // ── পাসওয়ার্ড পরিবর্তন ────────────────────────────────
    window.changePassword = async function() {
        const oldPass = document.getElementById('old-pass')?.value;
        const newPass = document.getElementById('new-pass')?.value;
        if (!oldPass || !newPass) { showToast('উভয় ফিল্ড পূরণ করুন।', 'error'); return; }

        try {
            const res = await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=change_password&csrf_token=' + encodeURIComponent(getCsrfToken()) + '&old_password=' + encodeURIComponent(oldPass) + '&new_password=' + encodeURIComponent(newPass),
            });

            const data = await res.json();
            if (data.success) {
                showToast('✅ পাসওয়ার্ড পরিবর্তন হয়েছে!', 'success');
                document.getElementById('old-pass').value = '';
                document.getElementById('new-pass').value = '';
            } else {
                showToast('❌ ' + (data.error || 'পরিবর্তন ব্যর্থ'), 'error');
            }
        } catch (e) {
            showToast('নেটওয়ার্ক ত্রুটি', 'error');
        }
    };

    // ── ক্যাটালগ ইম্পোর্ট ──────────────────────────────────
    window.importCatalog = async function() {
        const fileInput = document.getElementById('catalog-file');
        const resultEl = document.getElementById('import-result');
        if (!fileInput?.files?.length) {
            showToast('ফাইল সিলেক্ট করুন!', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('catalog', fileInput.files[0]);
        formData.append('csrf_token', getCsrfToken());

        resultEl.innerHTML = '⏳ ইম্পোর্ট চলছে...';

        try {
            const res = await fetch('api/import.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                resultEl.innerHTML = `✅ ইম্পোর্ট সম্পন্ন!<br>মোট: ${data.total}, সফল: ${data.imported}, স্কিপ: ${data.skipped}, ত্রুটি: ${data.errors}`;
                showToast('ক্যাটালগ ইম্পোর্ট সম্পন্ন!', 'success');
            } else {
                resultEl.innerHTML = '❌ ' + (data.error || 'ইম্পোর্ট ব্যর্থ');
            }
        } catch (e) {
            resultEl.innerHTML = '❌ নেটওয়ার্ক ত্রুটি';
        }
    };

    // ── ক্রন টেস্ট ──────────────────────────────────────────
    window.testCron = async function() {
        showToast('ক্রন টেস্ট চলছে...', 'info');
        try {
            const cronUrl = document.getElementById('cron-url')?.textContent?.trim();
            if (!cronUrl) { showToast('ক্রন URL পাওয়া যায়নি।', 'error'); return; }
            const res = await fetch(cronUrl);
            const data = await res.json();
            if (data.success) {
                showToast('✅ ক্রন টেস্ট সফল!', 'success');
            } else {
                showToast('❌ ' + (data.error || 'ক্রন ত্রুটি'), 'error');
            }
        } catch (e) {
            showToast('ক্রন URL সরাসরি ব্রাউজার থেকে টেস্ট করা যাবে না (CORS)। cPanel/curl দিয়ে টেস্ট করুন।', 'warning');
        }
    };

    // ── লগআউট ──────────────────────────────────────────────
    window.logout = async function() {
        try {
            await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=logout',
            });
        } catch (e) {}
        window.location.href = 'login.php';
    };

    // ── মডাল ────────────────────────────────────────────────
    window.showModal = function(title, body) {
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-body').textContent = body;
        document.getElementById('agent-modal').style.display = 'flex';
    };

    window.closeModal = function() {
        document.getElementById('agent-modal').style.display = 'none';
    };

    window.copyModalContent = function() {
        const body = document.getElementById('modal-body').textContent;
        navigator.clipboard.writeText(body).then(() => {
            showToast('📋 কপি হয়েছে!', 'success');
        }).catch(() => {
            showToast('কপি করতে সমস্যা। ম্যানুয়ালি সিলেক্ট করুন।', 'error');
        });
    };

    // ── টোস্ট ──────────────────────────────────────────────
    window.showToast = function(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, TOAST_DURATION);
    };

    // ── হেল্পার ────────────────────────────────────────────
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getCsrfToken() {
        return document.getElementById('csrf-token')?.value || '';
    }

    function csrfHeaders() {
        return { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() };
    }

    function timeAgo(dateStr) {
        const now = Date.now();
        const then = new Date(dateStr).getTime();
        const diff = Math.max(0, now - then);

        if (diff < 60000) return 'এইমাত্র';
        if (diff < 3600000) return Math.floor(diff / 60000) + ' মিনিট আগে';
        if (diff < 86400000) return Math.floor(diff / 3600000) + ' ঘন্টা আগে';
        return Math.floor(diff / 86400000) + ' দিন আগে';
    }

})();
