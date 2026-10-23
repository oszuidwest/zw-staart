/**
 * ZuidWest Staart: the next article bar ("Doorscrollen") and its A/B test against the reading tips
 * block. The README describes the behaviour and the test design.
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
	const HIDE_ABOVE_PX = 200;
	// Leave this long after the scroll-through at the latest, also when analytics does not answer.
	const NAVIGATE_TIMEOUT_MS = 1500;
	const SWIPE_START_PX = 6;
	const SWIPE_CLOSE_PX = 60;
	const VARIANT_KEY = 'zw_staart_ab_variant';
	const RETURN_KEY = 'zw_staart_ab_return';
	// Guardrail: coming back within this time means the visitor did not want the article.
	const QUICK_RETURN_MS = 15000;
	const KICKER = {
		idle: 'Lees ook',
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

	// The test day, Amsterdam time like the Plausible statistics.
	const today = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
	const testDay = today();

	// Draws a group once per day. Without storage there is no fixed group: such a visitor stays
	// outside the test.
	function drawVariant() {
		try {
			const [day, drawn] = (localStorage.getItem(VARIANT_KEY) || '').split(':');
			if (day === testDay && (drawn === 'a' || drawn === 'b')) {
				return drawn;
			}
			const group = Math.random() < 0.5 ? 'a' : 'b';
			localStorage.setItem(VARIANT_KEY, `${testDay}:${group}`);
			return group;
		} catch (e) {
			return '';
		}
	}

	const variant = config.test ? drawVariant() : '';

	// Events the visitor did not cause are non-interactive, so they do not change the bounce rate.
	// Analytics never blocks the bar: when it is missing or fails, the callback runs right away.
	function send(name, props, { callback, interactive = true } = {}) {
		try {
			window.plausible(name, { props: variant ? { variant, ...props } : props, callback, interactive });
		} catch (e) {
			if (callback) {
				callback();
			}
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
		const countView = () => {
			send('Recirculatie Weergave', {}, { interactive: false });
			checkReturn();
		};
		countView();
		// A page from an earlier day, restored or left open in a tab, loads again with today's group.
		const isStale = () => today() !== testDay;
		window.addEventListener('pageshow', (event) => {
			if (event.persisted) {
				if (isStale()) {
					location.reload();
					return;
				}
				countView();
			}
		});
		document.addEventListener('visibilitychange', () => {
			if (document.visibilityState === 'visible' && isStale()) {
				location.reload();
			}
		});
		document.querySelectorAll('#zw-staart-top-posts-list a').forEach((tip) => {
			tip.classList.add(`plausible-event-variant=${variant}`);
			tip.addEventListener('click', (event) => isPlainClick(event) && markLeave('leestip'));
		});
	}

	// In the test only group b gets the bar; group a and visitors without a group keep the block.
	if (config.test && variant !== 'b') {
		return;
	}

	let next = pickNext();
	if (!next || isDismissed()) {
		return;
	}

	// The bar replaces the reading tips block, which the stylesheet hides while this class is set. It
	// is set once the bar appears, so a reader who never gets the bar keeps the block.
	const ACTIVE_CLASS = 'zw-staart-has-next-article';

	// The end of the text is its last element: not the reading tips block appended to the content, nor
	// its script, so the wheel does not jump when the block hides. Looked up each time, as the reading
	// tips script may remove a "Lees ook" link at the end.
	function textEnd() {
		let el = article.lastElementChild;
		while (el && (el.id === 'zw-staart-top-posts-list' || el.tagName === 'SCRIPT')) {
			el = el.previousElementSibling;
		}
		return el || article;
	}

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
		// Decorative: the title next to it says the same. Loaded once the bar shows (see update()).
		image.hidden = !next.image;
		image.removeAttribute('src');
	};
	render();
	document.body.appendChild(bar);

	let dismissed = false;
	let impressed = false;
	let armed = false;
	// Set once the reader touches the page while still in the text on this view, so the end was
	// reached by reading rather than by a restore at the bottom.
	let reading = false;
	// Only a scroll opens the next article, not a resize or the first draw.
	let scrolled = false;
	let swiped = false;
	let frame = 0;
	// Set once the next article opens; from then on the page is on its way.
	let leaving = false;
	// Increased on every departure and on a bfcache restore, so a late callback or timeout of an
	// earlier departure cannot navigate the restored page.
	let departure = 0;

	function setKicker(text) {
		if (kicker.textContent !== text) {
			kicker.textContent = text;
		}
	}

	// 0 at the start of the text, 1 once its end is visible above the bar.
	function progress() {
		const { top } = article.getBoundingClientRect();
		const { bottom } = textEnd().getBoundingClientRect();
		const visibleBottom = window.innerHeight - bar.offsetHeight;
		return Math.min(1, Math.max(0, (visibleBottom - top) / Math.max(1, bottom - top)));
	}

	// Hides the bar for good on this page and brings the reading tips block back. A next article that
	// is still loading stays here: closing means "stop met doorladen".
	function deactivate() {
		dismissed = true;
		departure++;
		bar.classList.remove('is-visible');
		document.documentElement.classList.remove(ACTIVE_CLASS);
	}

	function dismiss(action) {
		try {
			sessionStorage.setItem(DISMISSED_KEY, '1');
		} catch (e) {}
		track(action);
		deactivate();
	}

	// Like swiping to the next video: the scroll-through is the switch. Count it, then load the next
	// article as a regular page as soon as analytics answers (or after a timeout); the closed ring and
	// the label show it is on its way.
	function openNext() {
		leaving = true;
		bar.classList.add('is-visible', 'is-loading');
		ringFill.style.strokeDashoffset = '0';
		setKicker(KICKER.loading);
		const current = ++departure;
		// Recorded only when the page really leaves, so a cancelled scroll-through leaves no departure.
		const leave = () => {
			if (current === departure) {
				departure++;
				markLeave('doorgescrold');
				window.location.href = next.url;
			}
		};
		track('doorgescrold', { callback: leave });
		window.setTimeout(leave, NAVIGATE_TIMEOUT_MS);
	}

	function update() {
		frame = 0;
		const byScroll = scrolled;
		scrolled = false;
		if (dismissed || leaving) {
			return;
		}
		const value = progress();

		if (value >= SHOW_AT && window.scrollY >= HIDE_ABOVE_PX) {
			if (!bar.classList.contains('is-visible')) {
				bar.classList.add('is-visible');
				if (!impressed) {
					impressed = true;
					document.documentElement.classList.add(ACTIVE_CLASS);
					track('getoond', { interactive: false });
				}
			}
		} else if (window.scrollY < HIDE_ABOVE_PX) {
			bar.classList.remove('is-visible');
		}
		// Readers who never reach the bar do not download its image; on mobile the stylesheet hides it,
		// so lazy loading skips it there.
		if (next.image && !image.hasAttribute('src') && bar.classList.contains('is-visible')) {
			image.src = next.image;
		}

		ringFill.style.strokeDashoffset = String(100 - value * 100);
		// Only promise what scrolling does: unarmed, as on a page restored at its end, scrolling down
		// does not open the next article.
		setKicker(value >= ALMOST && armed ? KICKER.almost : KICKER.idle);
		// Only open the next article while the bar is on screen, so a short article never jumps away unseen.
		if (byScroll && value >= 1 && armed && bar.classList.contains('is-visible')) {
			openNext();
		}
	}

	function schedule() {
		if (!frame) {
			frame = window.requestAnimationFrame(update);
		}
	}

	// Only a real scroll action by the reader, with the bar in view, may open the next article: a page
	// that the back button restores at the bottom must not load the next article unseen. One swipe from
	// the top of a short article past its end does not open it either, but the next one does: the reader
	// started in the text, and now sees the bar.
	function onInteract() {
		if (armed) {
			return;
		}
		reading = reading || progress() < 1;
		if (reading && bar.classList.contains('is-visible')) {
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

	window.addEventListener(
		'scroll',
		() => {
			scrolled = true;
			schedule();
		},
		{ passive: true }
	);
	window.addEventListener('resize', schedule);
	['wheel', 'keydown', 'pointerdown'].forEach((type) => window.addEventListener(type, onInteract, { passive: true }));
	// Back via the bfcache: the page is restored as it was left, loading state included. Reset it and
	// re-check the cookie and session, as the visitor may have read the tip or closed the bar since.
	window.addEventListener('pageshow', (event) => {
		if (!event.persisted) {
			return;
		}
		departure++;
		if (dismissed) {
			return;
		}
		leaving = false;
		bar.classList.remove('is-loading');
		armed = false;
		reading = false;
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
