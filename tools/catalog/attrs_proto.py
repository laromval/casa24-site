# Прототип разбора названий товаров: производитель и размеры (объём, вес, мощность, размер).
import csv, re, sys, collections

BRANDS = """Total|Wokin|Geko|Topex|EGA|Vorel|Zaruba|Strend Pro=Strend|Yato|Elefant|Vector+=Vector|Coloriks|Coloris|Falon Tech=Falon|SGS|Richmann=Richmann,Richman|Stern
Profmet|Mixxus|Intex|Horoz|Kraft&Dele=Kraft&dele,Kraft & dele|Bradas|Brimix|Biodur|Primaflex|Tytan|Akfix|Farbis|Zambak|Maxter|Samurai|Santino|DeWalt|Elif
Blueshark|Zegor|Kseibi|TDM|Procraft|DWT|Sigma|Moltopren|Torfland|Corona|Fleko=Fleko,Fleco|Flexitaly|Proline|Smartfix|Hardy|Mustang|Prosperplast|Soma Fix=Soma Fix,Somafix,Soma
Hardstar|Polax|Verto|Bauwelt=Bauwelt,Bau Welt|Tuffex|Elitacolor|Rotaru|Diolsem|Vatan|Faster Tools=Faster|Hardex|Kristalon|Plastart|Zimmer|Mutlusan|Tesa|Tolsen|Goalmac|Goalmag
Forse|Milwaukee|Dedra|Hammer|Prescott|Gosia|Monoflex|Berossi|Gepard|Stavtool|Neomax|Fertilux|Crasdan|Fairy|Maxell|Rodex|Emos|Bison|Bostik|Cif|Supraton|Mastiff|Kuchcik
Beorol|Cellfast|Trigor|Madeco|Wismark|HighGenic|Novax|Wermop|Gallus|Motive|Asude|Fudi|Deleex|Ezra|Sanliv|Tucai|Alya|Ouya|Medalyan|Suyap|Singa|Palmiye|Defne|Mamut|Festa
Vargo|Detex|Amigo|Dunya|MegaWatt|Partner|Sintex|Bondit|Zwalum|Gredre|Exen|Pratik|Dracon|Tiffany|Bingo|Antares|Aviora|Winso|Karasu|Unimas|Ipsan|Tufflex|Kratos|Burak
Mesan|OZK|Perilla|Oregon|Nika|Keltin|Dolpfin|Gondol|Zerix|Zhwei|HPX|Orient|Lider|Maan|Sebos|Soudal|Duracell|Kodak|Recyko|Klingspor|Tekasil|Śnieżka=Sniezka,Sniezca,Śnieżka
Speedymask|Unitape|Colombo|Trotec|Siroflex|XTLine|Eurolumina|Agrecol|Sunflex|Draumet|Sthor|Gamma=Gama|Foton|Imperial|Zuber|Aksel|Avers|Metsan|Mechanic|Kingjet|Scroll|Penosil|Ceresit|Knauf|Bosch|Makita|Stanley|Gardena|Varta|Energizer|Philips|Osram|Feron|Lemanso|Videx"""

def brand_table():
    out = []
    for item in BRANDS.replace('\n', '|').split('|'):
        name, _, alts = item.partition('=')
        name = name.strip()
        variants = [name] + [a.strip() for a in alts.split(',') if a.strip()]
        for v in variants:
            out.append((re.compile(r'(?<![A-Za-zА-Яа-я0-9])' + re.escape(v) + r'(?![A-Za-zА-Яа-я])', re.I), name))
    return out

BT = brand_table()

def brand(title):
    best = None
    for rx, name in BT:
        m = rx.search(title)
        if m and (best is None or m.start() < best[0]):
            best = (m.start(), name)
    return best[1] if best else None

NUM = r'(\d+(?:[.,]\d+)?)'
B = r'(?![A-Za-zА-Яа-я])'

def fmt(v):
    v = v.replace(',', '.')
    f = float(v)
    s = ('%g' % f)
    return s.replace('.', ',')

def volume(t):
    m = re.search(NUM + r'\s*(мл|ml|л|l|L|литр\w*)' + B, t)
    if not m: return None
    n, u = m.group(1), m.group(2).lower()
    if u in ('мл', 'ml'):
        return fmt(n) + ' ml'
    return fmt(n) + ' L'

def weight(t):
    m = re.search(NUM + r'\s*(кг|kg|гр|gr|г|g)' + r'(?![A-Za-zА-Яа-я/])', t)
    if not m: return None
    n, u = m.group(1), m.group(2).lower()
    if u in ('кг', 'kg'):
        return fmt(n) + ' kg'
    return fmt(n) + ' g'

def power(t):
    m = re.search(NUM + r'\s*(квт|kw|вт|w)' + B, t, re.I)
    if not m: return None
    n, u = m.group(1), m.group(2).lower()
    if u in ('квт', 'kw'):
        return fmt(n) + ' kW'
    return fmt(n) + ' W'

DIM = NUM + r'\s*[xх×*]\s*' + NUM + r'(?:\s*[xх×*]\s*' + NUM + r')?\s*(мм|mm|см|cm|м|m)?' + B
LEN = NUM + r'\s*(мм|mm|см|cm|м|m)' + B
UNIT = {'мм': 'mm', 'mm': 'mm', 'см': 'cm', 'cm': 'cm', 'м': 'm', 'm': 'm'}

def size(t):
    m = re.search(DIM, t)
    if m and m.group(4):
        parts = [fmt(x) for x in m.group(1, 2, 3) if x]
        return '×'.join(parts) + ' ' + UNIT[m.group(4).lower()]
    m = re.search(LEN, t)
    if m:
        return fmt(m.group(1)) + ' ' + UNIT[m.group(2).lower()]
    return None

if __name__ == '__main__':
    sys.stdout.reconfigure(encoding='utf-8')
    rows = [r for r in csv.reader(open(sys.argv[1], encoding='utf-8')) if len(r) == 4 and r[1] == 'ru']
    stats = collections.Counter()
    vals = collections.defaultdict(collections.Counter)
    samples = collections.defaultdict(list)
    for r in rows:
        t = r[2]
        for name, fn in (('brand', brand), ('volume', volume), ('weight', weight), ('power', power), ('size', size)):
            v = fn(t)
            if v:
                stats[name] += 1
                vals[name][v] += 1
                if len(samples[name]) < 25 and hash(t) % 7 == 0:
                    samples[name].append(t + '  =>  ' + v)
    print(len(rows), dict(stats))
    for k in vals:
        print('==', k, len(vals[k]), 'distinct;', ', '.join(f'{a}:{b}' for a, b in vals[k].most_common(30)))
        print('   ' + '\n   '.join(samples[k]))
