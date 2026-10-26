# ZuidWest Staart (van artikel)

WordPress-plugin voor het WordPress-thema van Streekomroep ZuidWest die leestips onder artikelen toont voor betere recirculatie en engagement.

## Wat doet deze plugin?

De plugin voegt automatisch een blok **Leestips voor jou** toe onder nieuwsartikelen (niet bij dossier-artikelen): de top 5 meest gelezen artikelen die de bezoeker nog niet heeft gelezen.

Optioneel kan de bezoeker **doorscrollen** naar de volgende leestip (zie hieronder).

### Doorscrollen
- Na ongeveer een derde van het artikel schuift onderin een balk in beeld met de eerste leestip: het meest gelezen artikel dat de bezoeker nog niet heeft gelezen
- Op desktop staat de uitgelichte afbeelding van de leestip naast de titel; op mobiel alleen de titel
- Zodra de balk verschijnt, vraagt hij een prefetch van het volgende artikel aan, waar de browser dat ondersteunt. Die draait geen scripts, dus telt niet als paginaweergave of gelezen artikel
- Het wiel in de balk loopt vol tijdens het lezen en is vol zodra de deelknoppen onder het artikel boven de balk zichtbaar zijn
- Pas als het einde van het artikel 2 seconden in beeld is en de pagina stilstaat, verandert het label in "Scroll opnieuw om te openen". Een nieuwe veeg, scrollbeweging of scrolltoets omlaag opent dan het volgende artikel (een gewone paginalading). Zolang de veeg waarmee je het einde bereikt nog naloopt, opent er niets, zodat je de laatste alinea rustig kunt lezen; een tik telt niet als veeg
- Een pagina die via de terugknop onderaan wordt hersteld, laadt niet vanzelf opnieuw door; daarvoor moet de lezer eerst terug omhoog scrollen, tot vóór het einde van het artikel
- Omhoog scrollen opent het volgende artikel nooit; klikken op de balk werkt altijd meteen
- De balk sluit je met de knop **Sluiten** (kruisje), op mobiel ook door hem omlaag te vegen; daarna blijft hij de rest van de sessie weg
- Zolang de balk actief is, wordt het leestipsblok onder het artikel verborgen; na sluiten komt het terug (als er minstens 5 ongelezen leestips zijn, zoals altijd)

## Installatie

1. Upload de plugin naar `/wp-content/plugins/` of installeer via de WordPress plugin-directory
2. Activeer de plugin via het 'Plugins' menu in WordPress
3. Ga naar **Instellingen → ZuidWest Staart** om de plugin te configureren

## Configuratie

### Weergave

- **Koptekst leestips**: Standaard "Leestips voor jou"
- **Doorscrollen**: Uit (standaard), Aan of A/B-test (zie hieronder)

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
- **Privacy-vriendelijk**: Gebruikt alleen functionele cookies voor het bijhouden van gelezen artikelen; tijdens de A/B-test bewaart `localStorage` (`zw_staart_ab_variant`) alleen de datum en de groep, a of b

## Tracking & Analytics

Klikken worden geregistreerd in Plausible Analytics:

- **Recirculatie+Artikel**: Klikken op artikelen in leestips (met `?utm_source=recirculatie`)
- **Recirculatie+Scroll**: Balk van Doorscrollen, met eigenschap `actie`: `getoond`, `geklikt`, `doorgescrold`, `gesloten` (kruisje) of `weggeveegd` (mobiel). De link naar het volgende artikel krijgt `?utm_source=recirculatie&utm_content=scroll`

Alleen tijdens de A/B-test, en daar krijgt elk event hierboven ook de eigenschap `variant` (`a` of `b`):

- **Recirculatie Weergave**: elke artikelweergave in de test, ook een pagina die via de terugknop terugkomt
- **Recirculatie Terug**: binnen 15 seconden terug op het artikel dat via de leestips of de balk werd verlaten, met eigenschap `via`: `leestip`, `geklikt` of `doorgescrold`

Let op: UTM-parameters op interne links tellen in Plausible niet als bron, want de bron hoort bij het begin van een bezoek. Meten gaat daarom via de events.

## A/B-test: leestipsblok tegen doorscroll-balk

Vooraf vastgelegd, zodat het besluit niet achteraf wordt bijgesteld.

- **Vraag**: levert de doorscroll-balk meer doorlezers op dan het leestipsblok, zonder lezers ongewild weg te sturen?
- **Groepen**: elke bezoeker wordt in de browser willekeurig ingedeeld, 50/50, en blijft die dag in dezelfde groep; de volgende dag wordt opnieuw geloot. Zo is elke dag een onafhankelijke herhaling en past de indeling bij Plausible, dat bezoekers per dag telt. Groep a krijgt het leestipsblok, groep b de balk (en het leestipsblok na het sluiten van de balk). Bezoekers zonder `localStorage` doen niet mee en zien het leestipsblok. Ingelogde gebruikers (de redactie) doen ook niet mee: zij zien de balk, zonder test-events.
- **Primaire maatstaf**: doorlezers per artikelweergave. Doorlezers zijn klikken op een leestip (`Recirculatie+Artikel`) plus klikken en doorscrollen op de balk (`Recirculatie+Scroll` met `actie` `geklikt` of `doorgescrold`); weergaven zijn `Recirculatie Weergave`. Elke weergave telt, ook als de bezoeker het blok of de balk niet bereikt.
- **Analyse**: per dag het verschil tussen b en a, met een 95%-interval over de dagen (beide groepen krijgen hetzelfde nieuws, dus de schommelingen per dag vallen weg).
- **Controle**: het aantal bezoekers per groep, per dag geteld, moet 50/50 zijn (chi-kwadraat, p ≥ 0,001); anders is de test ongeldig.
- **Guardrail**: het aandeel doorlezers dat snel terugkomt (`Recirculatie Terug`) mag bij b hooguit 5 procentpunt hoger zijn dan bij a.
- **Duur**: 42 dagen, zes volle weken, zonder tussentijds te stoppen; alleen dagen met beide groepen tellen. Met ongeveer 4.700 artikelweergaven per groep per dag en terugkerende lezers die elk hun eigen klikgedrag hebben, vindt de test een echt verschil van 10% in ongeveer 98% en van 5% in ongeveer 60% van de gevallen; zonder echt verschil wijst hij in ongeveer 5% van de gevallen toch een winnaar aan (simulatie op basis van de Plausible-cijfers van september en oktober 2026). Met een vaste groep per bezoeker zou dat laatste bijna de helft zijn, daarom wordt per dag geloot.
- **Besluit**: b als het interval boven 0 ligt en de guardrail houdt; a als het interval onder 0 ligt of de guardrail breekt; anders geen aantoonbaar verschil en blijft Doorscrollen uit.

### Voorbereiden in Plausible

Voeg vóór de start onder **Site Settings → Goals** de custom events `Recirculatie Weergave`, `Recirculatie Scroll` en `Recirculatie Terug` toe (`Recirculatie Artikel` bestaat al), en onder **Custom Properties** `variant`, `actie` en `via`.

### Rapport

Na afloop, met een Plausible-sleutel voor de Stats API en Node.js 18 of hoger:

```sh
PLAUSIBLE_API_KEY=... node tools/ab-rapport.mjs --van 2026-11-02 --tot 2026-12-13
```

Het script toont per groep de bezoekers, weergaven, doorlezers en snel-terugkomers, het verschil per dag met interval, de 50/50-controle, de uitsplitsing naar apparaat en het besluit volgens de regel hierboven. Een venster van meer dan 42 dagen weigert het; een korter venster mag voor een tussentijdse controle van de verdeling, maar geeft geen besluit. Het staat niet in de plugin-zip.

## Vereisten

- WordPress 7.0 of hoger
- PHP 8.3 of hoger
- JavaScript voor het filteren van gelezen artikelen en cookie tracking
- Plausible Analytics account (voor leestips functionaliteit)
