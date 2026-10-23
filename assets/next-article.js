/**
 * ZuidWest Staart: next article bar.
 *
 * Halfway through the article a bar slides in with the most read reading tip the visitor has
 * not read yet. Its wheel fills while reading and is full once the share buttons are visible
 * above the bar; scrolling there opens the next article as a regular page load. On mobile the
 * bar is closed by swiping it down, on desktop with a close button. Closing it keeps it away
 * for the rest of the session and brings back the reading tips block.
 *
 * In A/B test mode half of the visitors get the bar (b) and the other half only the reading tips
 * block (a). The group is drawn in the browser, as pages are cached, and kept for the day: Plausible
 * counts visitors per day, so each day is an independent repeat of the test. Every view and every
 * recirculation event then carries the group as the Plausible property "variant".
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
	const VARIANT_KEY = 'zw_staart_ab_variant';
	const RETURN_KEY = 'zw_staart_ab_return';
	// Guardrail: coming back within this time means the visitor did not want the article.
	const QUICK_RETURN_MS = 15000;
	const KICKER = {
		idle: 'Volgende artikel',
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

	// Draws a group once per day, Amsterdam time like the Plausible statistics. Without storage there
	// is no fixed group: such a visitor stays outside the test.
	function drawVariant() {
		const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
		try {
			const [day, drawn] = (localStorage.getItem(VARIANT_KEY) || '').split(':');
			if (day === today && (drawn === 'a' || drawn === 'b')) {
				return drawn;
			}
			const group = Math.random() < 0.5 ? 'a' : 'b';
			localStorage.setItem(VARIANT_KEY, `${today}:${group}`);
			return group;
		} catch (e) {
			return '';
		}
	}

	const variant = config.test ? drawVariant() : '';

	// Events the visitor did not cause are non-interactive, so they do not change the bounce rate.
	function send(name, props, { callback, interactive = true } = {}) {
		if (typeof window.plausible === 'function') {
			window.plausible(name, { props: variant ? { variant, ...props } : props, callback, interactive });
		} else if (callback) {
			callback();
		}
	}

	function track(action, options) {
		send(EVENT_NAME, { actie: action }, options);
	}

	const isPlainClick = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

	// Departures per article, so a quick return is noticed on each step of a chain of articles.
	function departures() {
		try {
			const all = JSON.parse(sessionStorage.getItem(RETURN_KEY)) || {};
			return Object.fromEntries(Object.entries(all).filter(([, left]) => Date.now() - left.at <= QUICK_RETURN_MS));
		} catch (e) {
			return {};
		}
	}

	function saveDepartures(all) {
		try {
			sessionStorage.setItem(RETURN_KEY, JSON.stringify(all));
		} catch (e) {}
	}

	function markLeave(via) {
		if (variant) {
			saveDepartures({ ...departures(), [location.pathname]: { at: Date.now(), via, variant } });
		}
	}

	function checkReturn() {
		const all = departures();
		const left = all[location.pathname];
		if (left) {
			delete all[location.pathname];
			saveDepartures(all);
			// Counted for the group the visitor left in, also when a new day has drawn another one since.
			send('Recirculatie Terug', { via: left.via, variant: left.variant }, { interactive: false });
		}
	}

	if (variant) {
		// Every view counts, whether or not the visitor reaches the bar or the block (intent to treat).
		// A page restored by the back button is a new view too, as it is for Plausible's page views.
		send('Recirculatie Weergave', {}, { interactive: false });
		checkReturn();
		window.addEventListener('pageshow', (event) => {
			if (event.persisted) {
				// Restored from an earlier day: load the page again with today's group.
				if (drawVariant() !== variant) {
					location.reload();
					return;
				}
				send('Recirculatie Weergave', {}, { interactive: false });
				checkReturn();
			}
		});
		document.querySelectorAll('#zw-staart-top-posts-list a').forEach((tip) => {
			tip.classList.add(`plausible-event-variant=${variant}`);
			tip.addEventListener('click', (event) => isPlainClick(event) && markLeave('leestip'));
		});
		if (variant === 'a') {
			return;
		}
	} else if (config.test) {
		return;
	}

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
	// The row of buttons, not the whole share block, whose bottom padding would keep the wheel short.
	const end = (shareLink && shareLink.parentElement) || article;

	const bar = document.createElement('div');
	bar.className = 'zw-staart-next-article';
	bar.setAttribute('role', 'region');
	bar.setAttribute('aria-label', 'Volgende artikel voor jou');
	// Icons: Material Icons (filled), the same set the theme header uses.
	bar.innerHTML = `
		<div class="zw-staart-next-article-inner">
			<a class="zw-staart-next-article-link plausible-event-name=Recirculatie+Scroll plausible-event-actie=geklikt">
				<img class="zw-staart-next-article-image" alt="" width="80" height="45" loading="lazy" decoding="async">
				<span class="zw-staart-next-article-text">
					<span class="zw-staart-next-article-label">${KICKER.idle}</span>
					<span class="zw-staart-next-article-title"></span>
				</span>
				<span class="zw-staart-next-article-wheel" aria-hidden="true">
					<svg class="zw-staart-next-article-ring" viewBox="0 0 48 48"><circle class="zw-staart-next-article-ring-track" cx="24" cy="24" r="21"/><circle class="zw-staart-next-article-ring-fill" cx="24" cy="24" r="21" pathLength="100"/></svg>
					<svg class="zw-staart-next-article-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6.41 16.59 5 12 9.58 7.41 5 6 6.41l6 6z"/><path d="m18 13-1.41-1.41L12 16.17l-4.59-4.58L6 13l6 6z"/></svg>
				</span>
			</a>
			<button class="zw-staart-next-article-close" type="button" aria-label="Stop met doorladen"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
		</div>`;
	const link = bar.querySelector('.zw-staart-next-article-link');
	if (variant) {
		link.classList.add(`plausible-event-variant=${variant}`);
	}
	const title = bar.querySelector('.zw-staart-next-article-title');
	const kicker = bar.querySelector('.zw-staart-next-article-label');
	const ringFill = bar.querySelector('.zw-staart-next-article-ring-fill');
	const image = bar.querySelector('.zw-staart-next-article-image');
	const render = () => {
		link.href = next.url;
		title.textContent = next.title;
		// Decorative: the title next to it says the same. Mobile hides it, so lazy loading skips it.
		image.hidden = !next.image;
		if (next.image) {
			image.src = next.image;
		}
	};
	render();
	document.body.appendChild(bar);

	let dismissed = false;
	let impressed = false;
	let armed = false;
	let triggered = false;
	let swiped = false;
	let frame = 0;
	// Set once the page switch is committed; scrolling back no longer cancels it.
	let leaving = false;
	let timer = 0;

	function cancelNavigation() {
		window.clearTimeout(timer);
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

		// Show the full, green wheel briefly; scrolling back meanwhile cancels. Then the switch is final:
		// only now count it, and load the next article as a regular page, also if analytics does not answer.
		timer = window.setTimeout(() => {
			leaving = true;
			markLeave('doorgescrold');
			let left = false;
			const leave = () => {
				if (!left) {
					left = true;
					window.location.href = next.url;
				}
			};
			track('doorgescrold', { callback: leave });
			window.setTimeout(leave, NAVIGATE_TIMEOUT_MS);
		}, NAVIGATE_DELAY_MS);
	}

	function update() {
		frame = 0;
		if (dismissed || leaving) {
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
					track('getoond', { interactive: false });
				}
			}
		} else if (window.scrollY < HIDE_ABOVE_PX) {
			bar.classList.remove('is-visible');
		}

		ringFill.style.strokeDashoffset = String(100 - value * 100);
		// Only promise what scrolling does: unarmed, as on a short article or a page restored at its end,
		// scrolling down does not open the next article.
		setKicker(value >= ALMOST && armed ? KICKER.almost : KICKER.idle);
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
		if (!dismissed && !triggered && !armed && progress() < 1) {
			armed = true;
			schedule(); // The label may now promise the next article.
		}
	}

	link.addEventListener('click', (event) => {
		// A swipe can end in a click; that must neither open nor count as a click.
		if (swiped) {
			event.preventDefault();
			event.stopPropagation();
			swiped = false;
		} else if (isPlainClick(event)) {
			markLeave('geklikt');
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
		leaving = false;
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
