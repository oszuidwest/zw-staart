# ZuidWest Staart (van artikel)

WordPress-plugin voor het WordPress-thema van Streekomroep ZuidWest die leestips onder artikelen toont voor betere recirculatie en engagement.

## Wat doet deze plugin?

De plugin voegt automatisch een blok **Leestips voor jou** toe onder nieuwsartikelen (niet bij dossier-artikelen): de top 5 meest gelezen artikelen die de bezoeker nog niet heeft gelezen.

## Installatie

1. Upload de plugin naar `/wp-content/plugins/` of installeer via de WordPress plugin-directory
2. Activeer de plugin via het 'Plugins' menu in WordPress
3. Ga naar **Instellingen → ZuidWest Staart** om de plugin te configureren

## Configuratie

### Weergave

- **Koptekst leestips**: Standaard "Leestips voor jou"

### Plausible Analytics (voor leestips)

Configureer de verbinding met Plausible Analytics:

- **API-sleutel**: Jouw Plausible Analytics API-sleutel
- **Site-ID**: bijvoorbeeld `zuidwestupdate.nl`
- **API Endpoint**: bijvoorbeeld `https://stats.zuidwesttv.nl/api/v2/query`
- **Aantal dagen terug**: Standaard 5 dagen

## Hoe het werkt

### Data synchronisatie
- Elk uur haalt de plugin automatisch de top 25 meest gelezen artikelen op via Plausible Analytics
- Alleen artikelen uit `/nieuws/` en `/achtergrond/` worden meegenomen (hardcoded voor nu, zou een instelling kunnen worden)
- Data wordt lokaal gecached voor snelle weergave

### Personalisatie
- De plugin onthoudt welke artikelen een bezoeker heeft gelezen via een cookie (`zw_staart_visited_posts`, 7 dagen geldig, maximaal 100 artikelen)
- Alleen ongelezen artikelen worden getoond in de leestips
- Het huidige artikel en dossier-artikelen worden automatisch uitgesloten
- Als er minder dan 5 ongelezen artikelen beschikbaar zijn, wordt het leestips-blok verborgen

### Techniek
- **Cache-compatibel**: Het blok wordt server-side gerenderd, JavaScript verbergt client-side de al gelezen artikelen
- **Performance**: Minimale impact door slimme caching en efficiënte queries
- **Privacy-vriendelijk**: Gebruikt alleen functionele cookies voor het bijhouden van gelezen artikelen

## Tracking & Analytics

Klikken worden geregistreerd in Plausible Analytics:

- **Recirculatie+Artikel**: Klikken op artikelen in leestips (met `?utm_source=recirculatie`)

## Vereisten

- WordPress 7.0 of hoger
- PHP 8.3 of hoger
- JavaScript voor het filteren van gelezen artikelen en cookie tracking
- Plausible Analytics account (voor leestips functionaliteit)
