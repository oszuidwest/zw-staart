# ZuidWest Staart (van artikel)

WordPress-plugin voor Streekomroep ZuidWest die de staart van artikelen beheert met dynamische content om betrokkenheid te verhogen.

## Wat doet deze plugin?

De plugin voegt automatisch een van deze blokken toe onder elk artikel:

- **Leestips voor jou**: Top 5 populaire artikelen die de bezoeker nog niet heeft gelezen
- **Podcast-promo**: Podcast artwork met directe links naar Spotify en Apple Podcasts

Bij elke paginalading wordt willekeurig (50/50) bepaald welk blok wordt getoond. Als je slechts één blok configureert, wordt die altijd getoond.

## Installatie

1. Upload de plugin naar `/wp-content/plugins/` of installeer via de WordPress plugin-directory
2. Activeer de plugin via het 'Plugins' menu in WordPress
3. Ga naar **Instellingen → ZuidWest Staart** om de plugin te configureren

## Configuratie

### Plausible Analytics (verplicht voor leestips)

Configureer de verbinding met Plausible Analytics:

- **API-sleutel**: Jouw Plausible Analytics API-sleutel
- **Site-ID**: bijvoorbeeld `zuidwestupdate.nl`
- **API Endpoint**: bijvoorbeeld `https://stats.zuidwesttv.nl/api/v2/query`
- **Aantal dagen terug**: Standaard 5 dagen

### Podcast-promo (optioneel)

- **Koptekst**: Standaard "Luister ook naar onze podcast"
- **Beschrijving**: Korte introductie van de podcast
- **Artwork URL**: Vierkante afbeelding (1:1)
- **Spotify-link**: Link naar de podcast op Spotify
- **Apple Podcasts-link**: Link naar de podcast op Apple Podcasts

### Leestips weergave

- **Koptekst**: Standaard "Leestips voor jou"

## Hoe het werkt

- Elk uur haalt de plugin de top 25 meest gelezen artikelen op via Plausible Analytics
- Alleen artikelen uit `/nieuws/` en `/achtergrond/` worden meegenomen
- De plugin onthoudt welke artikelen een bezoeker heeft gelezen via een cookie (7 dagen geldig)
- Alleen ongelezen artikelen worden getoond
- Als er minder dan 5 ongelezen artikelen zijn, wordt het hele blok verborgen
- Cache-compatibel: beide blokken worden server-side gerenderd, JavaScript bepaalt client-side welke getoond wordt

## Tracking

Alle klikken worden geregistreerd in Plausible Analytics met `?utm_source=recirculatie`:

- **Recirculatie**: Klikken op leestips
- **Podcast+Spotify**: Klikken op Spotify-knop
- **Podcast+Apple**: Klikken op Apple Podcasts-knop

## Vereisten

- WordPress 6.0 of hoger
- PHP 8.1 of hoger
- JavaScript voor 50/50 selectie en cookie tracking
- Plausible Analytics account
