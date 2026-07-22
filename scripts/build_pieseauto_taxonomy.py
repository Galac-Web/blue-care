#!/usr/bin/env py
"""Genereaza data/pieseauto_taxonomy.json din taxonomia PieseAuto.ro."""
import json
import os

RAW = open(os.path.join(os.path.dirname(__file__), 'pieseauto_taxonomy_raw.txt'), encoding='utf-8').read()

KNOWN_MAINS = {
    'Accesorii auto', 'Accesorii roti', 'Aprindere', 'Cabluri auto', 'Car audio', 'Caroserie',
    'Climatizare', 'Dezmembrari auto', 'Directie', 'Diverse', 'Electrica & Electronica Auto',
    'Evacuare', 'Faruri stopuri lumini', 'Filtre auto', 'Frane', 'Instalatii GPL',
    'Interioare auto', 'Intretinere auto', 'Jante & Anvelope', 'Navigatie GPS', 'Pachete revizie',
    'Piese moto', 'Piese Motoare', 'Pompe si injectoare', 'Punte si rulmenti', 'Racire',
    'Scule auto', 'Suspensie', 'Transmisie', 'Tuning', 'Turbo', 'Ulei Auto', 'Xenon',
}

lines = [l.strip() for l in RAW.strip().split('\n') if l.strip()]
entries = []
main = ''
fallbacks = {}

for line in lines:
    if line in KNOWN_MAINS:
        main = line
        continue
    sub = line
    if sub.startswith('Alte ') or sub.startswith('Diverse'):
        fallbacks[main] = sub
    entries.append({'main_category': main, 'sub_category': sub, 'keywords': [sub.lower()]})

project_root = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
ai_path = os.path.join(project_root, 'data', 'pieseauto_taxonomy_seed.json')
if not os.path.isfile(ai_path):
    ai_path = os.path.join(project_root, 'prd', 'categories.json')
if os.path.isfile(ai_path):
    with open(ai_path, encoding='utf-8') as f:
        ai = json.load(f)
    sub_map = {e['sub_category'].lower(): i for i, e in enumerate(entries)}
    for row in ai:
        sub = (row.get('sub_category') or '').strip()
        kws = row.get('keywords') or []
        idx = sub_map.get(sub.lower())
        if idx is None:
            for i, e in enumerate(entries):
                sl, el = sub.lower(), e['sub_category'].lower()
                if sl in el or el in sl:
                    idx = i
                    break
        if idx is None:
            continue
        existing = set(entries[idx]['keywords'])
        for kw in kws:
            kl = str(kw).lower().strip()
            if kl:
                existing.add(kl)
        entries[idx]['keywords'] = sorted(existing)

aliases = {
    'Plansa bord': ['deska rozdzielcza', 'konsola', 'plansza', 'tablica rozdzielcza', 'dashboard'],
    'Aripa fata': ['blotnik przedni', 'blotnik', 'fender', 'błotnik'],
    'Aripa spate': ['blotnik tylny'],
    'Bara fata': ['zderzak przedni', 'przedni zderzak', 'bumper front', 'kompletny przod'],
    'Bara spate': ['zderzak tylny', 'tylny zderzak'],
    'Capota': ['maska', 'pokrywa silnika', 'bonnet'],
    'Capota portbagaj': ['klapa bagaznika', 'klapa'],
    'Usa fata': ['drzwi przednie'],
    'Usa spate': ['drzwi tylne'],
    'Usa': ['drzwi', 'usa'],
    'Amortizoare': ['amortyzator', 'amortizor', 'shock'],
    'Discuri frana': ['tarcza', 'disc frana', 'tarcze'],
    'Placute frana': ['klocki', 'placute'],
    'Etriere': ['zacisk', 'etrier', 'caliper', 'zaciski'],
    'Cutie viteze automata': ['skrzynia automat', 'cutie automata', 'dsg', 'skrzynia biegow'],
    'Convertizor': ['konwerter', 'torque converter'],
    'Volanta': ['kolo zamachowe', 'flywheel'],
    'Macara geam electric': ['podnosnik szyby', 'window regulator', 'mechanizm szyby'],
    'Grila radiator': ['atrapa chlodnicy', 'kratka', 'chlodnica'],
    'Radiator apa': ['chlodnica wody'],
    'Compresor AC': ['kompresor klimatyzacji'],
    'Alternator': ['alternator'],
    'Electromotor': ['rozrusznik', 'starter'],
    'Scaune auto': ['fotel', 'fotele', 'scaun', 'kanapa', 'boczki'],
    'Plafon': ['podsufitka', 'headliner'],
    'Stopuri': ['lampa tylna', 'stop', 'lampa'],
    'Faruri': ['reflektor', 'lampa przednia', 'headlight'],
    'Planetare': ['polos', 'planetara', 'półoś'],
    'Diferentiale': ['dyferencjal', 'differential', 'most'],
    'Chiulasa': ['glowica', 'cylinder head'],
    'Vibrochen': ['wal korbowy', 'crankshaft'],
    'Toba esapament': ['tlumik', 'wydech'],
    'Filtru de particule': ['dpf', 'fap'],
    'Injectoare': ['wtryskiwacz', 'injector'],
    'Sonda Lambda': ['sonda lambda', 'lambda'],
    'Oglinda retrovizoare': ['lusterko', 'mirror'],
    'Centuri siguranta': ['pasy', 'centura'],
    'Covorase': ['dywaniki', 'mocheta podea'],
    'Parbriz': ['szyba przednia', 'windshield'],
    'Luneta': ['szyba tylna', 'geam spate'],
    'Bandouri': ['listwa', 'ornament', 'carbon', 'listwy'],
    'Spoilere': ['spoiler', 'spojler', 'eleron'],
    'Oglinzi complete': ['lusterko', 'oglinda'],
    'Carcasa baterie auto': ['obudowa akumulatora'],
    'Suport motor': ['wspornik silnika', 'suport motor'],
    'Bieleta directie': ['drążek kierowniczy', 'bieleta'],
    'Caseta directie': ['przekladnia kierownicza', 'cremaliera'],
    'Pompa servodirectie': ['pompa wspomagania'],
    'Butuc roata': ['piasta', 'hub'],
    'Rulmenti': ['lozysko', 'rulment'],
    'Kit ambreiaj': ['sprzeglo', 'ambreiaj'],
    'Turbine': ['turbina', 'turbo'],
    'Intercooler': ['chlodnica powietrza'],
    'Kit distributie': ['rozrzad', 'distributie'],
    'Pompa apa': ['pompa wody'],
    'Pompa ulei': ['pompa oleju'],
    'Bobina inductie': ['cewka zaplonowa'],
    'Bujii': ['swieca zaplonowa', 'bujie'],
    'Filtru aer': ['filtr powietrza'],
    'Filtru ulei': ['filtr oleju'],
    'Filtru polen': ['filtr kabinowy'],
    'Motor complet': ['silnik kompletny', 'engine complete'],
    'Bloc motor': ['blok silnika'],
    'Segmenti': ['pierścienie', 'segment'],
    'Pistoane': ['tlok', 'piston'],
    'Biela': ['korbowod', 'connecting rod'],
    'Cuzineti': ['panewka', 'bearing shell'],
    'Electroventilator': ['wentylator', 'ventilator'],
    'Termostat auto': ['termostat'],
    'Antigel': ['plyn chlodniczy'],
    'Ansamblu stergatoare': ['wycieraczki', 'stergatoare'],
    'Calculator ECU': ['sterownik', 'ecu', 'modul'],
    'Display': ['ekran', 'display', 'navi'],
    'Ceasuri bord': ['zegary', 'instrument cluster'],
    'Airbag': ['poduszka powietrzna'],
    'Manere usi interior': ['klamka wewnetrzna'],
    'Fete usi': ['wykladzina drzwi', 'tapiterie'],
    'Mocheta podea interior': ['wykladzina podlogowa'],
    'Mocheta portbagaj': ['wykladzina bagaznika'],
    'Nuca schimbator': ['gałka zmiany biegów'],
    'Volane': ['kierownica', 'volan'],
    'Pedale': ['pedaly'],
    'Torpedou': ['kokpit'],
    'Consola centrala': ['konsola srodkowa'],
    'Cotiera auto': ['podlokietnik'],
    'Parasolar': ['osłona przeciwsłoneczna'],
    'Tetiere': ['zagłówek'],
    'Bancheta': ['kanapa', 'bancheta'],
    'Trapa': ['szyberdach'],
    'Geamuri': ['szyba', 'geam'],
    'Praguri': ['progi'],
    'Chedere': ['zamek', 'broasca'],
    'Broasca usa': ['zamek drzwi'],
    'Maner usa': ['klamka'],
    'Balamale': ['zawias'],
    'Clipsuri auto': ['klips', 'clip'],
    'Emblema': ['emblem', 'znak'],
    'Catadioptru': ['odblask'],
    'Carenaj roata': ['nadkole'],
    'Scut motor metalic': ['osłona silnika', 'scut motor'],
    'Armatura bara fata': ['belka zderzaka'],
    'Difuzor bara fata': ['dyfuzor'],
    'Flaps bara': ['flapsy'],
    'Hardtop': ['dach twardy'],
    'Cui tractare': ['hak holowniczy'],
    'Camera marsarier': ['kamera cofania'],
    'Senzori parcare': ['czujnik parkowania', 'pdc'],
    'Inchidere centralizata': ['centralny zamek'],
    'Stergatoare': ['wycieraczka'],
    'Prelata auto': ['plandeka'],
    'Husa volan': ['pokrowiec na kierownice'],
    'Huse scaune': ['pokrowce na fotele'],
    'Alte piese de caroserie': ['karoseria', 'nadwozie', 'cwiartka', 'element caroserie'],
    'Alte piese directie': ['directie', 'kierownica'],
    'Alte piese suspensie': ['zawieszenie', 'wahacz', 'suspensie'],
    'Alte piese transmisie': ['przekladnia', 'transmisie'],
    'Alte piese motor': ['silnik', 'motor'],
    'Alte elemente frana': ['hamulce', 'frane'],
    'Alte piese lumini auto': ['lampa', 'swiatlo'],
    'Alte piese clima auto': ['klimatyzacja', 'clima'],
    'Alte piese evacuare': ['uklad wydechowy', 'esapament'],
    'Alte piese racire': ['chlodzenie', 'racire'],
    'Alte piese tren rulare': ['tren rulare', 'punte'],
    'Alte piese alimentare': ['zasilanie', 'paliwo'],
    'Alte piese Electrica & Electronica': ['elektryka', 'modul'],
    'Diverse accesorii auto': ['akcesoria'],
    'Diverse': ['diverse', 'rozne'],
}

for e in entries:
    sub = e['sub_category']
    if sub in aliases:
        existing = set(e['keywords'])
        existing.update(k.lower() for k in aliases[sub])
        e['keywords'] = sorted(existing)

root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
out_path = os.path.join(root, 'data', 'pieseauto_taxonomy.json')
out = {
    'version': 1,
    'source': 'pieseauto.ro taxonomy',
    'fallbacks': fallbacks,
    'entries': entries,
}
with open(out_path, 'w', encoding='utf-8') as f:
    json.dump(out, f, ensure_ascii=False, indent=2)
print(f'OK: {len(entries)} entries, {len(fallbacks)} fallbacks -> {out_path}')
