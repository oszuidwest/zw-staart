/**
 * ZuidWest Staart: next article bar.
 *
 * Halfway through the article a bar slides in with the most read reading tip the visitor has
 * not read yet. Its wheel fills while reading and is full once the share buttons are visible
 * above the bar; scrolling there opens the next article as a regular page load. On mobile the
 * bar is closed by swiping it down, on desktop with a close button. Closing it keeps it away
 * for the rest of the session and brings back the reading tips block.
 */
(() => {
	'use strict';

	const config = window.zwStaartNextArticle;
	const article = document.querySelector('.zw-prose');
	if (!config || !article) {
		return;
	}

	const DISMISSED_KEY = 'zw_staart_next_article_dismissed';
	const VISITED_COOKIE = 'zw_staart_visited_posts=';
	const EVENT_NAME = 'Recirculatie Scroll';
	const SHOW_AT = 0.35;
	const ALMOST = 0.9;
	// Scrolling back below this while the next article loads cancels it; the margin ignores jitter.
	const CANCEL_BELOW = 0.99;
	const HIDE_ABOVE_PX = 200;
	const NAVIGATE_DELAY_MS = 300;
	const NAVIGATE_TIMEOUT_MS = 1500;
	const SWIPE_START_PX = 6;
	const SWIPE_CLOSE_PX = 60;
	const KICKER = {
		idle: 'Leestip',
		almost: 'Blijf scrollen om te openen',
		loading: 'Volgende artikel laden\u2026', // Ellipsis escaped: the file stays ASCII, whatever charset the server sends.
	};

	function isDismissed() {
		try {
			return sessionStorage.getItem(DISMISSED_KEY) === '1';
		} catch (e) {
			return false;
		}
	}

	function visitedPostIds() {
		const row = document.cookie.split('; ').find((part) => part.startsWith(VISITED_COOKIE));
		return new Set(row ? row.slice(VISITED_COOKIE.length).split(',').map((id) => parseInt(id, 10)) : []);
	}

	const pickNext = () => {
		const visited = visitedPostIds();
		return config.candidates.find((candidate) => !visited.has(candidate.id));
	};

	let next = pickNext();
	if (!next || isDismissed()) {
		return;
	}

	// The bar replaces the reading tips block. Hide it right away, not when the bar slides in,
	// so the text does not jump while reading.
	const readingTips = document.getElementById('zw-staart-top-posts-list');
	if (readingTips) {
		readingTips.hidden = true;
	}

	// The wheel is full once the share buttons below the article are visible; without them, at the
	// end of the text.
	const shareLink = Array.from(document.querySelectorAll('a[href*="sharebutton"]')).find(
		(candidate) => article.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING
	);
	const end = (shareLink && shareLink.closest('aside')) || article;

	function track(action, callback) {
		if (typeof window.plausible === 'function') {
			window.plausible(EVENT_NAME, { props: { actie: action }, callback });
		} else if (callback) {
			callback();
		}
	}

	const bar = document.createElement('div');
	bar.className = 'zw-staart-next-article';
	bar.setAttribute('role', 'region');
	bar.setAttribute('aria-label', 'Volgende artikel voor jou');
	// Icons: Material Icons (filled), the same set the theme header uses.
	bar.innerHTML = `
		<div class="zw-staart-next-article-inner">
			<a class="zw-staart-next-article-link plausible-event-name=Recirculatie+Scroll plausible-event-actie=geklikt">
				<span class="zw-staart-next-article-text">
					<span class="zw-staart-next-article-label">${KICKER.idle}</span>
					<span class="zw-staart-next-article-title"></span>
				</span>
				<span class="zw-staart-next-article-wheel" aria-hidden="true">
					<svg class="zw-staart-next-article-ring" viewBox="0 0 48 48"><circle class="zw-staart-next-article-ring-track" cx="24" cy="24" r="21"/><circle class="zw-staart-next-article-ring-fill" cx="24" cy="24" r="21" pathLength="100"/></svg>
					<svg class="zw-staart-next-article-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12l-1.41-1.41L13 16.17V4h-2v12.17l-5.58-5.59L4 12l8 8 8-8z"/></svg>
				</span>
			</a>
			<button class="zw-staart-next-article-close" type="button" aria-label="Stop met doorladen"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
		</div>`;
	const link = bar.querySelector('.zw-staart-next-article-link');
	const title = bar.querySelector('.zw-staart-next-article-title');
	const kicker = bar.querySelector('.zw-staart-next-article-label');
	const ringFill = bar.querySelector('.zw-staart-next-article-ring-fill');
	const render = () => {
		link.href = next.url;
		title.textContent = next.title;
	};
	render();
	document.body.appendChild(bar);

	let dismissed = false;
	let impressed = false;
	let armed = false;
	let triggered = false;
	let swiped = false;
	let frame = 0;
	// Increased to cancel a pending page load, including a late analytics callback.
	let navigationId = 0;
	let timers = [];

	function cancelNavigation() {
		navigationId++;
		timers.forEach((timer) => window.clearTimeout(timer));
		timers = [];
		triggered = false;
		bar.classList.remove('is-loading');
	}

	function setKicker(text) {
		if (kicker.textContent !== text) {
			kicker.textContent = text;
		}
	}

	// 0 at the start of the text, 1 once the share buttons are fully visible above the bar.
	function progress() {
		const top = article.getBoundingClientRect().top;
		const bottom = end.getBoundingClientRect().bottom;
		const visibleBottom = window.innerHeight - bar.offsetHeight;
		return Math.min(1, Math.max(0, (visibleBottom - top) / Math.max(1, bottom - top)));
	}

	// Hides the bar for good on this page and brings the reading tips block back.
	function deactivate() {
		dismissed = true;
		cancelNavigation();
		bar.classList.remove('is-visible');
		if (readingTips) {
			readingTips.hidden = false;
		}
	}

	function dismiss(action) {
		try {
			sessionStorage.setItem(DISMISSED_KEY, '1');
		} catch (e) {}
		track(action);
		deactivate();
	}

	function openNext() {
		triggered = true;
		bar.classList.add('is-visible', 'is-loading');
		ringFill.style.strokeDashoffset = '0';
		setKicker(KICKER.loading);

		// Show the full, green wheel briefly, then load the next article as a regular page.
		const current = ++navigationId;
		const go = () => {
			if (current === navigationId) {
				navigationId++;
				window.location.href = next.url;
			}
		};
		timers = [
			window.setTimeout(() => track('doorgescrold', go), NAVIGATE_DELAY_MS),
			window.setTimeout(go, NAVIGATE_DELAY_MS + NAVIGATE_TIMEOUT_MS),
		];
	}

	function update() {
		frame = 0;
		if (dismissed) {
			return;
		}
		const value = progress();
		if (triggered) {
			if (value >= CANCEL_BELOW) {
				return;
			}
			cancelNavigation();
		}

		if (value >= SHOW_AT && window.scrollY >= HIDE_ABOVE_PX) {
			if (!bar.classList.contains('is-visible')) {
				bar.classList.add('is-visible');
				if (!impressed) {
					impressed = true;
					track('getoond');
				}
			}
		} else if (window.scrollY < HIDE_ABOVE_PX) {
			bar.classList.remove('is-visible');
		}

		ringFill.style.strokeDashoffset = String(100 - value * 100);
		setKicker(value >= ALMOST ? KICKER.almost : KICKER.idle);
		// Only open the next article while the bar is on screen, so a short article never jumps away unseen.
		if (value >= 1 && armed && bar.classList.contains('is-visible')) {
			openNext();
		}
	}

	function schedule() {
		if (!frame) {
			frame = window.requestAnimationFrame(update);
		}
	}

	// Only a real scroll action by the reader may open the next article. A page that the back button
	// restores at the bottom must not immediately load the next article again.
	function onInteract() {
		if (!dismissed && !triggered && progress() < 1) {
			armed = true;
		}
	}

	link.addEventListener('click', (event) => {
		// A swipe can end in a click; that must neither open nor count as a click.
		if (swiped) {
			event.preventDefault();
			event.stopPropagation();
			swiped = false;
		}
	});
	bar.querySelector('.zw-staart-next-article-close').addEventListener('click', () => dismiss('gesloten'));

	// Mobile: swiping the bar down closes it, like a notification. A short swipe springs back.
	let startY = null;
	let distance = 0;
	bar.addEventListener('pointerdown', (event) => {
		swiped = false;
		if (event.pointerType === 'touch') {
			startY = event.clientY;
			distance = 0;
		}
	});
	bar.addEventListener('pointermove', (event) => {
		if (startY === null) {
			return;
		}
		distance = Math.max(0, event.clientY - startY);
		if (distance > SWIPE_START_PX && !bar.classList.contains('is-dragging')) {
			// Only capture once the reader really swipes, so a tap still opens the link.
			bar.setPointerCapture(event.pointerId);
			bar.classList.add('is-dragging');
		}
		if (bar.classList.contains('is-dragging')) {
			bar.style.transform = `translateY(${distance}px)`;
		}
	});
	const release = () => {
		if (startY === null) {
			return;
		}
		startY = null;
		swiped = bar.classList.contains('is-dragging');
		bar.classList.remove('is-dragging');
		bar.style.transform = '';
		if (distance > Math.min(SWIPE_CLOSE_PX, bar.offsetHeight / 2)) {
			dismiss('weggeveegd');
		}
	};
	bar.addEventListener('pointerup', release);
	bar.addEventListener('pointercancel', release);

	window.addEventListener('scroll', schedule, { passive: true });
	window.addEventListener('resize', schedule);
	['wheel', 'touchstart', 'keydown', 'pointerdown'].forEach((type) => window.addEventListener(type, onInteract, { passive: true }));
	// Back via the bfcache: the page is restored as it was left. Cancel a pending page load and
	// re-check the cookie and session, as the visitor may have read the tip or closed the bar since.
	window.addEventListener('pageshow', (event) => {
		if (!event.persisted || dismissed) {
			return;
		}
		cancelNavigation();
		armed = false;
		next = pickNext();
		if (!next || isDismissed()) {
			deactivate();
			return;
		}
		render();
		schedule();
	});

	update();
})();
