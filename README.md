# ZuidWest Staart (van artikel)

WordPress-plugin voor het WordPress-thema van Streekomroep ZuidWest die promotionele content onder artikelen toont voor betere recirculatie en engagement.

## Wat doet deze plugin?

De plugin voegt automatisch promotionele content toe onder elk artikel:

- **Leestips voor jou** (50% kans): Toont de top 5 meest gelezen artikelen die de bezoeker nog niet heeft gelezen
- **Podcast-promo** (50% kans): Promoot de podcast met artwork en directe links naar Spotify en Apple Podcasts

De plugin gebruikt een 50/50 verdeling waarbij bij elke pageview willekeurig wordt bepaald welk blok wordt getoond. Podcast-links openen automatisch in een nieuw tabblad.

### Fallback
Als slechts één van de twee blokken is geconfigureerd, wordt die altijd getoond. Dit zorgt ervoor dat de plugin blijft werken zelfs als je alleen leestips óf alleen de podcast-promo wilt gebruiken.

## Installatie

1. Upload de plugin naar `/wp-content/plugins/` of installeer via de WordPress plugin-directory
2. Activeer de plugin via het 'Plugins' menu in WordPress
3. Ga naar **Instellingen → ZuidWest Staart** om de plugin te configureren

## Configuratie

### Plausible Analytics (voor leestips)

Configureer de verbinding met Plausible Analytics:

- **API-sleutel**: Jouw Plausible Analytics API-sleutel
- **Site-ID**: bijvoorbeeld `zuidwestupdate.nl`
- **API Endpoint**: bijvoorbeeld `https://stats.zuidwesttv.nl/api/v2/query`
- **Aantal dagen terug**: Standaard 5 dagen

### Podcast-promo

- **Koptekst**: Standaard "Luister ook naar onze podcast"
- **Beschrijving**: Korte introductie van de podcast
- **Artwork URL**: Vierkante afbeelding (1:1)
- **Spotify-link**: Link naar de podcast op Spotify
- **Apple Podcasts-link**: Link naar de podcast op Apple Podcasts

## Hoe het werkt

### Data synchronisatie
- Elk uur haalt de plugin automatisch de top 25 meest gelezen artikelen op via Plausible Analytics
- Alleen artikelen uit `/nieuws/` en `/achtergrond/` worden meegenomen (hardcoded voor nu, zou een instelling kunnen worden)
- Data wordt lokaal gecached voor snelle weergave

### Personalisatie
- De plugin onthoudt welke artikelen een bezoeker heeft gelezen via een cookie (`zw_staart_visited_posts`, 7 dagen geldig)
- Alleen ongelezen artikelen worden getoond in de leestips
- Het huidige artikel en dossier-artikelen worden automatisch uitgesloten
- Als er minder dan 5 ongelezen artikelen beschikbaar zijn, wordt het leestips-blok verborgen

### Techniek
- **Cache-compatibel**: Beide blokken worden server-side gerenderd, JavaScript bepaalt client-side welke getoond wordt
- **Performance**: Minimale impact door slimme caching en efficiënte queries
- **Privacy-vriendelijk**: Gebruikt alleen functionele cookies voor het bijhouden van gelezen artikelen

## Tracking & Analytics

Alle klikken worden geregistreerd in Plausible Analytics:

- **Recirculatie+Artikel**: Klikken op artikelen in leestips (met `?utm_source=recirculatie`)
- **Recirculatie+Podcast+Spotify**: Klikken op Spotify-knop (zonder UTM)
- **Recirculatie+Podcast+Apple**: Klikken op Apple Podcasts-knop (zonder UTM)

Alleen artikel-links bevatten UTM-parameters voor campagne-tracking. Podcast-links zijn schoon voor een betere gebruikerservaring.

## Vereisten

- WordPress 6.0 of hoger
- PHP 8.1 of hoger
- JavaScript voor 50/50 selectie en cookie tracking
- Plausible Analytics account (voor leestips functionaliteit)