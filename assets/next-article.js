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
	const SETTLE_MS = 300;
	const FAST_PX_PER_MS = 1.5;
	// Input expires even if scroll events continue.
	const STEER_MS = 250;
	const SCROLL_KEYS = new Set(['ArrowDown', 'PageDown', 'End', ' ']);
	// Navigate even if analytics never calls back.
	const NAVIGATE_TIMEOUT_MS = 1500;
	const SWIPE_START_PX = 6;
	const SWIPE_CLOSE_PX = 60;
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
	let touchStartY = null;
	let touchFromFull = false;
	let settleTimer = 0;
	let swiped = false;
	let frame = 0;
	let prefetchRule = null;
	let leaving = false;
	// Invalidate pending navigation callbacks on departure, dismissal and bfcache restore.
	let departure = 0;

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
		departure++;
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
		const current = ++departure;
		// Record departure only when navigation actually starts.
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

		// Open on downward steering or a coast from a push begun with the ring full.
		// A fast flick begun in the text needs a rest and a new gesture.
		const phase2 = phase.p1 >= 1;
		ringFull = phase2;
		const steered = Date.now() - lastInput < STEER_MS && (touches > 0 || !coasting);
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

	function onTouchEnd(event) {
		touches = event.touches.length;
		lastMotion = Date.now();
		schedule();
	}

	link.addEventListener('click', (event) => {
		// Suppress the click synthesized after a swipe, including its analytics event.
		if (swiped) {
			event.preventDefault();
			event.stopPropagation();
			swiped = false;
		} else if (isPlainClick(event)) {
			markLeave('geklikt');
		}
	});
	closeTab.addEventListener('click', () => dismiss('gesloten'));

	let startY = null;
	let distance = 0;
	bar.addEventListener('pointerdown', (event) => {
		swiped = false;
		if (event.pointerType === 'touch' && !closeTab.contains(event.target)) {
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
			// Capture only after a swipe starts, preserving ordinary link taps.
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
			// Bound the sample window without rounding high-frequency scroll intervals.
			const now = performance.now();
			const speed = (window.scrollY - lastY) / Math.min(100, Math.max(4, now - lastScrollAt));
			coasting = coasting || speed >= FAST_PX_PER_MS;
			movedDown = movedDown || window.scrollY > lastY;
			lastY = window.scrollY;
			lastScrollAt = now;
			// Layout shifts after a rest must not inherit a previous push.
			if (Date.now() - lastMotion >= SETTLE_MS) {
				pushedOn = false;
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
			// The bar handles its own touches; only a moving finger outside it may steer.
			touchSteers = scrollsPage(event) && !(event.target instanceof Node && bar.contains(event.target));
			lastInput = 0;
			if (event.touches.length > 1) {
				pushedOn = false;
			}
			touchY = event.touches[0].clientY;
			touchStartY = ready && touchSteers ? touchY : null;
			touchFromFull = ringFull;
			touches = event.touches.length;
		},
		{ passive: true }
	);
	window.addEventListener(
		'touchmove',
		(event) => {
			if (event.defaultPrevented) {
				touchSteers = false;
				touchStartY = null;
			}
			const y = event.touches[0].clientY;
			const oneFinger = event.touches.length === 1;
			if (oneFinger && touchSteers && y < touchY) {
				lastInput = Date.now();
				pushedOn = pushedOn || touchFromFull;
			}
			touchY = y;
			if (oneFinger && touchStartY !== null && y < touchStartY - SWIPE_START_PX) {
				touchStartY = null;
				openNext();
			}
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
			if (event.deltaY > 0 && !event.ctrlKey && scrollsPage(event)) {
				lastInput = Date.now();
				pushedOn = pushedOn || (isNew && ringFull);
				if (ready) {
					openNext();
				}
			}
		},
		{ passive: true }
	);
	// Key repeats continue the original gesture.
	window.addEventListener('keydown', (event) => {
		if (!SCROLL_KEYS.has(event.key) || (event.key === ' ' && event.shiftKey) || isFieldKey(event)) {
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
	// Returning to a tab requires another settling period.
	document.addEventListener('visibilitychange', () => {
		lastMotion = Date.now();
		schedule();
	});
	// bfcache retains the loading state and stale reading history.
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
		touchStartY = null;
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
