/**
 * Account › Messages: send without reloading, poll for new messages (5 s while the conversation is open and the
 * tab visible) and for the conversation list (20 s), keep the unread badges in sync.
 * The page works without it (plain forms); see template-parts/account/chat.php.
 */

import { config } from '../lib/config.js';

const cfg = config.chat || {};
const i18n = cfg.i18n || {};
const OPEN_EVERY = 5000;
const LIST_EVERY = 20000;

function api(path, options = {}) {
	return fetch(cfg.rest + path, {
		credentials: 'same-origin',
		...options,
		headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json', ...(options.headers || {}) },
	}).then(async (r) => {
		const json = await r.json().catch(() => ({}));
		if (!r.ok) {
			throw new Error(json?.message || i18n.failed);
		}
		return json;
	});
}

function setBadges(count) {
	document.querySelectorAll('[data-th-chat-badge]').forEach((badge) => {
		const sr = badge.querySelector('.th-sr-only');
		badge.textContent = count > 99 ? '99+' : String(count);
		if (sr) {
			badge.append(' ', sr);
		}
		badge.hidden = !count;
	});
}

function messageEl(m, otherName) {
	const li = document.createElement('li');
	li.className = `th-msg${m.mine ? ' th-msg--mine' : ''}`;
	li.dataset.id = String(m.id);
	const who = document.createElement('span');
	who.className = 'th-sr-only';
	who.textContent = `${m.mine ? i18n.you : otherName}:`;
	const p = document.createElement('p');
	p.className = 'th-msg__body';
	p.textContent = m.body;
	const time = document.createElement('time');
	time.className = 'th-msg__time';
	time.dateTime = m.at;
	time.textContent = m.time;
	li.append(who, p, time);
	return li;
}

export function init(root = document) {
	const chat = root.querySelector('[data-th-chat]');
	if (!chat || chat.dataset.thReady || !cfg.rest) {
		return;
	}
	chat.dataset.thReady = '1';
	const list = chat.querySelector('[data-th-chat-messages]');
	const form = chat.querySelector('[data-th-chat-form]');
	const input = form?.querySelector('textarea');
	const status = chat.querySelector('[data-th-chat-status]');
	const seenEl = chat.querySelector('[data-th-chat-seen]');
	const otherName = chat.querySelector('#th-chat-with')?.textContent.trim() || '';
	let threadId = Number(chat.dataset.thread) || 0;
	let busy = false;

	const last = () => Number(list?.dataset.last) || 0;
	const scrollDown = () => list && (list.scrollTop = list.scrollHeight);
	const append = (messages) => {
		if (!list || !messages?.length) {
			return;
		}
		list.querySelector('.th-chat__intro')?.remove();
		messages.forEach((m) => {
			if (!list.querySelector(`[data-id="${m.id}"]`)) {
				list.append(messageEl(m, otherName));
			}
		});
		list.dataset.last = String(Math.max(last(), ...messages.map((m) => m.id)));
		scrollDown();
	};
	const updateSeen = (seen) => {
		if (!seenEl || !list) {
			return;
		}
		const mine = [...list.querySelectorAll('.th-msg--mine')].pop();
		seenEl.hidden = !(mine && seen >= Number(mine.dataset.id));
	};
	scrollDown();

	form?.addEventListener('submit', async (event) => {
		event.preventDefault();
		const text = input.value.trim();
		if (!text || busy) {
			return;
		}
		busy = true;
		status.textContent = i18n.sending;
		try {
			if (threadId) {
				const json = await api(`threads/${threadId}`, { method: 'POST', body: JSON.stringify({ message: text, after: last() }) });
				append(json.messages);
				updateSeen(Number(list.dataset.seen) || 0);
			} else {
				const listing = Number(form.querySelector('[name="listing"]').value);
				const json = await api('threads', { method: 'POST', body: JSON.stringify({ listing_id: listing, message: text }) });
				window.location.href = json.url;
				return;
			}
			input.value = '';
			status.textContent = '';
		} catch (e) {
			status.textContent = e.message || i18n.failed;
		} finally {
			busy = false;
		}
	});
	input?.addEventListener('keydown', (event) => {
		if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
			event.preventDefault();
			form.requestSubmit();
		}
	});

	const pollThread = async () => {
		if (!threadId || document.hidden) {
			return;
		}
		try {
			const json = await api(`threads/${threadId}?after=${last()}`, { method: 'GET' });
			append(json.messages);
			list.dataset.seen = String(json.seen || 0);
			updateSeen(json.seen || 0);
			setBadges(json.unread || 0);
		} catch {
			// Offline or logged out: try again on the next tick.
		}
	};
	const threadsEl = chat.querySelector('[data-th-chat-threads]');
	const pollList = async () => {
		if (document.hidden || !threadsEl) {
			return;
		}
		try {
			const json = await api('threads', { method: 'GET' });
			setBadges(json.unread || 0);
			(json.threads || []).forEach((t) => {
				const a = threadsEl.querySelector(`[data-thread="${t.id}"]`);
				if (!a) {
					return;
				}
				a.classList.toggle('is-unread', t.unread > 0 && t.id !== threadId);
				a.querySelector('.th-chat-item__last').textContent = (t.last.mine ? `${i18n.you}: ` : '') + t.last.body;
				a.querySelector('.th-chat-item__when').textContent = t.when;
			});
		} catch {
			// Try again on the next tick.
		}
	};
	if (threadId) {
		setInterval(pollThread, OPEN_EVERY);
	}
	setInterval(pollList, LIST_EVERY);
	document.addEventListener('visibilitychange', () => {
		if (!document.hidden) {
			pollThread();
			pollList();
		}
	});
}
