"""Новое дерево каталога casa24.md (решение владельца 28.10.2026).

Раздел -> группа -> подгруппа. Каждая подгруппа: (RU, RO, [ID старых категорий 1С]).
ID — term_id категорий, которые создаёт обмен с 1С (одинаковые на проде и на
staging — staging скопирован с прода). Товары этих категорий попадают в
подгруппу автоматически после каждой выгрузки (mu-plugin casa24-catalog-map).

SORT — товары не по группе, а по названию (группы-бренды красок и бытовой
химии): правила в casa24-catalog-map.php, функция casa24_catalog_classify().
Подгруппа None — товары лежат прямо в группе (у группы нет подгрупп).

Из этого файла tools/catalog/build_map.py собирает
wp-content/mu-plugins/casa24-catalog/map.php. Подробности и обоснование —
docs/catalog-structure-proposal.md.
"""

SORT = 'SORT'

TREE = [
 ('Стройматериалы', 'Materiale de construcție', [
   ('Строительная химия', 'Chimie pentru construcții', [
     ('Грунтовки', 'Grunduri', [1440, 1173]),
     ('Клеи для плитки и пенополистирола', 'Adezivi pentru faianță și polistiren', [1472]),
     ('Гидроизоляция', 'Hidroizolație', [1414]),
     ('Добавки и строительная химия', 'Aditivi și chimie pentru construcții', [1412]),
     ('Смазки и технические жидкости', 'Lubrifianți și lichide tehnice', [968, SORT]),
     ('Соль техническая', 'Sare tehnică', [1466]),
   ]),
   ('Пена, герметики, скотч', 'Spumă, siliconi, bandă adezivă', [
     ('Пена монтажная', 'Spumă de montaj', [947]),
     ('Силиконы и герметики', 'Siliconi și chituri', [974]),
     ('Скотч и клейкие ленты', 'Bandă adezivă și scotch', [932]),
   ]),
   ('Пиломатериалы и плиты', 'Cherestea și plăci', [
     ('Брус', 'Grinzi', [1386]),
     ('Доска обрезная', 'Scânduri tivite', [1387]),
     ('OSB-плиты', 'Plăci OSB', [1385]),
     ('Фанера, штапик, наличник', 'Placaj, șipci, pervazuri', [1384]),
   ]),
   ('Сетки, арматура, проволока', 'Plase, armătură, sârmă', [
     ('Сетка штукатурная из стекловолокна', 'Plasă din fibră de sticlă pentru tencuială', [1381, 1382]),
     ('Сетка металлическая', 'Plasă metalică', [1379]),
     ('Арматура', 'Armătură', [1383]),
     ('Проволока вязальная', 'Sârmă de legat', [1380]),
   ]),
   ('Кровля, фасад, забор', 'Acoperiș, fațadă, gard', [
     ('Водосточная система', 'Sistem pluvial', [1213]),
     ('Еврозабор', 'Gard din beton (eurogard)', [1408]),
   ]),
 ]),
 ('Краски и лаки', 'Vopsele și lacuri', [
   ('Краски для стен и потолков', 'Vopsele pentru pereți și tavane', [
     ('Интерьерные краски', 'Vopsele de interior', [SORT]),
     ('Фасадные краски', 'Vopsele de fațadă', [SORT]),
     ('Антисептические и влагостойкие краски', 'Vopsele antiseptice și rezistente la umezeală', [SORT]),
   ]),
   ('Эмали', 'Emailuri', [
     ('Эмали алкидные (ПФ-115)', 'Emailuri alchidice (PF-115)', [SORT]),
     ('Эмали акриловые', 'Emailuri acrilice', [SORT]),
     ('Грунт-эмали 3 в 1 и молотковые', 'Grund-emailuri 3 în 1 și cu efect ciocănit', [1186, SORT]),
     ('Эмали для радиаторов', 'Emailuri pentru calorifere', [SORT]),
     ('Термостойкие краски', 'Vopsele termorezistente', [SORT]),
     ('Краски для кровли', 'Vopsele pentru acoperiș', [SORT]),
   ]),
   ('Аэрозольные краски', 'Vopsele spray', [(None, None, [SORT])]),
   ('Декоративные краски', 'Vopsele decorative', [(None, None, [SORT])]),
   ('Лаки и пропитки', 'Lacuri și impregnanți', [
     ('Лаки', 'Lacuri', [SORT]),
     ('Пропитки и антисептики для дерева', 'Impregnanți și antiseptice pentru lemn', [SORT]),
   ]),
   ('Колеры и пигменты', 'Coloranți și pigmenți', [(None, None, [1180, SORT])]),
   ('Растворители и разбавители', 'Solvenți și diluanți', [(None, None, [1174])]),
   ('Шпатлёвки', 'Chituri', [
     ('Шпатлёвки по дереву', 'Chituri pentru lemn', [1181]),
     ('Шпатлёвки по металлу', 'Chituri pentru metal', [1378]),
   ]),
 ]),
 # 28.10.2026: «Отделка, двери и фурнитура» разделён на два раздела — обои,
 # полы и клеи не подходили под «двери и фурнитуру» (обратная связь владельца).
 ('Отделочные материалы', 'Materiale de finisaj', [
   ('Напольные покрытия', 'Pardoseli', [
     ('Ламинат и подложка', 'Laminat și folie de bază', [1303]),
     ('Линолеум', 'Linoleum', [1302]),
     ('Ковролин и ковровые дорожки', 'Mochetă și traverse', [987, 986, 979]),
   ]),
   ('Стены и потолок', 'Pereți și tavan', [
     ('Обои', 'Tapete', [1306]),
     ('Самоклеящиеся обои и плёнка', 'Tapete și folie autoadezivă', [1305]),
     ('Самоклеящаяся плитка и панели', 'Plăci și panouri autoadezive', [1304]),
     ('Вагонка и профили', 'Lambriu și profile', [1462]),
   ]),
   ('Клеи', 'Adezivi', [
     ('Клей для обоев и стеклохолста', 'Adeziv pentru tapete și fibră de sticlă', [978, 967]),
     ('Клей ПВА', 'Adeziv PVA', [977]),
     ('Монтажный клей и жидкие гвозди', 'Adeziv de montaj și cuie lichide', [973, 970]),
     ('Клей для потолочной плитки', 'Adeziv pentru plăci de tavan', [975]),
     ('Клей для линолеума и паркета', 'Adeziv pentru linoleum și parchet', [971, 969]),
     ('Клей универсальный и секундный', 'Adeziv universal și instant', [972, 966]),
     ('Клеевые стержни', 'Batoane de silicon pentru pistol de lipit', [976]),
   ]),
 ]),
 ('Двери и фурнитура', 'Uși și feronerie', [
   ('Двери', 'Uși', [
     ('Двери входные', 'Uși metalice de intrare', [839]),
     ('Двери межкомнатные', 'Uși de interior', [814]),
   ]),
   ('Замки и дверная фурнитура', 'Încuietori și feronerie pentru uși', [
     ('Замки врезные', 'Broaște încastrate', [1295]),
     ('Замки навесные', 'Lacăte', [1299]),
     ('Цилиндры (личинки)', 'Cilindri (miezuri de yală)', [1300]),
     ('Ручки дверные', 'Mânere de ușă', [1298]),
     ('Петли', 'Balamale', [1297]),
     ('Фурнитура для окон и дверей', 'Feronerie pentru uși și ferestre', [1296]),
     ('Почтовые ящики', 'Cutii poștale', [1471]),
   ]),
   ('Мебельная фурнитура', 'Feronerie pentru mobilă', [(None, None, [877])]),
 ]),
 ('Инструмент', 'Instrumente', [
   ('Ручной инструмент', 'Instrumente manuale', [
     ('Измерительный инструмент', 'Instrumente de măsurat', [929, 930, 931]),
     ('Уровни', 'Nivele', [922]),
     ('Молотки и слесарный инструмент', 'Ciocane și instrumente de lăcătușerie', [918]),
     ('Ключи', 'Chei', [920]),
     ('Отвёртки', 'Șurubelnițe', [924]),
     ('Плоскогубцы, кусачки, бокорезы', 'Clești, cleștișori de tăiat', [915]),
     ('Напильники, рашпили, надфили', 'Pile, rașpile', [923]),
     ('Ножовки и стусла', 'Fierăstraie de mână și cutii de tăiat', [917, 927]),
     ('Топоры', 'Topoare', [928]),
     ('Струбцины', 'Menghine de strângere (clame)', [913]),
     ('Ножи строительные', 'Cuțite de construcții', [921]),
     ('Степлеры и заклёпочники', 'Capsatoare și clești de nituit', [912]),
     ('Пистолеты для герметика и пены', 'Pistoale pentru silicon și spumă', [925]),
     ('Прочий ручной инструмент', 'Alte instrumente manuale', [910]),
   ]),
   ('Малярный и штукатурный инструмент', 'Instrumente pentru vopsit și tencuit', [
     ('Кисти', 'Pensule', [945]),
     ('Валики и кюветы', 'Trafalete și tăvițe', [916]),
     ('Шпатели', 'Șpacluri', [926]),
     ('Штукатурный инструмент', 'Instrumente pentru tencuit', [911]),
     ('Тёрки', 'Drișcă', [944]),
     ('Насадки-миксеры', 'Palete de amestecat', [946]),
   ]),
   ('Плиточный инструмент', 'Instrumente pentru faianță', [
     ('Плиткорезы', 'Mașini de tăiat faianță', [919]),
     ('Крестики и клинья для плитки', 'Distanțiere și pene pentru faianță', [1433]),
   ]),
   ('Электроинструмент', 'Instrumente electrice', [
     ('Дрели и шуруповёрты', 'Bormașini și șurubelnițe cu acumulator', [889, 1259]),
     ('Перфораторы', 'Perforatoare', [904]),
     ('Болгарки (УШМ)', 'Polizoare unghiulare (flex)', [890]),
     ('Шлифмашины', 'Mașini de șlefuit', [896]),
     ('Электролобзики', 'Fierăstraie pendulare', [894]),
     ('Пилы электрические и бензопилы', 'Fierăstraie electrice și drujbe', [892]),
     ('Строительные миксеры', 'Mixere pentru construcții', [895]),
     ('Строительные фены', 'Pistoale cu aer cald', [891]),
     ('Краскопульты и пистолеты для штукатурки', 'Pistoale de vopsit și de tencuit', [900, 903, 902]),
     ('Сварочные аппараты и расходники', 'Aparate de sudură și consumabile', [897, 898]),
     ('Паяльники', 'Ciocane de lipit', [887]),
     ('Мойки высокого давления', 'Aparate de spălat cu presiune', [899]),
     ('Бетономешалки', 'Betoniere', [893]),
     ('Триммеры и леска', 'Trimmere și fir', [905, 906]),
     ('Насосы для накачки, пневмопистолеты, шланги', 'Pompe de umflat, pistoale pneumatice, furtunuri', [914, 901]),
     ('Электрорубанки', 'Rindele electrice', [SORT]),
     ('Фрезеры и штроборезы', 'Mașini de frezat și canelat', [SORT]),
     ('Реноваторы (многофункциональные)', 'Renovatoare (unelte multifuncționale)', [SORT]),
     ('Воздуходувы', 'Suflante', [SORT]),
     ('Аппараты для сварки пластиковых труб', 'Aparate de sudat țevi din plastic', [SORT]),
     ('Аккумуляторы и зарядные устройства', 'Acumulatori și încărcătoare', [SORT]),
     ('Прочий электроинструмент', 'Alte instrumente electrice', [SORT]),
   ]),
   ('Расходники к инструменту', 'Consumabile pentru instrumente', [
     ('Диски алмазные', 'Discuri diamantate', [955]),
     ('Диски по металлу', 'Discuri pentru metal', [956]),
     ('Диски по дереву', 'Discuri pentru lemn', [959]),
     ('Диски по камню', 'Discuri pentru piatră', [954]),
     ('Диски шлифовальные и полировальные', 'Discuri de șlefuit și lustruit', [957, 953]),
     ('Наждачная бумага и абразивы', 'Hârtie abrazivă și abrazive', [958]),
     ('Свёрла по бетону', 'Burghie pentru beton', [935]),
     ('Свёрла по металлу', 'Burghie pentru metal', [938, 939, 940, 937]),
     ('Свёрла по дереву', 'Burghie pentru lemn', [936]),
     ('Свёрла по стеклу и плитке', 'Burghie pentru sticlă și faianță', [941]),
     ('Свёрла универсальные', 'Burghie universale', [934]),
     ('Коронки', 'Carote', [933]),
     ('Биты и держатели', 'Biți și suporturi', [1429]),
     ('Полотна для ножовок и лобзиков', 'Pânze pentru fierăstraie', [948]),
     ('Цепи для пил', 'Lanțuri pentru fierăstraie', [SORT]),
     ('Щётки-крацовки', 'Perii de sârmă', [949, 950, 951]),
     ('Щётки угольные для электроинструмента', 'Perii de cărbune pentru instrumente electrice', [888, 907]),
     ('Скобы для степлера', 'Capse pentru capsator', [942]),
   ]),
   ('Лестницы и стремянки', 'Scări', [(None, None, [908])]),
   ('Спецодежда и средства защиты', 'Echipament de protecție', [
     ('Перчатки', 'Mănuși', [1348]),
     ('Маски и респираторы', 'Măști și respiratoare', [1350]),
     ('Очки защитные', 'Ochelari de protecție', [1347]),
     ('Спецодежда и рабочая обувь', 'Haine și încălțăminte de lucru', [1349, 1437]),
     ('Плащи-дождевики', 'Pelerine de ploaie', [1373]),
   ]),
 ]),
 ('Крепёж', 'Elemente de fixare', [
   ('Метизы', 'Șuruburi și piulițe', [
     ('Саморезы, шурупы, винты', 'Șuruburi autofiletante și holșuruburi', [1422, 1423]),
     ('Болты', 'Buloane', [1430]),
     ('Гайки и шайбы', 'Piulițe și șaibe', [1431]),
     ('Гвозди', 'Cuie', [1434]),
     ('Прочий крепёж', 'Alte elemente de fixare', [1420]),
   ]),
   ('Дюбели и анкеры', 'Dibluri și ancore', [
     ('Дюбели', 'Dibluri', [1421]),
     ('Анкеры', 'Ancore', [1424]),
   ]),
   ('Специальный крепёж', 'Elemente de fixare speciale', [
     ('Заклёпки', 'Nituri', [1435]),
     ('Крепёж для дерева', 'Elemente de fixare pentru lemn', [1436]),
     ('Крепёж для сантехники и бойлеров', 'Fixări pentru obiecte sanitare și boilere', [1425, 1432]),
     ('Крепёж для электромонтажа', 'Fixări pentru instalații electrice', [1426]),
     ('Крепёж по DIN', 'Elemente de fixare DIN', [1427]),
   ]),
   ('Такелаж, верёвки', 'Lanțuri, cabluri, frânghii', [
     ('Цепи', 'Lanțuri', [1280]),
     ('Тросы', 'Cabluri de oțel', [1281, 1279]),
     ('Такелажный крепёж', 'Accesorii pentru cabluri', [1428]),
     ('Верёвки, шпагат, обвязочный материал', 'Frânghii, sfoară, materiale de legat', [816]),
   ]),
 ]),
 ('Электрика и освещение', 'Electrice și iluminat', [
   ('Электроустановочные изделия', 'Materiale electrice de instalare', [
     ('Розетки и выключатели', 'Prize și întrerupătoare', [1285]),
     ('Вилки, тройники, переходники', 'Ștechere, triplu-prize, adaptoare', [1283]),
     ('Удлинители и сетевые фильтры', 'Prelungitoare și filtre de rețea', [1292]),
     ('Клеммники и распределительные коробки', 'Cleme și doze de distribuție', [1284]),
     ('Прочие электроустановочные изделия', 'Alte materiale electrice', [1286, 1287, 1289]),
     ('Звонки дверные', 'Sonerii', [1288]),
     ('Мультиметры, термоусадка, реле', 'Multimetre, tub termocontractabil, relee', [1282]),
   ]),
   ('Кабель и изоляция', 'Cabluri și izolație', [
     ('Провода и кабели', 'Conductori și cabluri', [1290]),
     ('Изоляторы и изолента', 'Izolatori și bandă izolatoare', [1291, 943]),
   ]),
   ('Освещение', 'Iluminat', [
     ('Лампы', 'Becuri', [1376]),
     ('Люстры', 'Lustre', [1465]),
     ('Светильники', 'Corpuri de iluminat', [1375]),
     ('Споты', 'Spoturi', [1377]),
     ('Настольные лампы', 'Veioze', [1409]),
     ('Прожекторы', 'Proiectoare', [1388]),
   ]),
   ('Фонари и батарейки', 'Lanterne și baterii', [
     ('Фонари', 'Lanterne', [964]),
     ('Батарейки', 'Baterii', [965]),
   ]),
 ]),
 ('Сантехника и ванная', 'Sanitare și baie', [
   ('Смесители и подводка', 'Baterii sanitare și racorduri', [
     ('Смесители и комплектующие', 'Baterii sanitare și accesorii', [884, 1351]),
     ('Шланги для смесителей', 'Furtunuri pentru baterii sanitare', [885]),
     ('Шланги для подключения воды', 'Furtunuri de racordare la apă', [1356, 1167]),
     ('Шланги для стиральных машин', 'Furtunuri pentru mașini de spălat', [1354]),
     ('Прокладки', 'Garnituri', [1372]),
   ]),
   ('Слив и канализация', 'Scurgere și canalizare', [
     ('Сифоны', 'Sifoane', [1353]),
     ('Тросы сантехнические и вантузы', 'Spirale de desfundat și pompe de desfundat', [1352, 1272, 1271]),
   ]),
   ('Унитазы и умывальники', 'Vase WC și lavoare', [
     ('Унитазы, умывальники', 'Vase WC, lavoare', [1470]),
     ('Арматура для бачка', 'Mecanisme pentru rezervor WC', [1355]),
   ]),
   ('Аксессуары для ванной', 'Accesorii pentru baie', [
     ('Шторки для ванной', 'Perdele pentru baie', [1371]),
     ('Карнизы для ванной', 'Bare pentru perdele de baie', [872]),
     ('Принадлежности для туалета', 'Accesorii pentru WC', [876, 871]),
     ('Коврики для ванной', 'Covorașe pentru baie', [981]),
     ('Прочие аксессуары для ванной', 'Alte accesorii pentru baie', [1225]),
   ]),
 ]),
 ('Сад и огород', 'Grădină', [
   ('Садовый инструмент', 'Unelte de grădină', [
     ('Лопаты, грабли, вилы', 'Lopeți, greble, furci', [820]),
     ('Секаторы и инструмент для обрезки', 'Secatoare și unelte de tăiat', [822]),
     ('Тачки', 'Roabe', [836]),
     ('Колёса и запчасти для тачек', 'Roți și piese pentru roabe', [837, 838]),
     ('Для уборки листвы и мусора', 'Pentru strângerea frunzelor și gunoiului', [823]),
     ('Прочий садовый инвентарь', 'Alt inventar de grădină', [815]),
   ]),
   ('Полив', 'Udare', [
     ('Шланги для полива', 'Furtunuri pentru udat', [833]),
     ('Пистолеты и оросители', 'Pistoale și aspersoare', [832]),
     ('Фитинги и аксессуары для полива', 'Racorduri și accesorii pentru udat', [834, 835, 831]),
     ('Насосы', 'Pompe', [817, 818]),
   ]),
   ('Защита растений', 'Protecția plantelor', [
     ('Удобрения', 'Îngrășăminte', [819]),
     ('Средства от болезней и сорняков', 'Fungicide și erbicide', [827, 826]),
     ('Опрыскиватели и запчасти', 'Stropitori și piese de schimb', [821]),
     ('От насекомых и грызунов', 'Împotriva insectelor și rozătoarelor', [1168]),
     ('Укрывной материал', 'Material de acoperire', [824]),
   ]),
   ('Посадка и выращивание', 'Plantare și cultivare', [
     ('Грунт и субстраты', 'Pământ și substraturi', [829]),
     ('Вазоны и кашпо', 'Ghivece', [1231, 856]),
     ('Подвязка растений', 'Legarea plantelor', [825]),
     ('Садовые заборчики и ограждения', 'Garduri și borduri de grădină', [828]),
     ('Живые цветы', 'Flori naturale', [830]),
   ]),
   ('Хозяйство и дача', 'Gospodărie și vilă', [
     ('Бидоны и бочки', 'Bidoane și butoaie', [961]),
     ('Газовые баллоны и горелки', 'Butelii de gaz și arzătoare', [845, 846]),
   ]),
 ]),
 ('Товары для дома', 'Mărfuri de uz casnic', [
   ('Уборка и бытовая химия', 'Curățenie și chimie de uz casnic', [
     ('Средства для уборки', 'Detergenți pentru curățenie', [882, SORT]),
     ('Средства для стирки', 'Detergenți pentru rufe', [SORT]),
     ('Мыло', 'Săpun', [SORT]),
     ('Губки и салфетки', 'Bureți și lavete', [1277, 1370, 1276]),
     ('Швабры, тряпки, прищепки', 'Mopuri, cârpe, cârlige de rufe', [1269]),
     ('Щётки, мётлы, веники', 'Perii, mături', [883, 881]),
     ('Совки', 'Fărașe', [859]),
     ('Вёдра и тазы', 'Găleți și lighene', [869, 870, 1278]),
     ('Мусорные вёдра', 'Coșuri de gunoi', [850]),
     ('Мешки для мусора и плёнка', 'Saci de gunoi și folie', [1171, 1258]),
     ('Освежители воздуха', 'Odorizante', [1215]),
   ]),
   ('Кухня', 'Bucătărie', [
     ('Кухонные принадлежности', 'Ustensile de bucătărie', [866]),
     ('Посуда', 'Veselă', [878, 879, 880, 1345]),
     ('Банки стеклянные', 'Borcane de sticlă', [868]),
     ('Контейнеры пищевые', 'Recipiente alimentare', [843]),
     ('Ножи и ножницы', 'Cuțite și foarfece', [848]),
     ('Корзины', 'Coșuri', [867]),
     ('Клеёнка и скатерти', 'Mușama și fețe de masă', [842]),
     ('Спички и зажигалки', 'Chibrituri și brichete', [851, 847]),
   ]),
   ('Хранение и порядок', 'Depozitare și ordine', [
     ('Полки и этажерки', 'Rafturi și etajere', [873]),
     ('Полки для обуви', 'Rafturi pentru încălțăminte', [854]),
     ('Вешалки', 'Umerașe', [857]),
     ('Сушилки для белья', 'Uscătoare de rufe', [874]),
     ('Зеркала', 'Oglinzi', [875]),
     ('Карнизы для штор', 'Cornișe pentru perdele', [849]),
   ]),
   ('Коврики и текстиль', 'Covorașe și textile', [
     ('Коврики придверные', 'Covorașe de intrare', [980]),
     ('Коврики для комнат', 'Covorașe pentru camere', [983, 984, 985]),
     ('Постельное бельё и полотенца', 'Lenjerie de pat și prosoape', [855, 1270]),
   ]),
   ('Бытовая техника', 'Electrocasnice', [
     ('Электроприборы', 'Aparate electrice', [844, 852]),
     ('Обогреватели', 'Radiatoare și încălzitoare', [853]),
     ('Часы', 'Ceasuri', [858]),
     ('Термометры', 'Termometre', [865]),
   ]),
   ('Декор и интерьер', 'Decor și interior', [
     ('Вазы', 'Vaze', [1230, 1226]),
     ('Свечи', 'Lumânări', [1232]),
     ('Искусственные цветы', 'Flori artificiale', [1228]),
     ('Декоративная галька', 'Pietriș decorativ', [1217]),
     ('Сувениры', 'Suveniruri', [1229, 1216]),
     ('Сезонный декор', 'Decor de sezon', [1464, 1219, 1218]),
     ('Подарочные пакеты', 'Pungi de cadou', [1438]),
     ('Мелочи для дома', 'Mărunțișuri pentru casă', [1227, 841]),
   ]),
 ]),
 ('Детям, отдых, канцтовары', 'Copii, odihnă, papetărie', [
   ('Игрушки', 'Jucării', [
     ('Мягкие игрушки', 'Jucării de pluș', [863]),
     ('Все игрушки', 'Toate jucăriile', [862]),
   ]),
   ('Для детей', 'Pentru copii', [
     ('Для творчества', 'Pentru creativitate', [864]),
     ('Книги', 'Cărți', [861]),
     ('Детские коврики', 'Covorașe pentru copii', [982]),
     ('Детские ванночки и прочее', 'Căzi pentru copii și altele', [860]),
   ]),
   ('Спорт и отдых', 'Sport și odihnă', [
     ('Спорт, пикник и пляж', 'Sport, picnic și plajă', [962]),
     ('Гаджеты и аксессуары', 'Gadgeturi și accesorii', [963]),
     ('Обувь', 'Încălțăminte', [1344]),
   ]),
   ('Канцтовары', 'Papetărie', [
     ('Мел', 'Cretă', [1416]),
     ('Все канцтовары', 'Toată papetăria', [1415]),
   ]),
 ]),
]

# Служебная категория: сюда попадает товар, который не удалось разложить.
# Скрыта от покупателей (см. casa24-catalog-map.php), её разбирают вручную.
UNSORTED = ('Не разобрано', 'Nesortat')

# Куда ведёт страница старой категории-«папки» (301), если не туда, куда уходят её
# собственные товары: ID старой категории -> RU-название новой категории.
REDIRECTS = {
    813: 'Двери', 1294: 'Замки и дверная фурнитура', 1346: 'Спецодежда и средства защиты',
    1301: 'Отделочные материалы', 1374: 'Освещение', 909: 'Расходники к инструменту',
    952: 'Расходники к инструменту', 933: 'Расходники к инструменту', 831: 'Полив', 886: 'Электроинструмент',
    910: 'Ручной инструмент', 1420: 'Крепёж', 1282: 'Электрика и освещение', 815: 'Сад и огород',
    866: 'Кухня', 962: 'Спорт и отдых', 1269: 'Уборка и бытовая химия', 841: 'Товары для дома',
    1384: 'Пиломатериалы и плиты', 1226: 'Декор и интерьер', 1216: 'Декор и интерьер',
    1351: 'Сантехника и ванная', 860: 'Для детей', 979: 'Коврики и текстиль', 1279: 'Такелаж, верёвки',
    1172: 'Краски и лаки', 1178: 'Краски и лаки', 1185: 'Эмали', 1175: 'Краски для стен и потолков',
    1182: 'Лаки и пропитки',
}

# Товары, которые правила по названию раскладывают неверно: ID товара -> RU-название подгруппы.
# None — в «Не разобрано».
PRODUCT_OVERRIDES = {
    1057465: 'Средства для стирки',      # Мешок для стирки круглый Jing Ting
    1086684: 'Эмали акриловые',          # НЦ 132 — нитроэмаль; ближе всего к быстросохнущим эмалям
    1087820: None,                       # «Памперсы» в группе SANO — непонятный товар
    1089246: 'Кухонные принадлежности',  # Щипцы для кухни (лежали в «Средствах для стирки»)
    1090753: 'Кухонные принадлежности',
    1089129: 'Кухонные принадлежности',
}

# Металлические двери без категории 1С (стоят только в категории по умолчанию,
# в каталоге их не найти) — 28.10.2026.
for _pid in (1055407, 1055408, 1055409, 1055410, 1055411, 1055412, 1055413, 1055414, 1055415, 1055416, 1055417,
             1055418, 1055419, 1055420, 1055423, 1055424):
    PRODUCT_OVERRIDES[_pid] = 'Двери входные'
