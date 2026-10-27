# NEXU AI Translator: инструкции для перевода RU → RO с молдавским уклоном

> **27.10.2026: NEXU выключен на проде и staging** (решение владельца: переводит некорректно — см. `docs/ro-drafts-review-2026-10-27.md`). Переводы делает Claude по skill `.claude/skills/perevod-ro/SKILL.md`, без NEXU. Правила и словарь ниже остаются в силе как требования к переводу. 27.10.2026 так переведено и опубликовано 90 товаров (82 + 8 черновиков); без перевода оставлен только служебный «Сертификат качества на бидоны».

Плагин NEXU AI Translation Addon for WPML 3.17.8 (wp-admin → NEXU → Settings) переводит задания WPML через Google Gemini (`gemini-2.5-flash-lite`). До 27.10.2026 в нём стоял шаблон вендора «контент о плагинах WordPress, используй терминологию WordPress», а поля своих инструкций были пустыми. Ниже — что ставим вместо этого. Источник терминов — `docs/glossary-ro-md.md`; при изменении глоссария обновлять и эти тексты.

**Решение владельца 27.10.2026:** провайдер остаётся Google Gemini (вариант с Claude через API Anthropic рассматривался и отложен); ключ Gemini API не меняем, хотя 27.10 он один раз попал в журнал рабочей сессии Claude (не в репозиторий).

Инструкции написаны по-английски: модель точнее выполняет правила на английском, а сами термины даны парами RU → RO.

## 1. Translation Prompt (`translation_prompt`) — меняется только блок CONTEXT

Было:

> CONTEXT: The content is about WordPress plugins and will be published directly to a live WordPress site or plugin. Use WordPress terminology consistently (shortcode, widget, hook, theme compatibility, plugin, admin, settings, etc.).

Стало:

```
CONTEXT: The content is from casa24.md, an online store of building materials, tools and household goods in Chișinău, Republic of Moldova. Texts are product names, product descriptions, category names and store pages. The audience is shoppers in Moldova: write Romanian as it is used and searched for in Moldova (see the custom instructions for preferred terms), in a clear, neutral retail tone.
```

Остальные правила шаблона (сохранять HTML, пробелы, не трогать коды/артикулы/URL, не добавлять комментариев) — без изменений.

## 2. Custom Instructions (общие, `custom_instructions`)

```
Target audience: customers in the Republic of Moldova. Use standard literary Romanian (Latin script) with correct diacritics: ă, â, î, ș, ț — always ș and ț with COMMA below (U+0219, U+021B), never ş/ţ with cedilla.
When Moldovan usage and Romania usage differ, use the Moldovan word:
- оплата → achitare (not "plată"); удобная оплата → achitare comodă
- товары → mărfuri; хозтовары → mărfuri de uz casnic
- контакты → Contacte; личный кабинет → Cabinet personal; регистрация → Înregistrare
- акции (promotions) → Promoții (never "acțiuni"); скидка → reducere; дисконтная карта → card-discount
- дача → vilă (la vilă)
- обмен и возврат → Schimbul și returnarea mărfii
- электроинструмент → instrumente electrice (not "scule electrice")
- дрель → bormașină; шуруповёрт → șurubelniță cu acumulator; перфоратор → perforator
- пила → fierăstrău (pl. fierăstraie); гипс → ghips (not "ipsos"); штукатурка → tencuială; краска → vopsea
- межкомнатная дверь → ușă de interior; входная металлическая дверь → ușă metalică de intrare
- секатор → secator; поливочный шланг → furtun pentru udat; тачка → roabă
- ведро → găleată (pl. găleți), BUT мусорное ведро → coș de gunoi; бочка → butoi (pl. butoaie); банки для консервации → borcane
- doors: дверное полотно → canat de ușă; дверная коробка → toc (masculine: „un toc”, „tocul”); наличник → ancadrament (pl. ancadramente); ручка двери → mâner; фурнитура → feronerie
Units: always Latin symbols with a space, never Cyrillic: Вт → W, кВт → kW, В → V, мАч → mAh, об/мин → rot/min, кг → kg, г → g, л → l, мм → mm, см → cm, м → m (30 kg, 980 W, 3000 mAh). Dimensions like 2000*800 stay as in source. Currency: lei / MDL, never RON. Quotes: „…”.
Never add anything that is not in the source: no explanations in parentheses, no notes like "(cod …)", no extra adjectives.
Check Romanian grammar: gender and article agreement (un toc, o ușă), correct plural forms.
Never translate or change: SKUs and article codes, model names and numbers (Model R14, KM01, DHMS-ST05), brand names (Zaruba, Wokin, Bosch, Ceresit, Supraten, Class Nas…), sizes and dimensions like 2000*800*34, standards (DIN127A, HSS).
If the source text is already in Romanian (supplier text), return it corrected: add missing diacritics, fix obvious typos, keep the meaning; do not translate it again.
```

## 3. Custom Instructions — Titles (`custom_instructions_titles`)

```
These are product and category names for an online store. Translate the product type into natural Moldovan Romanian and keep brand, model, size and code exactly as in the source, in the same order where natural. Do not add words that are not in the source (no "de calitate", "ieftin", etc.). Capitalize only the first word and proper nouns. Example: "Дверь межкомнатная Model R14" → "Ușă de interior Model R14"; "Ключ рожково-накидной 17мм Zaruba" → "Cheie combinată 17 mm Zaruba".
```

## 4. Custom Instructions — Body (`custom_instructions_body`)

```
These are product descriptions. Keep all HTML structure, lists and line breaks. Translate characteristics accurately (material, dimensions, set contents, purpose); keep numbers and units unchanged except for adding a space before units. Do not invent characteristics, advantages or marketing claims that are not in the source.
```

## 5. Custom Instructions — Terms (`custom_instructions_terms`)

```
These are product category names shown in the catalog menu. Use short plural Moldovan Romanian names: "Сад и огород" → "Grădină", "Хозтовары" → "Mărfuri de uz casnic", "Электроинструмент" → "Instrumente electrice", "Двери" → "Uși".
```

## Порядок применения

**27.10.2026: шаг 1 выполнен на staging** — тексты стоят (прежние значения: `~/backups/nexu-settings-staging-20261027.json` на сервере), модель `gemini-2.5-flash-lite` без изменений. Сравнение на 7 товарах — `docs/nexu-compare-2026-10-27.md`.

1. Поставить тексты на **staging** (те же поля), перевести там 5–10 товаров разного типа (дверь, инструмент, химия, товар с румынским описанием от поставщика) и сравнить с текущими RO-переводами.
2. Владелец смотрит примеры. После «да» — те же тексты на проде.
3. Новые товары без RO (~90 на 27.10.2026): NEXU → Bulk Tools → «Untranslated Only» → товары → румынский; сначала 5–10 штук, проверка, затем остальные.
4. Регулярно для новых товаров из 1С: решить отдельно — вручную раз в неделю через Bulk Tools или автоматически (mu-plugin, который после обмена с 1С ставит новые товары в очередь перевода WPML).

## Риски

- Обмен с 1С пишет описание в RU-товар при каждой выгрузке (опция «Пропустить описание» выключена). RO-переводы он не трогает, но переведённый текст останется от старой версии RU-описания, если в 1С описание поменяют.
- Gemini flash-lite — недорогая модель; для названий и коротких описаний её обычно хватает, качество проверяем на примерах шага 1.
