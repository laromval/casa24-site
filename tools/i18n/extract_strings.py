import re
import sys

root = sys.argv[1]
out = sys.argv[2]
files = [
    root + "/wp-content/themes/glozin-child/template-homepage-industrial-pro.php",
    root + "/wp-content/themes/glozin-child/inc/industrial-pro.php",
    root + "/wp-content/themes/glozin-child/template-parts/industrial-pro/header.php",
    root + "/wp-content/themes/glozin-child/template-parts/industrial-pro/footer.php",
]
pat = re.compile(r"(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'glozin-child'\s*\)")
strings = []
for f in files:
    s = open(f, encoding="utf-8").read()
    for m in pat.finditer(s):
        v = m.group(1).replace("\\'", "'")
        if v not in strings:
            strings.append(v)
open(out, "w", encoding="utf-8").write("\n".join(strings))
print(len(strings))
