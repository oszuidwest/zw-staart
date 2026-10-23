# ZuidWest Staart (van artikel)

WordPress-plugin voor het WordPress-thema van Streekomroep ZuidWest die leestips onder artikelen toont voor betere recirculatie en engagement.

## Wat doet deze plugin?

De plugin voegt automatisch een blok **Leestips voor jou** toe onder nieuwsartikelen (niet bij dossier-artikelen): de top 5 meest gelezen artikelen die de bezoeker nog niet heeft gelezen.

Optioneel kan de bezoeker **doorscrollen** naar de volgende leestip (zie hieronder).

### Doorscrollen
- Halverwege het artikel schuift onderin een balk in beeld met de eerste leestip: het meest gelezen artikel dat de bezoeker nog niet heeft gelezen
- Het wiel in de balk loopt vol tijdens het lezen en is vol zodra de deelknoppen boven de balk zichtbaar zijn; wie doorscrolt, gaat naar het volgende artikel (een gewone paginalading)
- Een pagina die via de terugknop onderaan wordt hersteld, laadt niet vanzelf opnieuw door; daarvoor is eerst een scrollbeweging nodig
- Op mobiel sluit je de balk door hem omlaag te vegen, op desktop met het kruisje; daarna blijft hij de rest van de sessie weg
- Zolang de balk actief is, wordt het leestipsblok onder het artikel verborgen; na sluiten komt het terug (als er minstens 5 ongelezen leestips zijn, zoals altijd)

## Installatie

1. Upload de plugin naar `/wp-content/plugins/` of installeer via de WordPress plugin-directory
2. Activeer de plugin via het 'Plugins' menu in WordPress
3. Ga naar **Instellingen → ZuidWest Staart** om de plugin te configureren

## Configuratie

### Weergave

- **Koptekst leestips**: Standaard "Leestips voor jou"
- **Doorscrollen**: Standaard uit

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
- **Recirculatie+Scroll**: Balk van Doorscrollen, met eigenschap `actie`: `getoond`, `geklikt`, `doorgescrold`, `gesloten` (kruisje) of `weggeveegd` (mobiel). De link naar het volgende artikel krijgt `?utm_source=recirculatie&utm_content=scroll`

## Vereisten

- WordPress 7.0 of hoger
- PHP 8.3 of hoger
- JavaScript voor het filteren van gelezen artikelen en cookie tracking
- Plausible Analytics account (voor leestips functionaliteit)
