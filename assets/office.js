/**
 * assets/office.js — HTML5 Canvas অফিস ভিউ
 * ১০টি ডিপার্টমেন্ট, প্রতিটিতে পিক্সেল-ম্যান
 * এজেন্ট state অনুযায়ী অ্যানিমেশন (working = টাইপিং, idle = হাঁটা)
 */

(function() {
    'use strict';

    // roundRect polyfill for older browsers
    if (!CanvasRenderingContext2D.prototype.roundRect) {
        CanvasRenderingContext2D.prototype.roundRect = function(x, y, w, h, r) {
            if (typeof r === 'number') r = [r, r, r, r];
            const [tl, tr, br, bl] = r;
            this.moveTo(x + tl, y);
            this.lineTo(x + w - tr, y);
            this.quadraticCurveTo(x + w, y, x + w, y + tr);
            this.lineTo(x + w, y + h - br);
            this.quadraticCurveTo(x + w, y + h, x + w - br, y + h);
            this.lineTo(x + bl, y + h);
            this.quadraticCurveTo(x, y + h, x, y + h - bl);
            this.lineTo(x, y + tl);
            this.quadraticCurveTo(x, y, x + tl, y);
            this.closePath();
            return this;
        };
    }

    const canvas = document.getElementById('office-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');

    // হাই-DPI সাপোর্ট
    function resizeCanvas() {
        const rect = canvas.parentElement.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = Math.max(400, rect.width * 0.5) * dpr;
        canvas.style.height = Math.max(400, rect.width * 0.5) + 'px';
        ctx.scale(dpr, dpr);
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // ── এজেন্ট ডেফিনিশন ──────────────────────────────────
    const agentDefs = [
        { id: 'leader',          name: 'লিডার',       emoji: '👔', color: '#2563eb' },
        { id: 'product_import',  name: 'পণ্য ইম্পোর্ট', emoji: '📦', color: '#8b5cf6' },
        { id: 'price',           name: 'দাম',          emoji: '💰', color: '#f59e0b' },
        { id: 'inventory',       name: 'ইনভেন্টরি',   emoji: '📋', color: '#22c55e' },
        { id: 'cart_recovery',   name: 'কার্ট রিকভারি', emoji: '🛒', color: '#ec4899' },
        { id: 'social',          name: 'সোশ্যাল',      emoji: '📱', color: '#06b6d4' },
        { id: 'seo',             name: 'SEO',          emoji: '🔍', color: '#ef4444' },
        { id: 'content',         name: 'কনটেন্ট',      emoji: '📝', color: '#f97316' },
        { id: 'customer_reply',  name: 'কাস্টমার রিপ্লাই', emoji: '💬', color: '#14b8a6' },
        { id: 'order_prep',      name: 'অর্ডার প্রেপ', emoji: '📦', color: '#6366f1' },
    ];

    // এজেন্ট স্টেট
    let agentStates = {};
    agentDefs.forEach(a => {
        agentStates[a.id] = { state: 'idle', lastRun: null, runCount: 0 };
    });

    // ── ডেস্ক লেআউট ────────────────────────────────────────
    // ২ সারি × ৫ কলাম
    function getDesks() {
        const w = canvas.clientWidth;
        const h = canvas.clientHeight;
        const cols = 5;
        const rows = 2;
        const deskW = w / cols;
        const deskH = h / rows;
        const desks = [];
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                desks.push({
                    x: c * deskW + deskW / 2,
                    y: r * deskH + deskH / 2 + 20,
                    w: deskW,
                    h: deskH,
                });
            }
        }
        return desks;
    }

    // ── পিক্সেল ম্যান ক্লাস ─────────────────────────────────
    class PixelMan {
        constructor(agent, deskIdx) {
            this.agent = agent;
            this.deskIdx = deskIdx;
            this.x = 0;
            this.y = 0;
            this.targetX = 0;
            this.targetY = 0;
            this.frame = 0;
            this.typeFrame = 0;
            this.walkDir = Math.random() > 0.5 ? 1 : -1;
            this.walkTimer = Math.random() * 200;
        }

        update(desks, dt) {
            const desk = desks[this.deskIdx];
            const state = agentStates[this.agent.id]?.state || 'idle';

            this.targetX = desk.x;
            this.targetY = desk.y - 10;

            if (state === 'working') {
                // ডেস্কে বসে টাইপিং
                this.x += (this.targetX - this.x) * 0.1;
                this.y += (this.targetY - this.y) * 0.1;
                this.typeFrame += dt * 0.01;
                this.frame = 0;
            } else if (state === 'idle') {
                // ডেস্কের আশেপাশে হাঁটা
                this.walkTimer -= dt;
                if (this.walkTimer <= 0) {
                    this.walkDir *= -1;
                    this.walkTimer = 100 + Math.random() * 200;
                }
                this.x += Math.sin(this.frame * 0.02) * 0.3 * this.walkDir;
                this.y += Math.cos(this.frame * 0.015) * 0.15;
                // ডেস্কের কাছাকাছি থাকুন
                this.x += (this.targetX - this.x) * 0.005;
                this.y += (this.targetY - this.y) * 0.005;
                this.frame += dt * 0.05;
            } else {
                // error — ডেস্কে আটকে
                this.x += (this.targetX - this.x) * 0.05;
                this.y += (this.targetY - this.y) * 0.05;
            }
        }

        draw(ctx) {
            const state = agentStates[this.agent.id]?.state || 'idle';
            const x = this.x;
            const y = this.y;

            // মাথা
            ctx.fillStyle = this.agent.color;
            ctx.beginPath();
            ctx.arc(x, y - 16, 8, 0, Math.PI * 2);
            ctx.fill();

            // চোখ
            ctx.fillStyle = '#fff';
            if (state === 'working') {
                // স্ক্রিনের দিকে তাকানো
                ctx.fillRect(x - 4, y - 18, 2, 2);
                ctx.fillRect(x + 2, y - 18, 2, 2);
            } else {
                ctx.fillRect(x - 4, y - 17, 2, 2);
                ctx.fillRect(x + 2, y - 17, 2, 2);
            }

            // শরীর
            ctx.fillStyle = this.agent.color;
            ctx.fillRect(x - 6, y - 8, 12, 12);

            // টাইপিং অ্যানিমেশন
            if (state === 'working') {
                const typeOffset = Math.sin(this.typeFrame) * 3;
                // বাম হাত
                ctx.fillRect(x - 10, y - 4 + typeOffset, 5, 3);
                // ডান হাত
                ctx.fillRect(x + 5, y - 4 - typeOffset, 5, 3);
            } else {
                // হাঁটার অ্যানিমেশন
                const legOffset = Math.sin(this.frame) * 3;
                ctx.fillRect(x - 4, y + 4, 3, 6 + legOffset);
                ctx.fillRect(x + 1, y + 4, 3, 6 - legOffset);
            }

            // এমোজি লেবেল
            ctx.font = '14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(this.agent.emoji, x, y - 30);

            // নাম
            ctx.font = '10px sans-serif';
            ctx.fillStyle = '#475569';
            ctx.fillText(this.agent.name, x, y + 24);
        }
    }

    // ── ইনিশিয়ালাইজ ────────────────────────────────────────
    const desks = getDesks();
    const men = agentDefs.map((a, i) => new PixelMan(a, i));

    // ── ড্র লুপ ──────────────────────────────────────────────
    let lastTime = performance.now();

    function draw(now) {
        const dt = Math.min(now - lastTime, 50);
        lastTime = now;

        const w = canvas.clientWidth;
        const h = canvas.clientHeight;

        // ক্লিয়ার
        ctx.clearRect(0, 0, w, h);

        // ব্যাকগ্রাউন্ড
        const gradient = ctx.createLinearGradient(0, 0, 0, h);
        gradient.addColorStop(0, '#f0f9ff');
        gradient.addColorStop(1, '#e0f2fe');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, w, h);

        // মেঝে
        ctx.fillStyle = '#cbd5e1';
        ctx.fillRect(0, h * 0.65, w, h * 0.35);

        // গ্রিড লাইন
        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 0.5;
        for (let x = 0; x < w; x += 50) {
            ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, h); ctx.stroke();
        }
        for (let y = 0; y < h; y += 50) {
            ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke();
        }

        // ডেস্ক আঁকুন
        const currentDesks = getDesks();
        currentDesks.forEach((desk, i) => {
            const agent = agentDefs[i];
            const state = agentStates[agent.id]?.state || 'idle';

            // ডেস্ক
            ctx.fillStyle = state === 'working' ? '#fef3c7' : state === 'error' ? '#fef2f2' : '#f8fafc';
            ctx.strokeStyle = agent.color;
            ctx.lineWidth = 2;
            const dx = desk.x - desk.w * 0.35;
            const dy = desk.y - 2;
            const dw = desk.w * 0.7;
            const dh = 20;
            ctx.beginPath();
            ctx.roundRect(dx, dy, dw, dh, 4);
            ctx.fill();
            ctx.stroke();

            // কম্পিউটার স্ক্রিন
            ctx.fillStyle = state === 'working' ? '#22c55e' : '#94a3b8';
            ctx.fillRect(desk.x - 8, desk.y - 8, 16, 6);

            // স্টেট ইন্ডিকেটর
            if (state === 'working') {
                ctx.fillStyle = '#f59e0b';
                ctx.beginPath();
                ctx.arc(desk.x + desk.w * 0.3, desk.y - 12, 4, 0, Math.PI * 2);
                ctx.fill();
                // পালস ইফেক্ট
                ctx.globalAlpha = 0.3 + Math.sin(now * 0.003) * 0.3;
                ctx.fillStyle = '#f59e0b';
                ctx.beginPath();
                ctx.arc(desk.x + desk.w * 0.3, desk.y - 12, 8, 0, Math.PI * 2);
                ctx.fill();
                ctx.globalAlpha = 1;
            } else if (state === 'error') {
                ctx.fillStyle = '#ef4444';
                ctx.beginPath();
                ctx.arc(desk.x + desk.w * 0.3, desk.y - 12, 4, 0, Math.PI * 2);
                ctx.fill();
            }

            // ডিপার্টমেন্ট লেবেল
            ctx.font = 'bold 11px sans-serif';
            ctx.fillStyle = agent.color;
            ctx.textAlign = 'center';
            ctx.fillText(agent.name, desk.x, desk.y + dh + 14);

            // রান কাউন্ট
            const runCount = agentStates[agent.id]?.runCount || 0;
            ctx.font = '9px sans-serif';
            ctx.fillStyle = '#94a3b8';
            ctx.fillText('রান: ' + runCount, desk.x, desk.y + dh + 26);
        });

        // পিক্সেল ম্যান আপডেট ও ড্র
        men.forEach((man, i) => {
            man.update(currentDesks, dt);
            man.draw(ctx);
        });

        // হেডার টেক্সট
        ctx.font = 'bold 14px sans-serif';
        ctx.fillStyle = '#1e293b';
        ctx.textAlign = 'left';
        ctx.fillText('🏢 AI অফিস — লাইভ মনিটর', 16, 22);

        const working = Object.values(agentStates).filter(s => s.state === 'working').length;
        const timeStr = new Date().toLocaleTimeString('bn-BD');
        ctx.font = '11px sans-serif';
        ctx.fillStyle = '#64748b';
        ctx.fillText('সময়: ' + timeStr + ' | কাজ চলছে: ' + working + '/১০', 16, 38);

        requestAnimationFrame(draw);
    }

    requestAnimationFrame(draw);

    // ── স্টেট আপডেট (গ্লোবাল) ─────────────────────────────
    window.updateOfficeStates = function(states) {
        if (!states || !Array.isArray(states)) return;
        states.forEach(s => {
            if (agentStates[s.agent]) {
                agentStates[s.agent] = {
                    state: s.state || 'idle',
                    lastRun: s.last_run,
                    runCount: s.run_count || 0,
                };
            }
        });
    };

})();
