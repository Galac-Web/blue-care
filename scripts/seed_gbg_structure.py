import json
import re
from pathlib import Path

root = Path(__file__).resolve().parents[1]
cache = root / "robot/profil_gbg_user_01/Default/Cache/Cache_Data/f_000177"
if not cache.is_file():
    cache = root / "robot/profil_gbg_gbg_user_01/Default/Cache/Cache_Data/f_000177"
html = cache.read_text(encoding="utf-8", errors="replace")

brands = []
for m in re.finditer(r"BrandClick\('([^']+)'[^>]*>.*?<span>([^<]+)</span>", html, re.S):
    brands.append({"id": m.group(1), "name": m.group(2).strip(), "logo_src": ""})

special = []
for m in re.finditer(
    r"FormIntClick1\('','','(\d+)'[^>]*>.*?<span[^>]*FormIntClick1[^>]*>([^<]+)</span>",
    html,
    re.S,
):
    special.append({"id": m.group(1), "label_gbg": m.group(2).strip()})

out = {
    "version": 1,
    "brands": brands,
    "special_categories": special,
    "model_groups": {},
    "main_categories_by_model": {},
    "source": "chrome_cache_seed",
}
path = root / "data/gbg_structure.json"
path.parent.mkdir(exist_ok=True)
path.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
print(f"Salvat {path}: {len(brands)} marci, {len(special)} categorii speciale")
