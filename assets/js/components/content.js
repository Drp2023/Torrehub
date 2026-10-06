/**
 * Guides and static pages: "In this guide" / legal contents scrollspy, reading progress (mobile), share + print,
 * FAQ search. Everything works without it (anchors, plain accordion).
 */

export function init(root = document) {
	root.querySelectorAll('[data-th-toc]').forEach(scrollspy);
	const progress = root.querySelector('[data-th-reading-progress]');
	const article = root.querySelector('[data-th-guide]');
	if (progress && article) {
		readingProgress(progress, article);
	}
	shareAndPrint(root);
	const faq = root.querySelector('[data-th-faq]');
	if (faq) {
		faqSearch(root, faq);
	}
}

/** Mark the contents link of the heading currently in view. */
function scrollspy(nav) {
	const links = [...nav.querySelectorAll('a[href^="#"]')];
	const targets = links.map((a) => document.getElementById(decodeURIComponent(a.hash.slice(1)))).filter(Boolean);
	if (!targets.length || !('IntersectionObserver' in window)) {
		return;
	}
	const visible = new Set();
	const mark = () => {
		const current = targets.find((t) => visible.has(t)) || targets.filter((t) => t.getBoundingClientRect().top < 0).pop() || targets[0];
		links.forEach((a) => {
			if (a.hash === `#${current.id}`) {
				a.setAttribute('aria-current', 'true');
			} else {
				a.removeAttribute('aria-current');
			}
		});
	};
	const io = new IntersectionObserver((entries) => {
		entries.forEach((e) => (e.isIntersecting ? visible.add(e.target) : visible.delete(e.target)));
		mark();
	}, { rootMargin: '0px 0px -60% 0px' });
	targets.forEach((t) => io.observe(t));
	mark();
}

/** Orange bar at the top (mobile): how far through the article. */
function readingProgress(bar, article) {
	const fill = bar.querySelector('span');
	let ticking = false;
	const update = () => {
		ticking = false;
		const rect = article.getBoundingClientRect();
		const total = rect.height - window.innerHeight;
		const done = total > 0 ? Math.min(1, Math.max(0, -rect.top / total)) : 1;
		fill.style.setProperty('--pct', `${Math.round(done * 100)}%`);
	};
	window.addEventListener('scroll', () => {
		if (!ticking) {
			ticking = true;
			requestAnimationFrame(update);
		}
	}, { passive: true });
	update();
}

function shareAndPrint(root) {
	const share = root.querySelector('[data-th-share]');
	if (share && (navigator.share || navigator.clipboard)) {
		share.hidden = false;
		share.addEventListener('click', () => {
			const payload = { title: share.dataset.title, url: share.dataset.url };
			if (navigator.share) {
				navigator.share(payload).catch(() => {});
			} else {
				navigator.clipboard.writeText(payload.url).then(() => window.torrehub?.toast?.(share.dataset.copied || 'Link copied', { type: 'success' }));
			}
		});
	}
	root.querySelector('[data-th-print]')?.addEventListener('click', () => window.print());
}

/** Show only the questions that contain every typed word. */
function faqSearch(root, faq) {
	const box = root.querySelector('[data-th-faq-search]');
	const input = box?.querySelector('input');
	const none = root.querySelector('[data-th-faq-none]');
	const items = [...faq.querySelectorAll('details')];
	if (!box || !input || !items.length) {
		return;
	}
	box.hidden = false;
	const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
	const texts = items.map((d) => norm(d.textContent || ''));
	input.addEventListener('input', () => {
		const words = norm(input.value).split(/\s+/).filter(Boolean);
		let shown = 0;
		items.forEach((d, i) => {
			const hit = words.every((w) => texts[i].includes(w));
			d.hidden = !hit;
			if (hit) {
				shown++;
				if (words.length) {
					d.open = true;
				}
			}
		});
		if (none) {
			none.hidden = shown > 0;
		}
	});
}
