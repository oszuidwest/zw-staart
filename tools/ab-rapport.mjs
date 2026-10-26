#!/usr/bin/env node
/* A/B report; usage and decision rule: README. */

import { parseArgs } from 'node:util';
import { pathToFileURL } from 'node:url';

const ENDPOINT = 'https://stats.zuidwesttv.nl/api/v2/query';
const SITE_ID = 'zuidwestupdate.nl';
const MIN_DAYS = 42;
// Quick-return guardrail: 5 percentage points above a.
const MAX_EXTRA_RETURNS = 0.05;
// Student's t, two-sided 95%, df 1..30; Cornish-Fisher expansion for higher df.
const T_975 = [12.706, 4.303, 3.182, 2.776, 2.571, 2.447, 2.365, 2.306, 2.262, 2.228, 2.201, 2.179, 2.16, 2.145, 2.131,
	2.12, 2.11, 2.101, 2.093, 2.086, 2.08, 2.074, 2.069, 2.064, 2.06, 2.056, 2.052, 2.048, 2.045, 2.042];
const VARIANTS = ['a', 'b'];

function tCritical(df) {
	if (df <= T_975.length) {
		return T_975[df - 1];
	}
	const z = 1.959964;
	return z + (z ** 3 + z) / (4 * df) + (5 * z ** 5 + 16 * z ** 3 + 3 * z) / (96 * df ** 2) +
		(3 * z ** 7 + 19 * z ** 5 + 17 * z ** 3 - 15 * z) / (384 * df ** 3);
}
const key = (...parts) => parts.join('|');
const first = (rows, ...parts) => rows.get(key(...parts))?.[0] ?? 0;
const percent = (value, digits = 2) => `${(value * 100).toFixed(digits)}%`;
const signed = (value, digits = 2) => `${value >= 0 ? '+' : ''}${percent(value, digits)}`;

// Numerical Recipes approximation for the chi-square p-value.
function erfc(x) {
	const t = 1 / (1 + 0.5 * Math.abs(x));
	const y = t * Math.exp(-x * x - 1.26551223 + t * (1.00002368 + t * (0.37409196 + t * (0.09678418 + t * (-0.18628806 +
		t * (0.27886807 + t * (-1.13520398 + t * (1.48851587 + t * (-0.82215223 + t * 0.17087277)))))))));
	return x >= 0 ? y : 2 - y;
}

function mean(values) {
	return values.reduce((sum, value) => sum + value, 0) / values.length;
}

function stdev(values) {
	const average = mean(values);
	return Math.sqrt(values.reduce((sum, value) => sum + (value - average) ** 2, 0) / (values.length - 1));
}

function plausible(apiKey, dateRange) {
	return async (goal, dimensions, metrics = ['events'], extraFilters = []) => {
		const response = await fetch(ENDPOINT, {
			method: 'POST',
			headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' },
			body: JSON.stringify({
				site_id: SITE_ID,
				metrics,
				date_range: dateRange,
				dimensions,
				filters: [['is', 'event:goal', [goal]], ...extraFilters],
				pagination: { limit: 10000 },
			}),
		});
		if (!response.ok) {
			// Proxy errors may lack a JSON body.
			const detail = await response.json().then((body) => body.error, () => null);
			throw new Error(`Plausible: ${detail ?? response.status}`);
		}
		const body = await response.json();
		return new Map(body.results.map((row) => [key(...row.dimensions), row.metrics]));
	};
}

/** Returns metric maps keyed by "day|variant|..." or "device|variant". */
export async function collect(query) {
	const navigated = [['is', 'event:props:actie', ['geklikt', 'doorgescrold']]];
	const dismissed = [['is', 'event:props:actie', ['gesloten', 'weggeveegd']]];
	const byDay = ['time:day', 'event:props:variant'];
	const byDevice = ['visit:device', 'event:props:variant'];
	const [views, tips, scroll, dismissals, returns, deviceViews, deviceTips, deviceScroll] = await Promise.all([
		query('Recirculatie Weergave', byDay, ['events', 'visitors']),
		query('Recirculatie Artikel', byDay),
		query('Recirculatie Scroll', byDay, ['events'], navigated),
		query('Recirculatie Scroll', byDay, ['events'], dismissed),
		query('Recirculatie Terug', [...byDay, 'event:props:via']),
		query('Recirculatie Weergave', byDevice),
		query('Recirculatie Artikel', byDevice),
		query('Recirculatie Scroll', byDevice, ['events'], navigated),
	]);
	return { views, tips, scroll, dismissals, returns, deviceViews, deviceTips, deviceScroll };
}

// Chi-square upper tail: closed forms for df 1 and 2, Wilson-Hilferty otherwise.
function chiSquareP(x, df) {
	if (df === 1) {
		return erfc(Math.sqrt(x / 2));
	}
	if (df === 2) {
		return Math.exp(-x / 2);
	}
	const z = (Math.cbrt(x / df) - (1 - 2 / (9 * df))) / Math.sqrt(2 / (9 * df));
	return erfc(z / Math.SQRT2) / 2;
}

export function analyse(data) {
	const days = [...new Set([...data.views.keys()].map((k) => k.split('|')[0]))]
		.filter((day) => VARIANTS.every((variant) => first(data.views, day, variant) > 0))
		.sort();
	const sum = (perDay, variant) => days.reduce((total, day) => total + perDay(day, variant), 0);
	const views = (day, variant) => first(data.views, day, variant);
	const visitors = (day, variant) => data.views.get(key(day, variant))?.[1] ?? 0;
	const hits = (day, variant) => first(data.tips, day, variant) + first(data.scroll, day, variant);
	const returns = (day, variant) =>
		[...data.returns].filter(([k]) => k.startsWith(`${key(day, variant)}|`)).reduce((total, [, metrics]) => total + metrics[0], 0);

	const totals = {};
	for (const variant of VARIANTS) {
		const recirculated = sum(hits, variant);
		const returned = sum(returns, variant);
		totals[variant] = {
			views: sum(views, variant),
			recirculated,
			rate: days.length ? recirculated / sum(views, variant) : 0,
			exposed: sum(visitors, variant),
			returns: returned,
			returnRate: recirculated ? returned / recirculated : 0,
			dismissed: sum((day, v) => first(data.dismissals, day, v), variant),
		};
	}

	// Pair by day to cancel variation in the news mix.
	const differences = days.map((day) => hits(day, 'b') / views(day, 'b') - hits(day, 'a') / views(day, 'a'));
	let paired = null;
	if (differences.length >= 2) {
		const average = mean(differences);
		const margin = (tCritical(differences.length - 1) * stdev(differences)) / Math.sqrt(differences.length);
		paired = { days: differences.length, mean: average, low: average - margin, high: average + margin };
	}

	// Sum daily SRM statistics so opposite imbalances cannot cancel out (one df per day).
	const chi2 = days.reduce((total, day) => {
		const counts = VARIANTS.map((variant) => visitors(day, variant));
		const expected = (counts[0] + counts[1]) / 2;
		return total + (expected ? counts.reduce((part, count) => part + (count - expected) ** 2 / expected, 0) : 0);
	}, 0);
	return { days, totals, paired, srmP: days.length ? chiSquareP(chi2, days.length) : 1, returnsByRoute: routes(data.returns, days) };
}

function routes(returns, days) {
	const byRoute = new Map();
	for (const [k, metrics] of returns) {
		const [day, variant, via] = k.split('|');
		if (days.includes(day)) {
			byRoute.set(`${variant}/${via}`, (byRoute.get(`${variant}/${via}`) ?? 0) + metrics[0]);
		}
	}
	return [...byRoute].sort();
}

export function decide({ totals, paired, srmP }) {
	if (srmP < 0.001) {
		return 'ONGELDIG: de groepen zijn niet 50/50 verdeeld; zoek eerst de oorzaak.';
	}
	if (!paired || paired.days < MIN_DAYS) {
		return `NOG GEEN BESLUIT: ${paired?.days ?? 0} van minimaal ${MIN_DAYS} dagen met beide groepen.`;
	}
	if (totals.b.returnRate > totals.a.returnRate + MAX_EXTRA_RETURNS) {
		return 'A: de balk stuurt te veel lezers door die snel terugkomen (guardrail).';
	}
	if (paired.low > 0) {
		return 'B: de balk levert aantoonbaar meer doorlezers op.';
	}
	if (paired.high < 0) {
		return 'A: het leestipsblok levert aantoonbaar meer doorlezers op.';
	}
	return 'GEEN AANTOONBAAR VERSCHIL: houd het leestipsblok (Doorscrollen uit).';
}

export function report(data, result) {
	const { days, totals, paired, srmP, returnsByRoute } = result;
	const lines = [];
	const span = days.length ? `${days[0]} t/m ${days.at(-1)}` : 'geen data';
	lines.push(`A/B-test leestips (a) tegen doorscroll-balk (b), ${span}, ${days.length} dagen met beide groepen`, '');
	lines.push('groep  bezoekers  weergaven  doorlezers  per weergave  snel terug');
	for (const variant of VARIANTS) {
		const t = totals[variant];
		lines.push(`${variant.padEnd(5)}  ${String(t.exposed).padStart(9)}  ${String(t.views).padStart(9)}  ${String(t.recirculated).padStart(10)}  ${percent(t.rate).padStart(12)}  ${percent(t.returnRate, 1).padStart(10)}`);
	}
	lines.push('');
	if (totals.a.rate) {
		lines.push(`verschil b ten opzichte van a: ${signed(totals.b.rate / totals.a.rate - 1, 1)}`);
	}
	if (paired) {
		lines.push(`gepaard per dag: ${signed(paired.mean)} punt, 95%-interval ${signed(paired.low)} tot ${signed(paired.high)}`);
	}
	lines.push(`verdeling 50/50 per dag (SRM): p = ${srmP.toFixed(3)}`);
	lines.push(`balk gesloten of weggeveegd: ${totals.b.dismissed}`);
	lines.push(`snel terug per route: ${returnsByRoute.map(([route, count]) => `${route}: ${count}`).join(', ')}`);
	lines.push('', 'apparaat (alle dagen, ter informatie)   a per weergave  b per weergave');
	const devices = [...new Set([...data.deviceViews.keys()].map((k) => k.split('|')[0]))].sort();
	for (const device of devices) {
		const rates = VARIANTS.map((variant) => {
			const views = first(data.deviceViews, device, variant);
			const hits = first(data.deviceTips, device, variant) + first(data.deviceScroll, device, variant);
			return (views ? percent(hits / views) : '-').padStart(14);
		});
		lines.push(`${device.padEnd(38)}  ${rates.join('  ')}`);
	}
	lines.push('', `BESLUIT: ${decide(result)}`);
	return lines.join('\n');
}

async function main() {
	const { values } = parseArgs({ options: { van: { type: 'string' }, tot: { type: 'string' } } });
	if (!values.van || !values.tot) {
		throw new Error('Gebruik: node tools/ab-rapport.mjs --van JJJJ-MM-DD --tot JJJJ-MM-DD');
	}
	// Enforce the prespecified test window; shorter windows give no decision.
	// Round-trip dates: Date.parse() normalizes invalid dates such as February 30.
	const isDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(value) && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().startsWith(value);
	const windowDays = (Date.parse(values.tot) - Date.parse(values.van)) / 86400000 + 1;
	if (!isDate(values.van) || !isDate(values.tot) || !(windowDays >= 1 && windowDays <= MIN_DAYS)) {
		throw new Error(`Kies --van en --tot als JJJJ-MM-DD, binnen ${MIN_DAYS} dagen: de vastgelegde duur van de test.`);
	}
	// Exclude partial days from the fixed-duration test.
	const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Europe/Amsterdam' }).format(new Date());
	if (values.tot >= today) {
		throw new Error(`De laatste dag (${values.tot}) is nog niet voorbij; gebruik uiterlijk gisteren.`);
	}
	if (!process.env.PLAUSIBLE_API_KEY) {
		throw new Error('Zet eerst PLAUSIBLE_API_KEY.');
	}
	const data = await collect(plausible(process.env.PLAUSIBLE_API_KEY, [values.van, values.tot]));
	console.log(report(data, analyse(data)));
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
	main().catch((error) => {
		console.error(error.message);
		process.exit(1);
	});
}
