# ZuidWest Staart (van artikel)

WordPress-plugin voor Streekomroep ZuidWest die de staart van artikelen beheert met dynamische content om betrokkenheid te verhogen.

## Wat doet deze plugin?

ZuidWest Staart voegt automatisch een van de volgende blokken toe onder elk artikel:

- **Leestips voor jou** (50% kans): Toont de top 5 meest gelezen artikelen die de bezoeker nog niet heeft gelezen
- **Podcast-promo** (50% kans): Promoot de podcast met artwork en directe links naar Spotify en Apple Podcasts

De plugin gebruikt Plausible Analytics om de populairste artikelen te identificeren en houdt bij welke artikelen een bezoeker al heeft gelezen via cookies.

## Installatie

1. Upload de plugin naar `/wp-content/plugins/` of installeer via de WordPress plugin-directory
2. Activeer de plugin via het 'Plugins' menu in WordPress
   - **Let op**: Bij activatie worden oude `zwr_` instellingen automatisch gemigreerd naar de nieuwe structuur
3. Ga naar **Instellingen → ZuidWest Staart** om alle instellingen te configureren:
   - Plausible Analytics-verbinding
   - Podcast-promocontent

## Configuratie

Navigeer naar **Instellingen → ZuidWest Staart** in het WordPress-beheerpaneel om alle instellingen te configureren.

### Plausible Analytics-instellingen (verplicht)

Configureer de verbinding met Plausible Analytics voor het ophalen van de meest populaire artikelen. **Deze instellingen zijn verplicht voordat de plugin kan functioneren.**

- **API-sleutel**: Jouw Plausible Analytics API-sleutel
- **Site-ID**: Het site-ID in Plausible (bijvoorbeeld: `zuidwestupdate.nl`)
- **API Endpoint**: De volledige URL naar de Plausible API v2 endpoint (bijvoorbeeld: `https://stats.zuidwesttv.nl/api/v2/query`)
- **Aantal dagen terug**: Hoeveel dagen geschiedenis moet worden gebruikt voor de meest gelezen artikelen (laat leeg voor standaard: 5)

### Podcast-promoinstellingen

Configureer het podcast-promoblok:

- **Koptekst**: De koptekst boven het podcast-promoblok (standaard: "Luister ook naar onze podcast")
- **Beschrijving**: Korte beschrijving die in de promo verschijnt
- **Artwork URL (1:1)**: Volledige URL naar de podcast artwork (vierkant formaat)
- **Spotify-link**: Link naar de podcast op Spotify
- **Apple Podcasts-link**: Link naar de podcast op Apple Podcasts

**Let op**: Als de podcast-promovelden niet zijn ingevuld, wordt bij elke podcast-selectie automatisch de lijst "Leestips voor jou" getoond als fallback.

### Leestips-instellingen

Configureer de weergave van de meest gelezen artikelen:

- **Koptekst**: De koptekst boven de lijst met meest gelezen artikelen (standaard: "Leestips voor jou")

## Hoe het werkt

### Automatische datasynchronisatie

- Elk uur haalt de plugin de top 25 meest gelezen artikelen op via Plausible Analytics
- Het aantal dagen terug is configureerbaar (standaard: 5 dagen)
- Alleen artikelen uit `/nieuws/` en `/achtergrond/` worden meegenomen
- Data wordt opgeslagen in de WordPress database voor snelle toegang
- Plausible Analytics endpoint, site-ID en API-sleutel zijn volledig configureerbaar via beheerinstellingen

### Slimme filtering

- De plugin onthoudt welke artikelen een bezoeker heeft gelezen via een cookie (`zw_staart_visited_posts`, 7 dagen geldig)
- Alleen ongelezen artikelen worden getoond in de "Leestips voor jou" lijst
- Het huidige artikel en artikelen uit dossiers worden altijd uitgesloten
- Als er minder dan 5 ongelezen artikelen zijn, wordt het hele blok verborgen

### 50/50 verdeling

Bij elke paginalading wordt willekeurig bepaald of de "Leestips voor jou" of de podcast-promo wordt getoond. Dit zorgt voor afwisseling en optimaliseert zowel recirculatie als podcastpromotie.

**Slimme fallback**: Als slechts een van de twee blokken is geconfigureerd, wordt die altijd getoond. Zo werkt de plugin ook als je alleen de meest gelezen artikelen wilt tonen of alleen de podcast-promo.

**Cache-compatibiliteit**: Beide blokken worden altijd in de HTML gerenderd, en JavaScript bepaalt client-side welke getoond wordt. Dit zorgt ervoor dat de plugin correct werkt met alle WordPress caching plugins (WP Super Cache, W3 Total Cache, etc.).

## Tracking & Analytics

Alle klikken worden geregistreerd in Plausible Analytics:

- **Recirculatie**: Klikken op artikelen in "Leestips voor jou"
- **Podcast+Spotify**: Klikken op de Spotify-knop
- **Podcast+Apple**: Klikken op de Apple Podcasts-knop

Alle links bevatten `?utm_source=recirculatie` voor campagnetracking.

## Deployment

De plugin wordt automatisch gedeployed via GitHub Actions bij elke push:

- **Main branch**: Deployment naar productie
- **Andere branches**: Deployment naar staging

**Configuratie**: Alle instellingen (Plausible API, podcast-promo) moeten via de WordPress-beheerinterface worden geconfigureerd. De Plausible Analytics-instellingen zijn verplicht voor de werking van de plugin.

## Technische details

- **WordPress versie**: 5.0 of hoger
- **PHP versie**: 7.4 of hoger
- **JavaScript**: Vereist voor volledige functionaliteit (50/50 selectie, cookie tracking)
- **Externe afhankelijkheden**: Plausible Analytics API v2
- **Database**: Gebruikt WordPress `wp_options` tabel voor opslag
  - `zw_staart_settings`: Geserialiseerde array met alle instellingen
  - `zw_staart_top_posts`: Array met populaire artikelen
- **Cache-compatibiliteit**: Werkt met alle WordPress caching plugins
- **Function prefix**: Alle functies gebruiken `zw_staart_` prefix
- **Cron hook**: `zw_staart_event_hook` (draait elk uur)
- **Migratie**: Oude `zwr_` settings en cron hooks worden automatisch opgeruimd bij plugin activatie

## Changelog

Zie [CHANGELOG](CHANGELOG) voor een volledig overzicht van alle wijzigingen.
