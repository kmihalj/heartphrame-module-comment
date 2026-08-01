# HeartPhrame Comment modul

[English version](README.md)

Comment modul dodaje komentare prijavljenih korisnika, reakcije, prijave i
moderiranje na objavljene dokumente HTML Editora. Ponovno koristi prava
Editora i Workspacea umjesto uvođenja drugog, konkurentskog ACL sustava.

## Mogućnosti

- Komentiranje objavljenih dokumenata korisnicima koji ih smiju čitati.
- Postavka “Omogućeni komentari” po dokumentu i jeziku.
- Odvojena Workspace postavka nacrta i objavljene stranice.
- Reakcije sviđa mi se / ne sviđa mi se, jedna aktivna po korisniku i komentaru.
- Jedna prijava neprimjerenog sadržaja po korisniku i komentaru.
- Obavijesti vlasniku sadržaja i autoru zadnje objavljene izmjene.
- Brisanje vlasniku sadržaja, zadnjem uredniku, objavljivaču ili administratoru
  samostalnog Editora.
- UI temeljen na Bootstrapu koji slijedi temu, ali radi i bez Theme modula.
- Prenosiva ORM shema za SQLite, PostgreSQL, MySQL i MariaDB.
- Bez vanjskih frontend i mail ovisnosti.

Isključivanje komentiranja onemogućuje nove unose, ali postojeće komentare
namjerno ostavlja vidljivima.

## Preduvjeti

- PHP 8.2 ili noviji s ekstenzijom `mbstring`.
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`
- `aaieduhr/heartphrame-module-editor-html`
- `aaieduhr/heartphrame-module-notification`

Opcionalne integracije:

- `aaieduhr/heartphrame-module-workspace` daje naslijeđena prava čitanja i
  objavljivanja.
- `aaieduhr/heartphrame-module-theme` daje podešene tokene svijetle i tamne teme.

## Instalacija

U `app.modules.enabled` uključi ovisnosti prije Comment modula, zatim kopiraj
i pokreni njegovu jedinu početnu migraciju:

```bash
vendor/bin/hph comment:install-migration
vendor/bin/hph orm-migrate up
```

Paket ne sadrži korisnike, dokumente, komentare ni testne seed podatke.

## Tijek u Editoru

HTML Editor prikazuje prekidač “Omogućeni komentari” samo kada je ovaj modul
instaliran.

- Samostalni Editor: spremanje odmah primjenjuje postavku na aktualnu objavu.
- Workspace: spremanje čuva traženu postavku uz zajednički nacrt.
- Objavljivanje prenosi nacrtnu postavku čitateljima.
- Odbacivanje nacrta odbacuje i njegovu postavku komentiranja.

Komentari nisu verzije dokumenta. Ostaju vezani uz dokument i jezik dok se
HTML verzije mijenjaju.

## Dokumentacija

- [Hrvatske upute](docs/index_hr.md)
- [English guide](docs/index_en.md)

## Provjere kvalitete

```bash
composer on-commit
```

Provjera pokreće PHPCS, Rector dry-run, PHPStan za produkcijski i testni kod
te PHPUnit.

## Politika ovisnosti

Framework i interni HeartPhrame moduli zahtijevaju se s pomične grane
`dev-main`. Ovaj modul ne sprema `composer.lock`; GitHub CI na PHP-u 8.2-8.5
dohvaća najnovija razvojna stanja i pokreće cijeli skup provjera
`composer on-commit`.
