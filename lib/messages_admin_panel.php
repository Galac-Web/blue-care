<?php
declare(strict_types=1);

require_once __DIR__ . '/messages_store.php';
require_once __DIR__ . '/admin_section_nav.php';

function blu_messages_api_url(): string
{
    $base = function_exists('blu_admin_web_base') ? blu_admin_web_base() : '../';
    return rtrim($base, '/') . '/api/admin-messages.php';
}

function blu_render_messages_admin_panel(): void
{
    blu_render_admin_section_nav('automatizare', 'messages');
    $api = blu_messages_api_url();
    $unread = blu_messages_unread_count();
    ?>
    <div class="msg-panel">
        <div class="msg-toolbar">
            <div>
                <h4 class="mb-1">Mesagerie</h4>
                <p class="text-muted small mb-0">Conversații clienți — WhatsApp, OLX, website, manual.</p>
            </div>
            <div class="msg-toolbar__actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="msgImportLeads">
                    <i class="fa-solid fa-file-import" aria-hidden="true"></i> Import cereri
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="msgNewConversation">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Conversație nouă
                </button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-4">
                <div class="msg-card msg-card--list">
                    <div class="msg-card__head">
                        <span class="fw-semibold">Conversații</span>
                        <?php if ($unread > 0): ?>
                            <span class="badge bg-danger"><?= (int) $unread ?> necitite</span>
                        <?php endif; ?>
                    </div>
                    <div class="msg-search-wrap">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="msgSearch" class="form-control form-control-sm" placeholder="Caută client..." autocomplete="off">
                    </div>
                    <div id="msgConversations" class="msg-conversations"></div>
                    <div id="msgPagination" class="msg-pagination"></div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="msg-card msg-card--thread">
                    <div class="msg-thread-head">
                        <div class="msg-thread-avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></div>
                        <div class="msg-thread-meta">
                            <div id="msgActiveName" class="fw-semibold">Selectează o conversație</div>
                            <div id="msgActiveStatus" class="text-muted small">—</div>
                        </div>
                    </div>
                    <div id="msgThread" class="msg-thread"></div>
                    <form id="msgReplyForm" class="msg-reply">
                        <input type="hidden" name="conversation_id">
                        <input type="hidden" name="name">
                        <input type="hidden" name="phone">
                        <input type="hidden" name="email">
                        <input type="text" name="message_body" class="form-control" placeholder="Scrie mesaj..." autocomplete="off">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="msgToast" class="alert msg-toast" role="alert"></div>

    <div id="msgModalBackdrop" class="msg-modal-backdrop" aria-hidden="true">
        <div class="msg-modal" role="dialog" aria-modal="true">
            <div class="msg-modal__head">
                <h5 class="mb-0">Conversație nouă</h5>
                <button type="button" class="btn-close" id="msgModalClose" aria-label="Închide"></button>
            </div>
            <form id="msgNewForm">
                <div class="msg-modal__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Client *</label>
                            <input class="form-control" type="text" name="name" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Canal</label>
                            <select class="form-select" name="channel">
                                <option value="manual">Manual</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="olx">OLX</option>
                                <option value="pieseauto">PieseAuto.ro</option>
                                <option value="dezro">dez.ro</option>
                                <option value="facebook">Facebook</option>
                                <option value="website">Website</option>
                                <option value="email">Email</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefon</label>
                            <input class="form-control" type="tel" name="phone" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="email" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subiect</label>
                            <input class="form-control" type="text" name="subject" maxlength="160">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ID conversație externă</label>
                            <input class="form-control" type="text" name="external_conversation_id" maxlength="190">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">URL sursă</label>
                            <input class="form-control" type="url" name="source_url" maxlength="500">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Robot asignat</label>
                            <input class="form-control" type="text" name="assigned_bot" maxlength="120">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Mesaj *</label>
                            <textarea class="form-control" name="message_body" rows="4" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="msg-modal__foot">
                    <button type="button" class="btn btn-outline-secondary" id="msgModalCancel">Anulează</button>
                    <button type="submit" class="btn btn-primary">Trimite</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .msg-panel { --msg-accent: #308e87; }
        .msg-toolbar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; justify-content: space-between; margin-bottom: 1rem; }
        .msg-toolbar__actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        .msg-card { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; box-shadow: 0 8px 24px rgba(15,23,42,.04); display: flex; flex-direction: column; min-height: 72vh; }
        .msg-card__head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: 1rem 1rem .5rem; border-bottom: 1px solid #f1f5f9; }
        .msg-search-wrap { position: relative; padding: .75rem 1rem; }
        .msg-search-wrap i { position: absolute; left: 1.35rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: .85rem; }
        .msg-search-wrap input { padding-left: 2rem; }
        .msg-conversations { flex: 1; overflow-y: auto; padding: 0 .5rem .5rem; max-height: calc(72vh - 130px); }
        .msg-conv-btn { width: 100%; border: 0; background: transparent; text-align: left; display: flex; gap: .75rem; align-items: flex-start; padding: .75rem; border-radius: 12px; transition: background .15s; }
        .msg-conv-btn:hover, .msg-conv-btn.is-active { background: rgba(48,142,135,.1); }
        .msg-conv-btn.is-unread .msg-conv-name { font-weight: 700; }
        .msg-conv-avatar { width: 40px; height: 40px; border-radius: 50%; background: rgba(48,142,135,.15); color: var(--msg-accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .msg-conv-body { min-width: 0; flex: 1; }
        .msg-conv-top { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; }
        .msg-conv-name { font-weight: 600; }
        .msg-conv-channel { font-size: .65rem; border: 1px solid #e2e8f0; border-radius: 999px; padding: .1rem .45rem; color: #64748b; }
        .msg-conv-preview { font-size: .78rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .msg-conv-sub { font-size: .68rem; color: #94a3b8; }
        .msg-conv-time { font-size: .72rem; color: #94a3b8; flex-shrink: 0; }
        .msg-pagination { padding: .75rem 1rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; gap: .5rem; font-size: .82rem; }
        .msg-thread-head { display: flex; align-items: center; gap: .75rem; padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
        .msg-thread-avatar { width: 42px; height: 42px; border-radius: 50%; background: rgba(48,142,135,.15); color: var(--msg-accent); display: flex; align-items: center; justify-content: center; }
        .msg-thread { flex: 1; overflow-y: auto; padding: 1.25rem; background: #f8fafc; min-height: 320px; max-height: calc(72vh - 150px); display: flex; flex-direction: column; gap: .75rem; }
        .msg-bubble { max-width: min(420px, 85%); padding: .75rem 1rem; border-radius: 14px; box-shadow: 0 2px 8px rgba(15,23,42,.06); font-size: .92rem; }
        .msg-bubble--in { background: #fff; align-self: flex-start; border-bottom-left-radius: 4px; }
        .msg-bubble--out { background: var(--msg-accent); color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }
        .msg-bubble__meta { margin-top: .35rem; font-size: .68rem; opacity: .75; }
        .msg-reply { display: flex; gap: .5rem; padding: 1rem 1.25rem; border-top: 1px solid #f1f5f9; }
        .msg-empty { text-align: center; color: #64748b; padding: 2rem 1rem; }
        .msg-toast { position: fixed; top: 1rem; right: 1rem; z-index: 10060; display: none; min-width: 240px; }
        .msg-toast.is-visible { display: block; }
        .msg-modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 10050; display: none; align-items: flex-start; justify-content: center; padding: 1.5rem; overflow-y: auto; }
        .msg-modal-backdrop.is-open { display: flex; }
        .msg-modal { background: #fff; border-radius: 14px; width: 100%; max-width: 640px; box-shadow: 0 20px 50px rgba(15,23,42,.2); }
        .msg-modal__head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; }
        .msg-modal__body { padding: 1.25rem; }
        .msg-modal__foot { padding: 1rem 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: .5rem; }
    </style>

    <script>
    (function () {
        'use strict';
        const API = <?= json_encode($api, JSON_UNESCAPED_UNICODE) ?>;
        const convEl = document.getElementById('msgConversations');
        const pagEl = document.getElementById('msgPagination');
        const threadEl = document.getElementById('msgThread');
        const searchEl = document.getElementById('msgSearch');
        const replyForm = document.getElementById('msgReplyForm');
        const newForm = document.getElementById('msgNewForm');
        const modal = document.getElementById('msgModalBackdrop');
        const toast = document.getElementById('msgToast');

        let conversations = [];
        let threadMessages = [];
        let activeConversationId = null;
        let listMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };
        let currentPage = 1;

        const channels = { whatsapp:'WhatsApp', olx:'OLX', pieseauto:'PieseAuto.ro', dezro:'dez.ro', facebook:'Facebook', website:'Website', email:'Email', manual:'Manual' };

        function esc(v) {
            return String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        }

        function channelLabel(ch) { return channels[ch] || ch || 'Manual'; }

        function showToast(msg, isErr) {
            if (!toast) return;
            toast.textContent = msg;
            toast.className = 'alert msg-toast is-visible ' + (isErr ? 'alert-danger' : 'alert-success');
            setTimeout(() => toast.classList.remove('is-visible'), 3200);
        }

        async function apiCall(action, payload) {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...payload })
            });
            const raw = await res.text();
            let json;
            try { json = JSON.parse(raw); } catch (e) { throw new Error('Răspuns invalid de la server.'); }
            if (!res.ok || !json.success) throw new Error(json.message || 'Eroare necunoscută');
            return json.data;
        }

        function convId(msg) { return Number(msg.conversation_id || msg.randomn_id); }

        function formToObject(form) {
            const payload = {};
            new FormData(form).forEach((value, key) => {
                if (String(value).trim() !== '') payload[key] = value;
            });
            return payload;
        }

        function renderPagination() {
            if (!pagEl) return;
            if (listMeta.total_pages <= 1) {
                pagEl.innerHTML = listMeta.total ? `<span class="text-muted">${listMeta.total} conversații</span>` : '';
                return;
            }
            pagEl.innerHTML = `
                <button type="button" class="btn btn-sm btn-outline-secondary" data-page="${listMeta.page - 1}" ${listMeta.page <= 1 ? 'disabled' : ''}>Înapoi</button>
                <span class="text-muted">${listMeta.page} / ${listMeta.total_pages}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-page="${listMeta.page + 1}" ${listMeta.page >= listMeta.total_pages ? 'disabled' : ''}>Înainte</button>`;
        }

        function renderConversations() {
            if (!convEl) return;
            if (!conversations.length) {
                convEl.innerHTML = '<div class="msg-empty">Nu există mesaje.</div>';
                renderPagination();
                return;
            }
            convEl.innerHTML = conversations.map((latest) => {
                const id = convId(latest);
                const unread = !latest.is_read && latest.direction === 'inbound';
                const active = Number(activeConversationId) === id;
                return `<button type="button" data-conversation-id="${esc(id)}" class="msg-conv-btn${active ? ' is-active' : ''}${unread ? ' is-unread' : ''}">
                    <div class="msg-conv-avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></div>
                    <div class="msg-conv-body">
                        <div class="msg-conv-top">
                            <span class="msg-conv-name">${esc(latest.name || 'Client')}</span>
                            <span class="msg-conv-channel">${esc(channelLabel(latest.channel))}</span>
                        </div>
                        <div class="msg-conv-preview">${esc(latest.message_body || '')}</div>
                        <div class="msg-conv-sub">${esc(latest.delivery_status || '')} · ${esc(latest.bot_status || '')}</div>
                    </div>
                    <div class="msg-conv-time">${esc(String(latest.created_at || '').slice(11, 16))}</div>
                </button>`;
            }).join('');
            renderPagination();
        }

        function renderThread() {
            const items = threadMessages.slice().sort((a, b) => Number(a.randomn_id || 0) - Number(b.randomn_id || 0));
            if (!items.length) {
                threadEl.innerHTML = '<div class="msg-empty">Selectează o conversație din listă.</div>';
                return;
            }
            const latest = items[items.length - 1];
            document.getElementById('msgActiveName').textContent = (latest.name || 'Client') + ' · ' + channelLabel(latest.channel);
            document.getElementById('msgActiveStatus').textContent = latest.phone || latest.email || '—';
            replyForm.elements.conversation_id.value = activeConversationId;
            replyForm.elements.name.value = latest.name || '';
            replyForm.elements.phone.value = latest.phone || '';
            replyForm.elements.email.value = latest.email || '';
            threadEl.innerHTML = items.map((m) => {
                const out = m.direction === 'outbound';
                return `<div class="msg-bubble ${out ? 'msg-bubble--out' : 'msg-bubble--in'}">
                    ${esc(m.message_body || '')}
                    <div class="msg-bubble__meta">${esc(m.created_at || '')} · ${esc(m.delivery_status || '')} · ${esc(m.bot_status || '')}</div>
                </div>`;
            }).join('');
            threadEl.scrollTop = threadEl.scrollHeight;
        }

        async function loadConversations(page) {
            if (page) currentPage = page;
            const data = await apiCall('conversations', { page: currentPage, per_page: 10, q: (searchEl?.value || '').trim() });
            conversations = data.items || [];
            listMeta = data;
            currentPage = data.page || 1;
            renderConversations();
        }

        async function loadThread(conversationId) {
            activeConversationId = Number(conversationId);
            threadMessages = await apiCall('conversation', { conversation_id: activeConversationId });
            renderConversations();
            renderThread();
            try {
                await apiCall('markread', { conversation_id: activeConversationId });
            } catch (e) {}
        }

        function openModal() { newForm.reset(); modal?.classList.add('is-open'); }
        function closeModal() { modal?.classList.remove('is-open'); }

        convEl?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-conversation-id]');
            if (!btn) return;
            loadThread(Number(btn.dataset.conversationId)).catch((err) => showToast(err.message, true));
        });

        pagEl?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-page]');
            if (!btn || btn.disabled) return;
            loadConversations(Number(btn.dataset.page)).catch((err) => showToast(err.message, true));
        });

        let searchTimer;
        searchEl?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { currentPage = 1; loadConversations().catch((e) => showToast(e.message, true)); }, 300);
        });

        document.getElementById('msgNewConversation')?.addEventListener('click', openModal);
        document.getElementById('msgModalClose')?.addEventListener('click', closeModal);
        document.getElementById('msgModalCancel')?.addEventListener('click', closeModal);

        document.getElementById('msgImportLeads')?.addEventListener('click', async () => {
            try {
                const data = await apiCall('import_leads', {});
                showToast('Importate ' + (data.imported || 0) + ' cereri.', false);
                await loadConversations(1);
            } catch (err) { showToast(err.message, true); }
        });

        replyForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const payload = formToObject(replyForm);
                if (!payload.conversation_id) throw new Error('Selectează o conversație.');
                const latest = threadMessages[threadMessages.length - 1] || conversations.find((c) => convId(c) === Number(payload.conversation_id));
                await apiCall('add', {
                    ...payload,
                    channel: latest?.channel || 'manual',
                    external_conversation_id: latest?.external_conversation_id || '',
                    assigned_bot: latest?.assigned_bot || '',
                    direction: 'outbound',
                    message_status: 'queued',
                    delivery_status: 'queued',
                    bot_status: 'pending',
                    is_read: 1
                });
                replyForm.elements.message_body.value = '';
                await loadThread(activeConversationId);
                await loadConversations(currentPage);
                showToast('Mesaj pus în coadă.', false);
            } catch (err) { showToast(err.message, true); }
        });

        newForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const created = await apiCall('add', {
                    ...formToObject(newForm),
                    direction: 'outbound',
                    message_status: 'queued',
                    delivery_status: 'queued',
                    bot_status: 'pending',
                    is_read: 1
                });
                closeModal();
                activeConversationId = created.conversation_id;
                await loadConversations(1);
                await loadThread(activeConversationId);
                showToast('Conversație creată.', false);
            } catch (err) { showToast(err.message, true); }
        });

        loadConversations().catch((err) => showToast(err.message, true));
    })();
    </script>
    <?php
}
