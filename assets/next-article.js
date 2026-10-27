/* Scroll behaviour and A/B test design: README. */
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
	// A gesture ends after this pause with no fingers down.
	const SETTLE_MS = 150;
	const FAST_PX_PER_MS = 1.5;
	// Input expires even if scroll events continue.
	const STEER_MS = 250;
	// End and modified keys jump to the footer or select text, so they never open.
	const SCROLL_KEYS = new Set(['ArrowDown', 'PageDown', ' ']);
	const SWIPE_START_PX = 6;
	// An unclear touch start may still straighten out before the swipe counts as sideways.
	const SWIPE_LOCK_PX = 24;
	const VARIANT_KEY = 'zw_staart_ab_variant';
	const RETURN_KEY = 'zw_staart_ab_return';
	const QUICK_RETURN_MS = 15000;
	const KICKER = {
		idle: 'Lees ook',
		phase2: 'Volgend artikel',
		ready: 'Scroll om te openen',
		loading: 'Volgende artikel laden\u2026', // ASCII avoids charset-dependent decoding.
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

	// Match the Plausible reporting timezone.
	const today = () => new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
	const testDay = today();

	// Assign daily; visitors without storage stay outside the test.
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

	// Non-interactive events preserve bounce rate; analytics failure must not block navigation.
	function send(name, props, { interactive = true } = {}) {
		try {
			window.plausible(name, { props: variant ? { variant, ...props } : props, interactive });
		} catch (e) {}
	}

	function track(action, options) {
		send(EVENT_NAME, { actie: action }, options);
	}

	const isPlainClick = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

	// Track each article separately so return detection works along a chain.
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
			// Attribute returns to the departure group, even across midnight.
			send('Recirculatie Terug', { via: left.via, variant: left.variant }, { interactive: false });
		}
	}

	if (variant) {
		// Include unreached tips and bfcache restores in the denominator.
		const countView = () => {
			send('Recirculatie Weergave', {}, { interactive: false });
			checkReturn();
		};
		countView();
		// Reload stale tabs and restores to use today's group.
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

	if (config.test && variant !== 'b') {
		return;
	}

	let next = pickNext();
	if (!next || isDismissed()) {
		return;
	}

	const ACTIVE_CLASS = 'zw-staart-has-next-article';

	// The theme places share buttons at the end of the article element.
	const post = article.closest('article') || article;
	// Exclude trailing embeds and related links from text progress.
	const lastParagraph = [...article.querySelectorAll('p')]
		.reverse()
		.find((p) => !p.closest('aside, figure, .not-prose, [hidden]') && p.textContent.trim() && p.offsetHeight > 0);
	// End phase 2 halfway through regional news to avoid opening at the share buttons.
	const isLatest = (el) => el.matches('aside') && /^laatste\s+nieuws\s+uit\b/i.test(el.querySelector('h2')?.textContent.trim() ?? '');
	let latest = post.nextElementSibling;
	while (latest && !isLatest(latest)) {
		latest = latest.nextElementSibling;
	}

	const bar = document.createElement('div');
	bar.className = 'zw-staart-next-article';
	bar.setAttribute('role', 'region');
	bar.setAttribute('aria-label', 'Volgende artikel voor jou');
	// Material Icons (filled), matching the theme.
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
		// Defer the decorative image until the bar appears.
		image.hidden = !next.image;
		image.removeAttribute('src');
	};
	render();
	document.body.appendChild(bar);

	let dismissed = false;
	let impressed = false;
	// History restores must return to the text before scrolling through again.
	let reading = performance.getEntriesByType?.('navigation')[0]?.type !== 'back_forward';
	let lastMotion = 0;
	let touches = 0;
	let lastInput = 0;
	let coasting = false;
	let ringFull = false;
	let pushedOn = false;
	let lastY = window.scrollY;
	let lastScrollAt = performance.now();
	let movedDown = false;
	let touchSteers = false;
	let touchY = 0;
	let touchX = 0;
	let armed = false;
	// Direction of the current gesture: '' until decided, then 'down' or 'other'.
	let heading = 'other';
	let swipeX = 0;
	let swipeY = 0;
	let touchFromFull = false;
	// The last swipe went down, so its coast is the reader's own.
	let swept = false;
	let settleTimer = 0;
	let frame = 0;
	let prefetchRule = null;
	let leaving = false;

	// Prefetch without running scripts; unsupported browsers ignore the rules.
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

	// Recompute progress after layout shifts; use the post end when regional news is absent.
	function phases() {
		const { top } = article.getBoundingClientRect();
		const textEnd = (lastParagraph || article).getBoundingClientRect().bottom;
		const end = (latest || post).getBoundingClientRect();
		const phaseEnd = latest ? (end.top + end.bottom) / 2 : end.bottom;
		const visibleBottom = window.innerHeight - bar.offsetHeight - closeTab.offsetHeight;
		const atBottom = window.scrollY >= document.documentElement.scrollHeight - window.innerHeight - 1;
		const p1 = clamp((visibleBottom - top) / Math.max(1, textEnd - top));
		let p2 = 0;
		if (p1 >= 1) {
			p2 = atBottom || phaseEnd <= textEnd ? 1 : clamp((visibleBottom - textEnd) / (phaseEnd - textEnd));
		}
		return { p1, p2 };
	}

	function deactivate() {
		dismissed = true;
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

	function openNext() {
		if (dismissed || leaving || document.hidden) {
			return;
		}
		leaving = true;
		bar.classList.add('is-visible', 'is-loading');
		ringFill.style.strokeDashoffset = '0';
		bar.style.setProperty('--zw-staart-next-article-fill', '1');
		setKicker(KICKER.loading);
		// Plausible sends with keepalive, so the event survives the navigation, as with clicks.
		track('doorgescrold');
		markLeave('doorgescrold');
		window.location.href = next.url;
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
		if (bar.classList.contains('is-visible')) {
			if (next.image && !image.hasAttribute('src')) {
				image.src = next.image;
			}
			if (prefetchRule?.dataset.url !== next.url) {
				prefetch(next.url);
			}
		}

		ringFull = phase.p1 >= 1;
		// After a history restore the disc stays empty until scrolling through works again.
		const phase2 = ringFull && reading;
		ringFill.style.strokeDashoffset = String(100 - phase.p1 * 100);
		bar.style.setProperty('--zw-staart-next-article-fill', phase2 ? phase.p2.toFixed(3) : '0');

		// Open on downward steering or a coast from a push begun with the ring full.
		// A fast flick begun in the text needs a rest and a new gesture.
		// A finger on a downward swipe steers without fresh touchmoves, which browsers throttle while scrolling.
		const swiping = touches === 1 && touchSteers && heading === 'down';
		const steered = swiping || (!coasting && (swept || Date.now() - lastInput < STEER_MS));
		if (down && (steered || pushedOn) && atEnd(phase)) {
			openNext();
			return;
		}

		const ready = isReady(phase);
		bar.classList.toggle('is-phase2', phase2);
		bar.classList.toggle('is-ready', ready);
		setKicker(ready ? KICKER.ready : phase2 ? KICKER.phase2 : KICKER.idle);
		// Recheck after settling; touchend handles fingers still down.
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

	// Require a visible bar so short articles cannot scroll through unseen.
	const atEnd = ({ p2 }) => p2 >= 1 && reading && !document.hidden && bar.classList.contains('is-visible');
	const isReady = (phase) => touches === 0 && Date.now() - lastMotion >= SETTLE_MS && atEnd(phase);

	// At page bottom a new gesture may open without a scroll event.
	function startGesture() {
		const phase = phases();
		reading = reading || phase.p1 < 1;
		const ready = isReady(phase);
		if (Date.now() - lastMotion >= SETTLE_MS) {
			coasting = false;
			pushedOn = false;
		}
		swept = false;
		lastMotion = Date.now();
		schedule();
		return ready;
	}

	const isFieldKey = (event) => event.target.closest?.('input, textarea, select, button, [contenteditable]');

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

	// A gesture's first pixels lock its direction, like the browser's axis lock.
	// Only a downward gesture steers, and opens if it began while ready.
	// A slow thumb arcs and wobbles, so a touch may go up to twice as much sideways.
	function startSwipe(ready) {
		armed = ready;
		heading = '';
		swipeX = 0;
		swipeY = 0;
	}

	function endSwipe() {
		armed = false;
		heading = 'other';
	}

	function swipe(dx, dy, thumb = false) {
		swipeX += dx;
		swipeY += dy;
		const distance = Math.hypot(swipeX, swipeY);
		if (heading || distance <= SWIPE_START_PX) {
			return;
		}
		if ((thumb ? 2 : 1) * swipeY > Math.abs(swipeX)) {
			heading = 'down';
			if (armed) {
				openNext();
			}
			armed = false;
		} else if (!thumb || distance > SWIPE_LOCK_PX) {
			heading = 'other';
			armed = false;
		}
	}

	function onTouchEnd(event) {
		swept = touches === 1 && touchSteers && heading === 'down';
		endSwipe();
		touches = event.touches.length;
		lastMotion = Date.now();
		schedule();
	}

	link.addEventListener('click', (event) => {
		// A tap that slipped into a scroll-through already navigates; skip its analytics event.
		if (leaving) {
			event.preventDefault();
			event.stopPropagation();
		} else if (isPlainClick(event)) {
			markLeave('geklikt');
		}
	});
	closeTab.addEventListener('click', () => dismiss('gesloten'));

	window.addEventListener(
		'scroll',
		() => {
			// Bound the sample window without rounding high-frequency scroll intervals.
			const now = performance.now();
			const speed = (window.scrollY - lastY) / Math.min(100, Math.max(4, now - lastScrollAt));
			coasting = coasting || speed >= FAST_PX_PER_MS;
			movedDown = movedDown || window.scrollY > lastY;
			lastY = window.scrollY;
			lastScrollAt = now;
			// Layout shifts after a rest must not inherit a previous push; a finger down keeps its own.
			if (touches === 0 && Date.now() - lastMotion >= SETTLE_MS) {
				pushedOn = false;
				swept = false;
				// Key repeats steer without a new gesture, so only input from before the rest is stale.
				if (lastInput <= lastMotion) {
					lastInput = 0;
				}
			}
			lastMotion = Date.now();
			schedule();
		},
		{ passive: true }
	);
	window.addEventListener('resize', schedule);
	window.addEventListener(
		'touchstart',
		(event) => {
			const ready = startGesture();
			// A slipping tap on the close tab must not open the next article.
			const onClose = event.target instanceof Node && closeTab.contains(event.target);
			touchSteers = scrollsPage(event) && !onClose;
			lastInput = 0;
			if (event.touches.length > 1 || onClose) {
				pushedOn = false;
			}
			touchX = event.touches[0].clientX;
			touchY = event.touches[0].clientY;
			startSwipe(ready && touchSteers);
			touchFromFull = ringFull;
			// A new finger catches the page, so an earlier fast flick no longer counts.
			coasting = false;
			touches = event.touches.length;
		},
		{ passive: true }
	);
	window.addEventListener(
		'touchmove',
		(event) => {
			if (event.defaultPrevented) {
				touchSteers = false;
				endSwipe();
			}
			const { clientX: x, clientY: y } = event.touches[0];
			if (event.touches.length === 1) {
				swipe(x - touchX, touchY - y, true);
				if (touchSteers && y < touchY && heading === 'down') {
					lastInput = Date.now();
					pushedOn = pushedOn || touchFromFull;
				}
			}
			touchX = x;
			touchY = y;
		},
		{ passive: true }
	);
	window.addEventListener('touchend', onTouchEnd, { passive: true });
	// Cancellation also revokes steering for the coast that follows.
	window.addEventListener(
		'touchcancel',
		(event) => {
			lastInput = 0;
			pushedOn = false;
			touchSteers = false;
			onTouchEnd(event);
		},
		{ passive: true }
	);
	// Trackpad pinch arrives as Ctrl+wheel; it must not steer.
	window.addEventListener(
		'wheel',
		(event) => {
			// Trackpad inertia emits wheel events; only a turn after a rest starts a new push.
			const isNew = Date.now() - lastMotion >= SETTLE_MS;
			const ready = startGesture();
			if (event.ctrlKey || !scrollsPage(event)) {
				endSwipe();
				return;
			}
			if (isNew) {
				startSwipe(ready);
			}
			// Mouse wheels may report lines or pages; trackpads report pixels.
			const scale = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? window.innerHeight : 1;
			swipe(event.deltaX * scale, event.deltaY * scale);
			if (event.deltaY > 0 && heading === 'down') {
				lastInput = Date.now();
				pushedOn = pushedOn || (isNew && ringFull);
			}
		},
		{ passive: true }
	);
	// Key repeats continue the original gesture.
	window.addEventListener('keydown', (event) => {
		if (isFieldKey(event)) {
			return;
		}
		// Jumps to the footer must not inherit an earlier push either.
		if (event.key === 'End' || (SCROLL_KEYS.has(event.key) && (event.shiftKey || event.metaKey || event.ctrlKey || event.altKey))) {
			lastInput = 0;
			pushedOn = false;
			swept = false;
			return;
		}
		if (!SCROLL_KEYS.has(event.key)) {
			return;
		}
		const ready = !event.repeat && startGesture();
		if (scrollsPage(event)) {
			lastInput = Date.now();
			pushedOn = pushedOn || (!event.repeat && ringFull);
			if (ready) {
				openNext();
			}
		}
	});
	// Returning to a tab requires another settling period and a new push.
	document.addEventListener('visibilitychange', () => {
		lastInput = 0;
		pushedOn = false;
		swept = false;
		lastMotion = Date.now();
		schedule();
	});
	// bfcache retains the loading state and stale reading history.
	window.addEventListener('pageshow', (event) => {
		if (!event.persisted) {
			return;
		}
		if (dismissed) {
			return;
		}
		leaving = false;
		bar.classList.remove('is-loading');
		reading = false;
		lastMotion = Date.now();
		touches = 0;
		endSwipe();
		touchFromFull = false;
		lastInput = 0;
		coasting = false;
		pushedOn = false;
		movedDown = false;
		lastY = window.scrollY;
		lastScrollAt = performance.now();
		next = pickNext();
		if (!next || isDismissed()) {
			deactivate();
			return;
		}
		// Withdraw stale rules before the browser re-evaluates them on restore.
		if (prefetchRule && prefetchRule.dataset.url !== next.url) {
			prefetchRule.remove();
			prefetchRule = null;
		}
		render();
		schedule();
	});

	update();
})();
