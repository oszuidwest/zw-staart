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
	const HIDE_ABOVE_PX = 200;
	// A gesture is over once the page has not moved this long and no finger touches the screen.
	const SETTLE_MS = 300;
	// Faster than this (about 1.5 screens a second) the page coasts after a flick or a spin of the wheel,
	// rather than being steered by the reader.
	const FAST_PX_PER_MS = 1.5;
	// A scroll this soon after a turn of the wheel or a scroll key is steered by it.
	const STEER_MS = 250;
	// Keys that scroll the page down; only these can start a scroll-through by keyboard.
	const SCROLL_KEYS = new Set(['ArrowDown', 'PageDown', 'End', ' ']);
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
		phase2: 'Volgend artikel',
		ready: 'Scroll opnieuw om te openen',
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

	// The post ends after the share buttons, which the theme puts at the end of its article element.
	const post = article.closest('article') || article;
	// Phase 1 of the wheel ends at the last paragraph of the text: not a caption, an embed, the reading
	// tips or the "Lees ook" links some articles end with.
	const lastParagraph = [...article.querySelectorAll('p')]
		.reverse()
		.find((p) => !p.closest('aside, figure, .not-prose, [hidden]') && p.textContent.trim() && p.offsetHeight > 0);
	// Phase 2 ends at the theme's regional news block below the post ("Laatste nieuws uit Zundert"),
	// or without it at the end of the post.
	let latest = post.nextElementSibling;
	while (latest && !(latest.matches('aside') && /^laatste\s+nieuws\s+uit\b/i.test(latest.querySelector('h2')?.textContent.trim() ?? ''))) {
		latest = latest.nextElementSibling;
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
	const closeTab = bar.querySelector('.zw-staart-next-article-close');
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
	// Whether this view may scroll through. A page the back button restores starts without it, so it
	// does not load the next article unseen; the reader then first scrolls back to before the end.
	let reading = performance.getEntriesByType?.('navigation')[0]?.type !== 'back_forward';
	// The last scroll or wheel movement, and the fingers on the screen.
	let lastMotion = 0;
	let touches = 0;
	// The last turn of the wheel, scroll key or finger moving up, and whether the page moved fast since
	// the last rest.
	let lastInput = 0;
	let coasting = false;
	// Where and when the page last scrolled, to measure the speed, and whether it moved down since the
	// last frame.
	let lastY = window.scrollY;
	let lastScrollAt = performance.now();
	let movedDown = false;
	// A touch that starts while ready opens the next article once the finger moves up, so a tap never does.
	let touchReady = false;
	let touchStartY = 0;
	let touchY = 0;
	let settleTimer = 0;
	let swiped = false;
	let frame = 0;
	// The prefetch rule for the current next article; next can change after a bfcache restore.
	let prefetchRule = null;
	// Set once the next article opens; from then on the page is on its way.
	let leaving = false;
	// Increased on every departure and on a bfcache restore, so a late callback or timeout of an
	// earlier departure cannot navigate the restored page.
	let departure = 0;

	// Best-effort prefetch of the next article's HTML; it runs no scripts of that page. Browsers without
	// speculation rules ignore the script. A new URL replaces the old rule, so a stale one is not fetched
	// again.
	function prefetch(url) {
		prefetchRule?.remove();
		prefetchRule = document.createElement('script');
		prefetchRule.type = 'speculationrules';
		prefetchRule.dataset.url = url;
		prefetchRule.textContent = JSON.stringify({ prefetch: [{ source: 'list', urls: [url] }] });
		document.head.appendChild(prefetchRule);
	}

	function setKicker(text) {
		if (kicker.textContent !== text) {
			kicker.textContent = text;
		}
	}

	const clamp = (value) => Math.min(1, Math.max(0, value));

	// The wheel in two phases, measured each time as the page may still change. Phase 1 (the ring) runs
	// from the start of the text to the last paragraph: full once that is above the bar and its close
	// tab. Phase 2 (the inside of the wheel) runs from there to the regional news block. A page that
	// cannot scroll that far ends phase 2 at the bottom.
	function phases() {
		const { top } = article.getBoundingClientRect();
		const textEnd = (lastParagraph || article).getBoundingClientRect().bottom;
		const phaseEnd = latest ? latest.getBoundingClientRect().top : post.getBoundingClientRect().bottom;
		const visibleBottom = window.innerHeight - bar.offsetHeight - closeTab.offsetHeight;
		const atBottom = window.scrollY >= document.documentElement.scrollHeight - window.innerHeight - 1;
		const p1 = clamp((visibleBottom - top) / Math.max(1, textEnd - top));
		let p2 = 0;
		if (p1 >= 1) {
			p2 = atBottom || phaseEnd <= textEnd ? 1 : clamp((visibleBottom - textEnd) / (phaseEnd - textEnd));
		}
		return { p1, p2 };
	}

	// Hides the bar for good on this page and brings the reading tips block back. A next article that
	// is still loading stays here and its prefetch is withdrawn: closing means "stop met doorladen".
	function deactivate() {
		dismissed = true;
		departure++;
		window.clearTimeout(settleTimer);
		bar.classList.remove('is-visible');
		document.documentElement.classList.remove(ACTIVE_CLASS);
		prefetchRule?.remove();
	}

	function dismiss(action) {
		try {
			sessionStorage.setItem(DISMISSED_KEY, '1');
		} catch (e) {}
		track(action);
		deactivate();
	}

	// Like swiping to the next video: the scroll-through is the switch. Count it, then load the next
	// article as a regular page as soon as analytics answers (or after a timeout); the full wheel and
	// the label show it is on its way.
	function openNext() {
		leaving = true;
		bar.classList.add('is-visible', 'is-loading');
		ringFill.style.strokeDashoffset = '0';
		bar.style.setProperty('--zw-staart-next-article-fill', '1');
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
		if (dismissed || leaving) {
			return;
		}
		const phase = phases();
		const down = movedDown;
		movedDown = false;

		if (phase.p1 >= SHOW_AT && window.scrollY >= HIDE_ABOVE_PX) {
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
		// The image and the prefetch start only once the bar shows; on mobile the stylesheet hides the
		// image, so lazy loading skips it there.
		if (bar.classList.contains('is-visible')) {
			if (next.image && !image.hasAttribute('src')) {
				image.src = next.image;
			}
			if (prefetchRule?.dataset.url !== next.url) {
				prefetch(next.url);
			}
		}

		ringFill.style.strokeDashoffset = String(100 - phase.p1 * 100);
		bar.style.setProperty('--zw-staart-next-article-fill', phase.p2.toFixed(3));

		// Scrolling on into the full wheel opens the next article right away while the reader steers: a
		// finger moving on the screen, or a turn of the wheel or a scroll key just now without a fast spin.
		// The coast after a fast flick does not; it waits for the page to come to rest and a new gesture
		// (see startGesture()). A scroll the reader did not make, also under a finger resting on the
		// screen, never opens anything.
		const steered = Date.now() - lastInput < STEER_MS && (touches > 0 || !coasting);
		if (down && steered && !document.hidden && atEnd(phase)) {
			openNext();
			return;
		}

		// Only promise what scrolling does: not while the gesture that reached the end still moves the page.
		const ready = isReady(phase);
		bar.classList.toggle('is-phase2', phase.p1 >= 1);
		bar.classList.toggle('is-ready', ready);
		setKicker(ready ? KICKER.ready : phase.p1 >= 1 ? KICKER.phase2 : KICKER.idle);
		// Look again once the page has come to rest, also when nothing scrolls. While a finger is down,
		// its touchend looks again.
		window.clearTimeout(settleTimer);
		const delay = lastMotion + SETTLE_MS - Date.now();
		if (atEnd(phase) && touches === 0 && delay > 0) {
			settleTimer = window.setTimeout(schedule, delay);
		}
	}

	function schedule() {
		if (!frame) {
			frame = window.requestAnimationFrame(update);
		}
	}

	// The end of phase 2 counts only while the bar is on screen, so a short article never jumps away
	// unseen.
	const atEnd = ({ p2 }) => p2 >= 1 && reading && bar.classList.contains('is-visible');
	const isReady = (phase) =>
		!dismissed && !leaving && !document.hidden && touches === 0 && Date.now() - lastMotion >= SETTLE_MS && atEnd(phase);

	// A swipe, wheel or scroll key by the reader. Before the last paragraph it marks this view as read.
	// Returns whether it started at the end of phase 2 with the page at rest: then a gesture down is the
	// deliberate scroll that opens the next article. It opens on the gesture itself, not on the scroll
	// it causes, so it also works at the bottom of the page, where nothing scrolls any more, and a scroll
	// the reader did not make never opens anything. Measured afresh, as the page may have changed since
	// the last frame.
	function startGesture() {
		const phase = phases();
		reading = reading || phase.p1 < 1;
		const ready = isReady(phase);
		// A gesture after a rest starts steered again.
		if (Date.now() - lastMotion >= SETTLE_MS) {
			coasting = false;
		}
		lastMotion = Date.now();
		schedule();
		return ready;
	}

	// Keys typed into a field or pressed on a button do not scroll the page.
	const isFieldKey = (event) => event.target.closest?.('input, textarea, select, button, [contenteditable]');

	// A gesture the page handles itself, or one that scrolls a panel inside the page further down (an
	// embed, a wide table), does not scroll the page.
	function scrollsPage(event) {
		if (event.defaultPrevented) {
			return false;
		}
		for (let el = event.target; el instanceof Element && el !== document.body; el = el.parentElement) {
			const { overflowY } = getComputedStyle(el);
			if ((overflowY === 'auto' || overflowY === 'scroll') && el.scrollTop + el.clientHeight < el.scrollHeight - 1) {
				return false;
			}
		}
		return true;
	}

	function onTouchEnd(event) {
		touches = event.touches.length;
		lastMotion = Date.now();
		schedule();
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
		// A tap on the close button must close, not start a swipe that swallows the click.
		if (event.pointerType === 'touch' && !event.target.closest('.zw-staart-next-article-close')) {
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
			// The speed since the last scroll, over at most 100 ms: one turn of the wheel without smooth
			// scrolling counts as steered, a jump of a page or the coast after a flick as fast. Measured
			// precisely, as screens at 120 Hz scroll every 8 ms.
			const now = performance.now();
			const speed = (window.scrollY - lastY) / Math.min(100, Math.max(4, now - lastScrollAt));
			coasting = coasting || speed >= FAST_PX_PER_MS;
			movedDown = movedDown || window.scrollY > lastY;
			lastY = window.scrollY;
			lastScrollAt = now;
			lastMotion = Date.now();
			schedule();
		},
		{ passive: true }
	);
	window.addEventListener('resize', schedule);
	window.addEventListener(
		'touchstart',
		(event) => {
			touchReady = startGesture() && scrollsPage(event);
			touchStartY = event.touches[0].clientY;
			touchY = touchStartY;
			touches = event.touches.length;
		},
		{ passive: true }
	);
	window.addEventListener(
		'touchmove',
		(event) => {
			// A finger moving up scrolls the page down: the reader steers.
			if (event.touches[0].clientY < touchY) {
				lastInput = Date.now();
			}
			touchY = event.touches[0].clientY;
			if (touchReady && event.touches[0].clientY < touchStartY - SWIPE_START_PX) {
				touchReady = false;
				if (!dismissed && !leaving) {
					openNext();
				}
			}
		},
		{ passive: true }
	);
	window.addEventListener('touchend', onTouchEnd, { passive: true });
	window.addEventListener('touchcancel', onTouchEnd, { passive: true });
	// A pinch on a trackpad arrives as a wheel with Ctrl and zooms instead.
	window.addEventListener(
		'wheel',
		(event) => {
			if (!event.ctrlKey) {
				lastInput = Date.now();
			}
			return startGesture() && event.deltaY > 0 && !event.ctrlKey && scrollsPage(event) && openNext();
		},
		{ passive: true }
	);
	// Shift+Space scrolls up. A held key repeats and steers, but it is still the press that started
	// before the end was ready.
	window.addEventListener('keydown', (event) => {
		if (!SCROLL_KEYS.has(event.key) || (event.key === ' ' && event.shiftKey) || isFieldKey(event)) {
			return;
		}
		lastInput = Date.now();
		if (!event.repeat && startGesture() && scrollsPage(event)) {
			openNext();
		}
	});
	// Back in a hidden tab, the page first comes to rest again.
	document.addEventListener('visibilitychange', () => {
		lastMotion = Date.now();
		schedule();
	});
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
		reading = false;
		lastMotion = Date.now();
		touches = 0;
		touchReady = false;
		lastInput = 0;
		coasting = false;
		movedDown = false;
		lastY = window.scrollY;
		lastScrollAt = performance.now();
		next = pickNext();
		if (!next || isDismissed()) {
			deactivate();
			return;
		}
		// The restore makes the browser look at the rules again: withdraw the one for the old candidate.
		if (prefetchRule && prefetchRule.dataset.url !== next.url) {
			prefetchRule.remove();
			prefetchRule = null;
		}
		render();
		schedule();
	});

	update();
})();
