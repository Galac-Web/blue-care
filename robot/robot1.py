"""
Robot scanare gbg-eshop → export date pe SERVER Blue-Car (TecDoc/RapidAPI).

Ruleaza oriunde (PC local, alt birou). Nu necesita Laragon.
Doar trimitere HTTP catre serverul blu-car.ro.

Config: robot_config.json (langa script) sau variabila BLU_SITE_API
"""
import os
import socket
import subprocess
import sys
import threading
import time
import json
import re
import urllib.request
import urllib.error
import urllib.parse
from pathlib import Path
from flask import Flask, request, jsonify
from flask_cors import CORS
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import undetected_chromedriver as uc

if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass


def safe_print(msg):
    try:
        print(msg)
    except UnicodeEncodeError:
        enc = sys.stdout.encoding or "ascii"
        print(msg.encode(enc, errors="replace").decode(enc, errors="replace"))


try:
    from robot_workflow_engine import (
        load_workflow_file,
        save_workflow_file,
        run_workflow_steps,
        build_context,
        close_all_banners,
    )
    HAS_WORKFLOW_ENGINE = True
except ImportError:
    HAS_WORKFLOW_ENGINE = False

app = Flask(__name__)
CORS(app)

SCRIPT_DIR = Path(__file__).resolve().parent
CONFIG_FILE = SCRIPT_DIR / "robot_config.json"

DEFAULT_API_URLS = [
    "https://blu-car.ro/robot-oem.php",
    "https://blu-car.ro/api/robot-oem.php",
]
DEFAULT_MONITOR = "https://blu-car.ro/robot-monitor.php"
DEFAULT_API_KEY = "19921705"


def load_robot_config():
    cfg = {
        "mode": "remote",
        "site_api_urls": list(DEFAULT_API_URLS),
        "monitor_url": DEFAULT_MONITOR,
        "robot_api_key": DEFAULT_API_KEY,
    }
    if CONFIG_FILE.is_file():
        try:
            with open(CONFIG_FILE, "r", encoding="utf-8") as f:
                data = json.load(f)
            if isinstance(data, dict):
                if isinstance(data.get("site_api_urls"), list) and data["site_api_urls"]:
                    cfg["site_api_urls"] = [str(u).rstrip("/") for u in data["site_api_urls"]]
                if data.get("site_api_url"):
                    cfg["site_api_urls"].insert(0, str(data["site_api_url"]).rstrip("/"))
                if data.get("monitor_url"):
                    cfg["monitor_url"] = str(data["monitor_url"])
                if data.get("robot_api_key"):
                    cfg["robot_api_key"] = str(data["robot_api_key"])
                if data.get("mode"):
                    cfg["mode"] = str(data["mode"])
        except Exception as ex:
            print(f"[config] Nu pot citi {CONFIG_FILE}: {ex}")
    if cfg.get("mode") == "remote":
        cfg["site_api_urls"] = [
            u for u in cfg["site_api_urls"]
            if "blu-car.ro" in u and "127.0.0.1" not in u and "localhost" not in u
        ]
        if not cfg["site_api_urls"]:
            cfg["site_api_urls"] = list(DEFAULT_API_URLS)
    seen = set()
    unique = []
    for u in cfg["site_api_urls"]:
        if u not in seen:
            seen.add(u)
            unique.append(u)
    cfg["site_api_urls"] = unique
    return cfg


ROBOT_CFG = load_robot_config()
SITE_API_URLS = ROBOT_CFG["site_api_urls"]
MONITOR_URL = ROBOT_CFG["monitor_url"]
ROBOT_API_KEY = (
    os.environ.get("ROBOT_API_KEY", "").strip()
    or str(ROBOT_CFG.get("robot_api_key", "")).strip()
    or DEFAULT_API_KEY
)

_env_url = os.environ.get("BLU_SITE_API", "").strip().rstrip("/")
if _env_url:
    SITE_API_URLS = [_env_url] + [u for u in SITE_API_URLS if u != _env_url]

SITE_API_URL = SITE_API_URLS[0]
SITE_API_URL_ACTIV = SITE_API_URL

browsere_active = {}
status_clienti = {}
jurnal_clienti = {}
stop_flags = {}
step_counters = {}
scan_active = {}
scan_optiuni = {}      # optiuni per cont_id: scan_from, scan_to, skip_duplicate
scanate_cache = {}     # set-uri de URL-uri / coduri deja scanate, per cont_id
_launch_lock = threading.Lock()
_launch_threads = {}   # cont_id -> Thread activ

STATE_FILE = SCRIPT_DIR / "robot_state.json"


class _ScanDone(Exception):
    """Ridicata cand s-a atins capatul intervalului scan_to — opreste scanarea curat."""
    pass


class _TecDocStop(Exception):
    """Ridicata cand serverul/TecDoc raspunde fals (eroare API/cheie/conexiune) — opreste scanarea."""
    pass


def get_scan_opt(cont_id):
    return scan_optiuni.get(cont_id, {"scan_from": 1, "scan_to": 0, "skip_duplicate": True})


def _scanate_file(cont_id):
    safe = "".join(c for c in str(cont_id) if c.isalnum() or c in ("_", "-")) or "default"
    return SCRIPT_DIR / f"scanate_{safe}.json"


def incarca_scanate(cont_id):
    """Incarca (cu cache) URL-urile si codurile deja scanate pentru un cont."""
    if cont_id in scanate_cache:
        return scanate_cache[cont_id]
    urls, coduri = set(), set()
    f = _scanate_file(cont_id)
    if f.is_file():
        try:
            with open(f, "r", encoding="utf-8") as fh:
                data = json.load(fh)
            urls = set(data.get("urls") or [])
            coduri = set(data.get("coduri") or [])
        except Exception as ex:
            print(f"[scanate] Nu pot citi {f}: {ex}")
    scanate_cache[cont_id] = {"urls": urls, "coduri": coduri}
    return scanate_cache[cont_id]


def salveaza_scanate(cont_id):
    rec = scanate_cache.get(cont_id)
    if rec is None:
        return
    try:
        with open(_scanate_file(cont_id), "w", encoding="utf-8") as fh:
            json.dump({
                "urls": sorted(rec["urls"]),
                "coduri": sorted(rec["coduri"]),
                "updated_at": time.strftime("%Y-%m-%d %H:%M:%S"),
            }, fh, ensure_ascii=False, indent=2)
    except Exception as ex:
        print(f"[scanate] Nu pot salva: {ex}")


def este_scanat(cont_id, url="", cod=""):
    rec = incarca_scanate(cont_id)
    if url and url in rec["urls"]:
        return True
    if cod and cod in rec["coduri"]:
        return True
    return False


def marcheaza_scanat(cont_id, url="", cod=""):
    rec = incarca_scanate(cont_id)
    if url:
        rec["urls"].add(url)
    if cod:
        rec["coduri"].add(cod)
    salveaza_scanate(cont_id)


def numar_scanate(cont_id):
    rec = incarca_scanate(cont_id)
    return len(rec["urls"] or rec["coduri"])


def reset_scanate(cont_id):
    scanate_cache.pop(cont_id, None)
    f = _scanate_file(cont_id)
    try:
        if f.is_file():
            f.unlink()
    except Exception as ex:
        print(f"[scanate] Nu pot sterge {f}: {ex}")

GBG_BASE = "http://www.gbg-eshop.gr/demo"
GBG_LOGIN = f"{GBG_BASE}/Authenticate3.aspx"
GBG_APP = f"{GBG_BASE}/gbg_wapp.aspx"
gbg_site_urls = {}


def _gbg_urls_from_site_url(site_url: str) -> dict:
    site_url = (site_url or "").strip()
    if not site_url:
        return {"base": GBG_BASE, "login": GBG_LOGIN, "app": GBG_APP}
    u = site_url.rstrip("/")
    low = u.lower()
    if "authenticate" in low:
        base = u.rsplit("/", 1)[0]
    elif "gbg_wapp" in low:
        base = u.rsplit("/", 1)[0]
    else:
        base = u
    return {
        "base": base,
        "login": f"{base}/Authenticate3.aspx",
        "app": f"{base}/gbg_wapp.aspx",
    }


def set_gbg_urls_for_cont(cont_id: str, site_url: str = "") -> dict:
    urls = _gbg_urls_from_site_url(site_url)
    gbg_site_urls[cont_id] = urls
    return urls


def get_gbg_urls(cont_id: str) -> dict:
    return gbg_site_urls.get(cont_id) or {
        "base": GBG_BASE,
        "login": GBG_LOGIN,
        "app": GBG_APP,
    }


def _salveaza_captura_gbg(driver, cont_id: str, tag: str = "debug") -> str:
    try:
        out_dir = SCRIPT_DIR / "debug_snapshots"
        out_dir.mkdir(exist_ok=True)
        ts = time.strftime("%Y%m%d_%H%M%S")
        path = out_dir / f"{cont_id}_{tag}_{ts}.png"
        driver.save_screenshot(str(path))
        return str(path)
    except Exception as ex:
        print(f"[{cont_id}] screenshot fail: {ex}")
        return ""


def _pagina_are_marci(driver) -> bool:
    try:
        return len(driver.find_elements(By.CSS_SELECTOR, "a[onclick*='BrandClick']")) > 0
    except Exception:
        return False


def _login_form_vizibil(driver) -> bool:
    try:
        elems = driver.find_elements(By.ID, "textUsername")
        return bool(elems and elems[0].is_displayed())
    except Exception:
        return False


def _mesaj_login_esuat(driver) -> str:
    """Detectează mesajul GBG «user/parolă greșite» (greacă/EN/RO)."""
    try:
        body = (driver.page_source or "")
    except Exception:
        return ""
    markers = (
        "λανθασμένα",
        "λανθασμενο",
        "incorrect",
        "invalid username",
        "invalid password",
        "wrong password",
        "parolă greșită",
        "parola gresita",
        "utilizator greșit",
    )
    low = body.lower()
    for m in markers:
        if m.lower() in low:
            return m
    return ""


def _verifica_login_dupa_workflow(driver, cont_id: str) -> bool:
    """După pașii de login din workflow: oprește dacă autentificarea a eșuat."""
    err = _mesaj_login_esuat(driver)
    if err:
        log_step(
            cont_id,
            "LOGIN EȘUAT — site-ul GBG spune că user/parola sunt greșite. "
            "Corectează datele în cartela furnizor (Monitor robot) și relansează.",
            "err",
        )
        _salveaza_captura_gbg(driver, cont_id, "login_esuat")
        return False
    if _pagina_are_marci(driver):
        return True
    if _login_form_vizibil(driver):
        cur = (driver.current_url or "")[:100]
        log_step(
            cont_id,
            f"LOGIN EȘUAT — încă pe pagina de autentificare ({cur}). "
            "Verifică user/parola din cartela furnizor.",
            "err",
        )
        _salveaza_captura_gbg(driver, cont_id, "login_esuat")
        return False
    return True


def _asteapta_marci_gbg(driver, cont_id: str, max_rounds: int = 4) -> int:
    urls = get_gbg_urls(cont_id)
    for rnd in range(max_rounds):
        try:
            WebDriverWait(driver, 18).until(
                EC.presence_of_element_located((By.CSS_SELECTOR, "a[onclick*='BrandClick']"))
            )
        except Exception:
            pass
        count = len(driver.find_elements(By.CSS_SELECTOR, "a[onclick*='BrandClick']"))
        if count > 0:
            if rnd > 0:
                log_step(cont_id, f"Catalog incarcat — {count} marci (dupa reincercare).", "ok")
            return count
        cur = (driver.current_url or "")[:120]
        titlu = (driver.title or "")[:80]
        log_step(
            cont_id,
            f"Nu vad marci in catalog (runda {rnd + 1}/{max_rounds}). URL: {cur} | Titlu: {titlu}",
            "warn",
        )
        if rnd + 1 < max_rounds:
            deschide_pagina_gbg(driver, urls["app"], cont_id)
            time.sleep(3)
    shot = _salveaza_captura_gbg(driver, cont_id, "catalog_fara_marci")
    if shot:
        log_step(cont_id, f"Captura ecran salvata: {shot}", "warn")
    return 0


def _all_cont_ids():
    return set(
        list(browsere_active.keys())
        + list(status_clienti.keys())
        + list(jurnal_clienti.keys())
        + list(scan_active.keys())
    )


def _running_map():
    return {
        cid: bool(
            scan_active.get(cid)
            or (cid in browsere_active and not este_oprit(cid))
        )
        for cid in _all_cont_ids()
    }


def _find_active_cont_id(running_map=None):
    running_map = running_map or _running_map()
    for cid, is_run in running_map.items():
        if is_run:
            return cid
    return ""


def pauza(cont_id, secunde, pasi=1.0):
    """Sleep interruptibil — verifica stop la fiecare pas."""
    ramas = float(secunde)
    while ramas > 0:
        if este_oprit(cont_id):
            return True
        time.sleep(min(pasi, ramas))
        ramas -= pasi
    return este_oprit(cont_id)


def save_robot_state():
    running_map = _running_map()
    payload = {
        "status_clienti": status_clienti,
        "jurnal_clienti": jurnal_clienti,
        "step_counters": step_counters,
        "running": running_map,
        "scan_active": {k: v for k, v in scan_active.items() if v},
        "active_cont_id": _find_active_cont_id(running_map),
        "updated_at": time.strftime("%Y-%m-%d %H:%M:%S"),
    }
    try:
        with open(STATE_FILE, "w", encoding="utf-8") as f:
            json.dump(payload, f, ensure_ascii=False, indent=2)
    except Exception as ex:
        print(f"[state] Nu pot salva: {ex}")


def load_robot_state():
    if not STATE_FILE.is_file():
        return
    try:
        with open(STATE_FILE, "r", encoding="utf-8") as f:
            data = json.load(f)
        if not isinstance(data, dict):
            return
        status_clienti.update(data.get("status_clienti") or {})
        for cid, entries in (data.get("jurnal_clienti") or {}).items():
            if isinstance(entries, list):
                jurnal_clienti[cid] = entries[-800:]
        for cid, n in (data.get("step_counters") or {}).items():
            step_counters[cid] = int(n)
        print(f"[state] Incarcat jurnal pentru {len(jurnal_clienti)} cont(uri).")
    except Exception as ex:
        print(f"[state] Nu pot incarca: {ex}")


load_robot_state()


def log_step(cont_id, mesaj, level="info"):
    """Inregistreaza un pas numerotat in jurnal + status curent."""
    ts = time.strftime("%H:%M:%S")
    step_counters[cont_id] = step_counters.get(cont_id, 0) + 1
    step = step_counters[cont_id]
    mesaj_fmt = f"Pas {step}: {mesaj}"
    status_clienti[cont_id] = f"{ts} - {mesaj_fmt}"
    if cont_id not in jurnal_clienti:
        jurnal_clienti[cont_id] = []
    jurnal_clienti[cont_id].append({"t": ts, "msg": mesaj_fmt, "level": level, "step": step})
    if len(jurnal_clienti[cont_id]) > 800:
        jurnal_clienti[cont_id] = jurnal_clienti[cont_id][-800:]
    safe_print(f"[{cont_id}] [{level}] {mesaj_fmt}")
    save_robot_state()


def update_status(cont_id, mesaj, level="info"):
    log_step(cont_id, mesaj, level)


def reset_scan_state(cont_id):
    stop_flags[cont_id] = False
    jurnal_clienti[cont_id] = []
    step_counters[cont_id] = 0


def este_oprit(cont_id):
    return bool(stop_flags.get(cont_id))


def verifica_stop(cont_id):
    if este_oprit(cont_id):
        log_step(cont_id, "Scanare oprita de utilizator.", "warn")
        return True
    return False


def opreste_robot(cont_id, silent=False):
    stop_flags[cont_id] = True
    scan_active[cont_id] = False
    driver = browsere_active.pop(cont_id, None)
    if driver:
        try:
            driver.quit()
        except Exception:
            pass
    profile_dir = os.path.join(os.getcwd(), f"profil_gbg_{cont_id}")
    try:
        opreste_chrome_profil(profile_dir, None)
    except Exception:
        pass
    ts = time.strftime("%H:%M:%S")
    if silent:
        status_clienti[cont_id] = f"{ts} - Pregatire relansare..."
        stop_flags[cont_id] = False
        save_robot_state()
        return
    step_counters[cont_id] = step_counters.get(cont_id, 0) + 1
    step = step_counters[cont_id]
    mesaj_fmt = f"Pas {step}: Robot oprit — browser inchis."
    status_clienti[cont_id] = f"{ts} - {mesaj_fmt}"
    if cont_id not in jurnal_clienti:
        jurnal_clienti[cont_id] = []
    jurnal_clienti[cont_id].append({"t": ts, "msg": mesaj_fmt, "level": "warn", "step": step})
    stop_flags[cont_id] = False
    save_robot_state()
    safe_print(f"[{cont_id}] STOP complet — gata de relansare.")


def salveaza_json(data, filename="catalog_auto.json"):
    with open(filename, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=4, ensure_ascii=False)


GBG_STRUCTURE_FILE = SCRIPT_DIR.parent / "data" / "gbg_structure.json"


def _load_gbg_structure_local():
    if GBG_STRUCTURE_FILE.is_file():
        try:
            with open(GBG_STRUCTURE_FILE, "r", encoding="utf-8") as f:
                data = json.load(f)
            if isinstance(data, dict):
                return data
        except Exception:
            pass
    return {
        "version": 1,
        "brands": [],
        "special_categories": [],
        "model_groups": {},
        "main_categories_by_model": {},
    }


def _save_gbg_structure_local(structure):
    GBG_STRUCTURE_FILE.parent.mkdir(parents=True, exist_ok=True)
    structure["version"] = int(structure.get("version") or 1)
    structure["updated_at"] = time.strftime("%Y-%m-%dT%H:%M:%S")
    with open(GBG_STRUCTURE_FILE, "w", encoding="utf-8") as f:
        json.dump(structure, f, indent=4, ensure_ascii=False)


def _gbg_structure_api_urls():
    urls = []
    for api_url in SITE_API_URLS:
        base = re.sub(r"/api/robot-oem\.php$|/robot-oem\.php$", "", api_url.rstrip("/"))
        if base:
            urls.append(f"{base}/api/gbg-structure.php")
    seen = set()
    out = []
    for u in urls:
        if u not in seen:
            seen.add(u)
            out.append(u)
    return out


def trimite_structura_gbg(structure, cont_id):
    payload = dict(structure)
    payload["cont_id"] = cont_id
    body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
    last_error = "Niciun URL structura GBG"
    for api_url in _gbg_structure_api_urls():
        api_url = _api_url_with_key(api_url)
        try:
            req = urllib.request.Request(
                api_url,
                data=body,
                headers=_robot_headers("application/json"),
                method="POST",
            )
            with urllib.request.urlopen(req, timeout=60) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except Exception as ex:
            last_error = str(ex)[:120]
    return {"ok": False, "error": last_error}


def _extrage_marci_si_speciale_gbg(driver):
    brands = []
    for link in driver.find_elements(By.CSS_SELECTOR, "#divAllform a[onclick*='BrandClick']"):
        onclick = link.get_attribute("onclick") or ""
        m = re.search(r"BrandClick\('([^']+)'", onclick)
        gbg_id = m.group(1) if m else ""
        try:
            name = link.find_element(By.TAG_NAME, "span").text.strip()
        except Exception:
            name = (link.text or "").strip()
        logo_src = ""
        try:
            logo_src = link.find_element(By.TAG_NAME, "img").get_attribute("src") or ""
        except Exception:
            pass
        if name:
            brands.append({"id": gbg_id, "name": name, "logo_src": logo_src})

    special = []
    seen_ids = set()
    for el in driver.find_elements(By.CSS_SELECTOR, "#divAllform [onclick*='FormIntClick1']"):
        onclick = el.get_attribute("onclick") or ""
        m = re.search(r"FormIntClick1\([^,]*,[^,]*,'([^']+)'\)", onclick)
        if not m:
            continue
        gbg_id = m.group(1)
        if gbg_id in seen_ids:
            continue
        seen_ids.add(gbg_id)
        label = (el.text or "").strip()
        if not label and el.tag_name.lower() == "img":
            try:
                label = el.find_element(By.XPATH, "following-sibling::span").text.strip()
            except Exception:
                label = ""
        special.append({"id": gbg_id, "label_gbg": label or gbg_id})

    return brands, special


def _extrage_grupe_modele_marca(driver, nume_marca):
    groups = []
    headers = driver.find_elements(By.XPATH, "//div[contains(@id, 'butModelHeader')]")
    for header in headers:
        group_name = re.sub(r"\s+", " ", (header.text or "").strip())
        try:
            driver.execute_script("arguments[0].click();", header)
            time.sleep(2)
        except Exception:
            pass
        models = []
        for link in driver.find_elements(By.CSS_SELECTOR, ".model-panel.show-content a.linkInBlack"):
            label = (link.text or "").strip()
            if not label:
                continue
            full_id = label if label.upper().startswith(nume_marca.upper()) else f"{nume_marca} {label}"
            years = ""
            ym = re.search(r"(\d{4})\s*-\s*(\d{4})", label)
            if ym:
                years = f"{ym.group(1)}–{ym.group(2)}"
            models.append({"id": full_id, "label": label, "years": years})
        if group_name or models:
            groups.append({"group": group_name, "models": models})
    return groups


def _extrage_categorii_form1(driver):
    categories = []
    for table in driver.find_elements(By.CSS_SELECTOR, "#divForm1 table.carFormTable"):
        group_name = ""
        group_id = ""
        try:
            header_el = table.find_element(By.CSS_SELECTOR, ".form-header-but")
            group_name = re.sub(r"\s+", " ", (header_el.text or "").strip())
            hid = table.find_element(By.CSS_SELECTOR, "[id*='butForm1Header']")
            group_id = (hid.get_attribute("id") or "").replace("butForm1Header", "")
        except Exception:
            continue
        items = []
        seen = set()
        for span in table.find_elements(By.CSS_SELECTOR, "span[onclick*='form1:']"):
            onclick = span.get_attribute("onclick") or ""
            m = re.search(r"form1:([^'\"]+)", onclick)
            if not m:
                continue
            item_id = m.group(1).strip()
            if item_id in seen:
                continue
            seen.add(item_id)
            label = (span.text or "").strip()
            if label:
                items.append({"id": item_id, "label": label})
        if group_name or items:
            categories.append({"group_id": group_id, "group": group_name, "items": items})
    return categories


def sincronizeaza_structura_gbg(driver, cont_id, structure, trimite_server=True):
    _save_gbg_structure_local(structure)
    if trimite_server:
        rsp = trimite_structura_gbg(structure, cont_id)
        if rsp.get("ok"):
            log_step(cont_id, "Structura GBG salvata pe server.", "ok")
        else:
            log_step(cont_id, f"Structura GBG locala OK — server: {str(rsp.get('error', 'esuat'))[:60]}", "warn")
    return structure


def extrage_structura_homepage_gbg(driver, cont_id, structure=None):
    structure = structure if isinstance(structure, dict) else _load_gbg_structure_local()
    brands, special = _extrage_marci_si_speciale_gbg(driver)
    if brands:
        structure["brands"] = brands
        log_step(cont_id, f"Structura GBG: {len(brands)} marci din homepage.", "info")
    if special:
        structure["special_categories"] = special
        log_step(cont_id, f"Structura GBG: {len(special)} categorii speciale.", "info")
    return sincronizeaza_structura_gbg(driver, cont_id, structure)


def extrage_si_salveaza_modele_marca(driver, cont_id, nume_marca, structure=None):
    structure = structure if isinstance(structure, dict) else _load_gbg_structure_local()
    groups = _extrage_grupe_modele_marca(driver, nume_marca)
    if groups:
        if "model_groups" not in structure or not isinstance(structure["model_groups"], dict):
            structure["model_groups"] = {}
        structure["model_groups"][nume_marca] = groups
        total_models = sum(len(g.get("models") or []) for g in groups)
        log_step(cont_id, f"Structura GBG: {nume_marca} — {len(groups)} grupe, {total_models} modele.", "info")
        return sincronizeaza_structura_gbg(driver, cont_id, structure, trimite_server=False)
    return structure


def extrage_si_salveaza_categorii_model(driver, cont_id, model_id, structure=None):
    structure = structure if isinstance(structure, dict) else _load_gbg_structure_local()
    categories = _extrage_categorii_form1(driver)
    if not categories:
        return structure
    if "main_categories_by_model" not in structure or not isinstance(structure["main_categories_by_model"], dict):
        structure["main_categories_by_model"] = {}
    structure["main_categories_by_model"][model_id] = categories
    log_step(cont_id, f"Structura GBG: {len(categories)} grupuri categorii pentru model.", "info")
    return sincronizeaza_structura_gbg(driver, cont_id, structure, trimite_server=False)


def scanare_structura_gbg(driver, cont_id):
    """Doar structura (marci, modele, categorii) — fara scanare piese."""
    log_step(cont_id, "Pornesc extragerea structurii GBG (fara piese).", "info")
    deschide_pagina_gbg(driver, get_gbg_urls(cont_id)["app"], cont_id)
    if _asteapta_marci_gbg(driver, cont_id) <= 0:
        log_step(cont_id, "Nu pot extrage structura — catalog GBG indisponibil.", "err")
        return
    structure = extrage_structura_homepage_gbg(driver, cont_id)
    marci_count = len(structure.get("brands") or [])
    for i in range(marci_count):
        if verifica_stop(cont_id):
            return
        deschide_pagina_gbg(driver, get_gbg_urls(cont_id)["app"], cont_id)
        time.sleep(5)
        marci = driver.find_elements(By.CSS_SELECTOR, "a[onclick*='BrandClick']")
        if i >= len(marci):
            break
        nume_marca = marci[i].find_element(By.TAG_NAME, "span").text.strip()
        log_step(cont_id, f"Structura: extrag modele pentru «{nume_marca}».", "info")
        driver.execute_script("arguments[0].click();", marci[i])
        time.sleep(12)
        structure = extrage_si_salveaza_modele_marca(driver, cont_id, nume_marca, structure)
    sincronizeaza_structura_gbg(driver, cont_id, structure, trimite_server=True)
    log_step(cont_id, "Extragere structura GBG finalizata.", "ok")


def _robot_headers(content_type="application/json"):
    headers = {
        "Content-Type": content_type,
        "User-Agent": "BlueCar-Robot/1.0 (compatible; TecDoc-Export)",
        "Accept": "application/json",
    }
    if ROBOT_API_KEY:
        headers["X-Robot-Key"] = ROBOT_API_KEY
    return headers


def _api_url_with_key(api_url):
    if not ROBOT_API_KEY:
        return api_url
    sep = "&" if "?" in api_url else "?"
    return f"{api_url}{sep}api_key={urllib.parse.quote(str(ROBOT_API_KEY).strip())}"


def _url_append_params(url, extra_params: dict):
    encoded = urllib.parse.urlencode({k: v for k, v in extra_params.items() if v is not None})
    if not encoded:
        return url
    sep = "&" if "?" in url else "?"
    return f"{url}{sep}{encoded}"


SITE_API_TIMEOUT = int(os.environ.get("BLU_SITE_API_TIMEOUT", "600"))


def trimite_la_site(brand, model, cod_articol, coduri_oem, cont_id, pret_eur=0, descriere_gr="", imagine_url=""):
    global SITE_API_URL_ACTIV
    payload = {
        "brand": brand,
        "model": model,
        "cod_articol": cod_articol,
        "coduri_oem": coduri_oem,
        "cont_id": cont_id,
        "pret_eur": round(float(pret_eur or 0), 2),
        "descriere_gr": (descriere_gr or "").strip(),
        "imagine_url": (imagine_url or "").strip(),
        "gbg_image": (imagine_url or "").strip(),
    }

    last_error = "Niciun URL API configurat"
    for api_url in SITE_API_URLS:
        api_url = _api_url_with_key(api_url)

        try:
            body = json.dumps(payload).encode("utf-8")
            req = urllib.request.Request(
                api_url, data=body,
                headers=_robot_headers("application/json"), method="POST",
            )
            with urllib.request.urlopen(req, timeout=SITE_API_TIMEOUT) as resp:
                SITE_API_URL_ACTIV = api_url
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            try:
                SITE_API_URL_ACTIV = api_url
                parsed = json.loads(e.read().decode("utf-8"))
                if e.code != 403:
                    return parsed
                last_error = parsed.get("error", f"HTTP {e.code}") + " — " + parsed.get("hint", "")
            except Exception:
                last_error = f"{api_url} → HTTP {e.code}"
        except Exception as ex:
            last_error = f"{api_url} → {ex}"

        try:
            form_body = urllib.parse.urlencode(payload).encode("utf-8")
            req = urllib.request.Request(
                api_url, data=form_body,
                headers=_robot_headers("application/x-www-form-urlencoded"), method="POST",
            )
            with urllib.request.urlopen(req, timeout=SITE_API_TIMEOUT) as resp:
                SITE_API_URL_ACTIV = api_url
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            try:
                parsed = json.loads(e.read().decode("utf-8"))
                last_error = parsed.get("error", f"HTTP {e.code}")
            except Exception:
                last_error = f"{api_url} → HTTP {e.code} (form)"
        except Exception as ex:
            last_error = f"{api_url} → {ex}"

        try:
            get_url = _url_append_params(api_url, payload)
            req = urllib.request.Request(get_url, headers=_robot_headers(), method="GET")
            with urllib.request.urlopen(req, timeout=SITE_API_TIMEOUT) as resp:
                SITE_API_URL_ACTIV = api_url
                return json.loads(resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            try:
                parsed = json.loads(e.read().decode("utf-8"))
                last_error = parsed.get("error", f"HTTP {e.code}")
            except Exception:
                last_error = f"{api_url} → HTTP {e.code} (get)"
        except Exception as ex:
            last_error = f"{api_url} → {ex}"

    return {"ok": False, "error": last_error, "hint": "Verifica ROBOT_API_KEY=19921705 pe server si robot"}


def test_conexiune_server():
    for base in SITE_API_URLS:
        ping_url = _url_append_params(_api_url_with_key(base), {"action": "stats"})
        try:
            req = urllib.request.Request(ping_url, method="GET", headers=_robot_headers())
            with urllib.request.urlopen(req, timeout=15) as resp:
                data = json.loads(resp.read().decode("utf-8"))
                if data.get("ok"):
                    print(f"[server] OK via {ping_url[:80]}...")
                    return True
        except urllib.error.HTTPError as e:
            try:
                body = json.loads(e.read().decode("utf-8"))
                print(f"[server] HTTP {e.code}: {body.get('error')} — {body.get('hint', '')}")
            except Exception:
                print(f"[server] HTTP {e.code} → {ping_url[:100]}")
        except Exception as ex:
            print(f"[server] {ex} → {ping_url[:100]}")
    return False


def gaseste_port_liber(preferat=0):
    """Returneaza un port TCP liber. Daca 'preferat' e dat si liber, il foloseste."""
    if preferat:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            try:
                s.bind(("127.0.0.1", preferat))
                return preferat
            except OSError:
                pass
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def curata_lockuri_profil(user_data_dir):
    for name in ("SingletonLock", "SingletonCookie", "SingletonSocket", "lockfile"):
        path = os.path.join(user_data_dir, name)
        try:
            if os.path.lexists(path):
                os.remove(path)
        except OSError:
            pass


def opreste_chrome_profil(user_data_dir, cont_id=None):
    """Inchide driver mort si procese Chrome ramase pe acelasi profil GBG."""
    if cont_id:
        driver = browsere_active.pop(cont_id, None)
        if driver:
            try:
                driver.quit()
            except Exception:
                pass

    if sys.platform == "win32" and user_data_dir:
        profile_abs = os.path.abspath(user_data_dir)
        profile_fwd = profile_abs.replace("\\", "/")
        profile_name = os.path.basename(profile_abs).replace("'", "''")
        ps = (
            "Get-CimInstance Win32_Process -Filter \"name='chrome.exe'\" | "
            "Where-Object { $_.CommandLine -and "
            f"($_.CommandLine -like '*{profile_name}*' -or $_.CommandLine -like '*{profile_fwd}*') }} | "
            "ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"
        )
        try:
            subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-Command", ps],
                capture_output=True,
                timeout=20,
            )
        except Exception:
            pass

    curata_lockuri_profil(user_data_dir)
    time.sleep(1.0)


def chrome_options_pentru_gbg(cont_id):
    profile_dir = os.path.join(os.getcwd(), f"profil_gbg_{cont_id}")
    options = uc.ChromeOptions()
    options.add_argument(f"--user-data-dir={profile_dir}")
    # Port de debugging dedicat si liber: evita conflictul cu un browser deja deschis.
    options.add_argument(f"--remote-debugging-port={gaseste_port_liber()}")
    options.add_argument("--disable-blink-features=AutomationControlled")
    options.add_argument("--start-maximized")
    options.add_argument("--window-position=0,0")
    options.add_argument("--ignore-certificate-errors")
    options.add_argument("--allow-running-insecure-content")
    options.add_argument("--disable-features=HttpsFirstBalancedMode,HttpsFirstMode,UpgradeInsecureRequests")
    options.add_argument(
        "--unsafely-treat-insecure-origin-as-secure=http://www.gbg-eshop.gr,http://gbg-eshop.gr"
    )
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--no-first-run")
    options.add_argument("--no-default-browser-check")
    return options


def detecteaza_versiune_chrome():
    """Versiunea major de Chrome instalata (ex: 150). None daca nu o gaseste."""
    import re as _re

    if sys.platform == "win32":
        try:
            import winreg
            for hive in (winreg.HKEY_CURRENT_USER, winreg.HKEY_LOCAL_MACHINE):
                try:
                    k = winreg.OpenKey(hive, r"Software\Google\Chrome\BLBeacon")
                    val, _ = winreg.QueryValueEx(k, "version")
                    winreg.CloseKey(k)
                    m = _re.match(r"(\d+)", str(val))
                    if m:
                        return int(m.group(1))
                except OSError:
                    continue
        except Exception:
            pass

    candidati = [
        os.environ.get("CHROME_BIN", ""),
        r"C:\Program Files\Google\Chrome\Application\chrome.exe",
        r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe",
        os.path.expandvars(r"%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"),
        "google-chrome",
        "chromium",
    ]
    for exe in candidati:
        if not exe:
            continue
        try:
            out = subprocess.run([exe, "--version"], capture_output=True, text=True, timeout=10)
            m = _re.search(r"(\d+)\.\d+", (out.stdout or "") + (out.stderr or ""))
            if m:
                return int(m.group(1))
        except Exception:
            continue
    return None


CHROME_VERSION_MAIN = detecteaza_versiune_chrome()


def versiuni_de_incercat():
    """Lista de version_main: intai cea instalata, apoi auto, apoi fallback."""
    v = []
    if CHROME_VERSION_MAIN:
        v.append(CHROME_VERSION_MAIN)
    v.append(None)
    for fb in (150, 149, 148, 146):
        if fb not in v:
            v.append(fb)
    return v


def porneste_chrome_gbg(cont_id):
    """Porneste Chrome GBG cu version_main potrivit versiunii instalate + retry."""
    profile_dir = os.path.join(os.getcwd(), f"profil_gbg_{cont_id}")
    last_err = None
    for attempt in range(2):
        if attempt > 0:
            opreste_chrome_profil(profile_dir, cont_id)
            time.sleep(1.5)
        for ver in versiuni_de_incercat():
            try:
                options = chrome_options_pentru_gbg(cont_id)
                if ver:
                    return uc.Chrome(options=options, version_main=ver, use_subprocess=True)
                return uc.Chrome(options=options, use_subprocess=True)
            except Exception as ex:
                last_err = ex
                msg = str(ex).lower()
                if "session not created" in msg or "chrome not reachable" in msg or "cannot connect" in msg:
                    break
    raise last_err or RuntimeError("Nu am putut porni Chrome.")


def ocolire_avertisment_http(driver, cont_id=""):
    """Chrome blocheaza HTTP cu pagina 'site fara conexiune securizata' — trecem automat."""
    time.sleep(2)
    for attempt in range(3):
        try:
            xpaths = [
                "//button[contains(., 'Перейти')]",
                "//button[contains(., 'Continue')]",
                "//button[contains(., 'Proceed')]",
                "//button[contains(., 'Advanced')]",
                "//a[contains(., 'Proceed')]",
                "//*[@id='proceed-button']",
                "//*[@id='proceed-link']",
                "//*[@id='primary-button']",
            ]
            for xp in xpaths:
                elems = driver.find_elements(By.XPATH, xp)
                for el in elems:
                    if el.is_displayed():
                        if cont_id:
                            log_step(cont_id, "Am gasit bannerul de securitate HTTP — il inchid.", "warn")
                        driver.execute_script("arguments[0].click();", el)
                        time.sleep(3)
                        if cont_id:
                            log_step(cont_id, "Banner inchis — continui navigarea.", "ok")
                        return True
            cur = (driver.current_url or "").lower()
            if "gbg-eshop" not in cur and "chromewebdata" in cur:
                if cont_id:
                    log_step(cont_id, "Reincerc accesul la site (avertisment HTTP activ).", "warn")
                driver.get(get_gbg_urls(cont_id)["login"])
                time.sleep(3)
        except Exception:
            pass
        time.sleep(2)
    return False


def inchide_popup_mesaje_gbg(driver, cont_id="", max_rounds: int = 6) -> int:
    """
    Închide popup-ul GBG «Μήνυμα» (DIVGreetingsMsg / #btnMsgClose / ShowNextPopupMsg).
    Apare după login și blochează click-urile pe mărci dacă rămâne deschis.
    """
    closed = 0
    for rnd in range(max_rounds):
        try:
            popup = None
            for sel in ("#DIV1.DIVGreetingsMsg", "div.DIVGreetingsMsg", "#DIV1"):
                elems = driver.find_elements(By.CSS_SELECTOR, sel)
                for el in elems:
                    try:
                        if el.is_displayed():
                            popup = el
                            break
                    except Exception:
                        continue
                if popup is not None:
                    break

            btn = None
            for sel in ("#btnMsgClose", "button#btnMsgClose", ".GrMsgFooter button"):
                elems = driver.find_elements(By.CSS_SELECTOR, sel)
                for el in elems:
                    try:
                        if el.is_displayed():
                            btn = el
                            break
                    except Exception:
                        continue
                if btn is not None:
                    break

            if popup is None and btn is None:
                break

            # Bifă «nu mai afișa» dacă există
            try:
                chk = driver.find_elements(By.CSS_SELECTOR, "#chkMsgRead")
                if chk and chk[0].is_displayed() and not chk[0].is_selected():
                    driver.execute_script(
                        "arguments[0].checked=true; "
                        "if (typeof SetPopupMsgRead==='function') { try{SetPopupMsgRead();}catch(e){} }",
                        chk[0],
                    )
            except Exception:
                pass

            clicked = False
            if btn is not None:
                try:
                    driver.execute_script("arguments[0].click();", btn)
                    clicked = True
                except Exception:
                    pass
            if not clicked:
                try:
                    driver.execute_script(
                        "if (typeof ShowNextPopupMsg==='function') { ShowNextPopupMsg(); }"
                        " else if (typeof ClosePopupMsg==='function') { ClosePopupMsg(); }"
                    )
                    clicked = True
                except Exception:
                    pass
            if not clicked and popup is not None:
                try:
                    driver.execute_script(
                        "arguments[0].style.display='none';"
                        "var o=document.querySelector('.ui-widget-overlay,.modal-backdrop,.blockUI');"
                        "if(o){o.style.display='none';}",
                        popup,
                    )
                    clicked = True
                except Exception:
                    pass

            if clicked:
                closed += 1
                if cont_id:
                    log_step(cont_id, f"Am închis popup-ul de mesaje GBG (#{closed}).", "ok")
                time.sleep(1.2)
            else:
                break
        except Exception:
            break
    return closed


def _parse_eur_price(text):
    """Parseaza '56,55€' / '2,6€' in float."""
    if not text:
        return 0.0
    m = re.search(r"([\d.,]+)\s*€", str(text).replace("\xa0", " "))
    if not m:
        return 0.0
    raw = m.group(1).strip()
    if "," in raw and "." in raw:
        raw = raw.replace(".", "").replace(",", ".")
    elif "," in raw:
        raw = raw.replace(",", ".")
    try:
        return max(0.0, float(raw))
    except ValueError:
        return 0.0


def _gbg_item_key_from_href(href, base_url=""):
    if not href:
        return ""
    try:
        full = urllib.parse.urljoin(base_url or "", href)
        qs = urllib.parse.parse_qs(urllib.parse.urlparse(full).query)
        item_id = (qs.get("ItemID") or qs.get("itemid") or [""])[0].strip()
        if item_id:
            return item_id.lower()
        kodan = (qs.get("kodan") or [""])[0].strip()
        return kodan.lower() if kodan else full.split("#")[0].rstrip("/").lower()
    except Exception:
        return ""


def _extrage_pret_eur_din_rand(row):
    try:
        for b in row.find_elements(By.TAG_NAME, "b"):
            txt = (b.text or "").strip()
            if "€" in txt:
                val = _parse_eur_price(txt)
                if val > 0:
                    return val
        row_text = (row.text or "").strip()
        if "€" in row_text:
            return _parse_eur_price(row_text)
    except Exception:
        pass
    return 0.0


def extrage_preturi_din_grid(driver):
    """Returneaza dict ItemID/kodan -> pret EUR din tabelul #divGrid."""
    prices = {}
    base = driver.current_url or ""
    try:
        rows = driver.find_elements(By.CSS_SELECTOR, "#divGrid tr[valign='top']")
        if not rows:
            rows = driver.find_elements(By.XPATH, "//div[@id='divGrid']//tr[@valign='top']")
        for row in rows:
            try:
                link = row.find_element(By.CSS_SELECTOR, "a.GenericStyle_Link")
                href = link.get_attribute("href") or ""
                key = _gbg_item_key_from_href(href, base)
                if not key:
                    continue
                pret = _extrage_pret_eur_din_rand(row)
                if pret > 0:
                    prices[key] = pret
            except Exception:
                continue
    except Exception:
        pass
    return prices


def _extrage_pret_eur_din_pagina(driver):
    """Fallback: cauta primul pret cu € pe pagina produsului."""
    try:
        for b in driver.find_elements(By.TAG_NAME, "b"):
            txt = (b.text or "").strip()
            if "€" in txt:
                val = _parse_eur_price(txt)
                if val > 0:
                    return val
    except Exception:
        pass
    return 0.0


def _extrage_imagine_gbg(driver) -> str:
    """
    Extrage URL-ul imaginii principale a produsului din pagina GBG.
    Folosit ca fallback când Autodoc/TecDoc nu returnează poză.
    """
    try:
        url = driver.execute_script("""
            function abs(u) {
                try { return new URL(u, location.href).href; } catch (e) { return ''; }
            }
            function bad(u) {
                if (!u) return true;
                var s = String(u).toLowerCase();
                if (s.indexOf('data:') === 0) return true;
                return /logo|icon|spacer|blank|pixel|button|banner|avatar|favicon|1x1|loading|spinner|arrow|cart|basket/.test(s);
            }
            function score(el, u) {
                if (bad(u)) return -1;
                var w = el.naturalWidth || el.width || parseInt(el.getAttribute('width') || '0', 10) || 0;
                var h = el.naturalHeight || el.height || parseInt(el.getAttribute('height') || '0', 10) || 0;
                var area = w * h;
                if (area > 0 && area < 2500) return -1; // prea mică
                var s = 0;
                var id = (el.id || '').toLowerCase();
                var cls = (el.className || '').toString().toLowerCase();
                if (/item|product|photo|imagine|main|large|zoom/.test(id + ' ' + cls)) s += 50000;
                if (/centerplaceholder/.test(id)) s += 20000;
                return s + area;
            }

            var candidates = [];
            var selectors = [
                '#ctl00_centerPlaceHolder_imgItem',
                '#ctl00_centerPlaceHolder_Image1',
                '#ctl00_centerPlaceHolder_productImage',
                "img[id*='ItemImage']",
                "img[id*='itemImage']",
                "img[id*='ProductImage']",
                "img[id*='ImgItem']",
                "img[id*='imgItem']",
                "img[src*='ItemID']",
                "img[src*='itemid']",
                "img[src*='GetImage']",
                "img[src*='showimage']",
                "img[src*='ProductImages']",
                '#ctl00_centerPlaceHolder img',
                '.product-image img',
                'a[href*=".jpg"] img',
                'a[href*=".jpeg"] img',
                'a[href*=".png"] img',
                'a[href*=".webp"] img'
            ];
            selectors.forEach(function(sel) {
                document.querySelectorAll(sel).forEach(function(el) {
                    var u = abs(el.currentSrc || el.src || el.getAttribute('data-src') || '');
                    var sc = score(el, u);
                    if (sc > 0) candidates.push({u: u, s: sc});
                    // uneori poza mare e pe link-ul părinte
                    var a = el.closest('a[href]');
                    if (a) {
                        var hu = abs(a.getAttribute('href') || '');
                        if (/\\.(jpe?g|png|webp|gif)(\\?|$)/i.test(hu) && !bad(hu)) {
                            candidates.push({u: hu, s: sc + 100000});
                        }
                    }
                });
            });

            var og = document.querySelector('meta[property="og:image"]');
            if (og && og.content) {
                var ou = abs(og.content);
                if (!bad(ou)) candidates.push({u: ou, s: 80000});
            }

            candidates.sort(function(a, b) { return b.s - a.s; });
            return candidates.length ? candidates[0].u : '';
        """)
        url = (url or "").strip()
        if url.lower().startswith("http://") or url.lower().startswith("https://"):
            return url
    except Exception:
        pass
    return ""


def _urls_egale(a: str, b: str) -> bool:
    """Compară URL-uri ignorând slash final și fragment."""
    def norm(u: str) -> str:
        u = (u or "").strip().split("#", 1)[0].rstrip("/").lower()
        return u
    return bool(a and b and norm(a) == norm(b))


def _sesiune_moarta(err: Exception) -> bool:
    msg = str(err).lower()
    return any(
        x in msg
        for x in (
            "invalid session id",
            "session deleted",
            "chrome not reachable",
            "disconnected",
            "no such window",
            "target window already closed",
            "browser has closed",
        )
    )


def safe_driver_get(driver, url, cont_id="", wait_after=0.0):
    """
    Navigare robustă: sare dacă suntem deja pe URL, retry o dată la erori tranzitorii.
    Ridică din nou excepția dacă sesiunea e moartă (caller decide restart).
    """
    url = (url or "").strip()
    if not url:
        return driver
    try:
        cur = driver.current_url or ""
        if _urls_egale(cur, url):
            if wait_after > 0:
                time.sleep(wait_after)
            return driver
    except Exception as ex:
        if _sesiune_moarta(ex):
            raise
    last_ex = None
    for attempt in range(2):
        try:
            driver.get(url)
            if wait_after > 0:
                time.sleep(wait_after)
            return driver
        except Exception as ex:
            last_ex = ex
            if _sesiune_moarta(ex):
                raise
            if cont_id:
                log_step(cont_id, f"Navigare eșuată (încercare {attempt + 1}/2): {str(ex)[:80]}", "warn")
            time.sleep(1.5)
    if last_ex:
        raise last_ex
    return driver


def deschide_pagina_gbg(driver, url, cont_id=""):
    safe_driver_get(driver, url, cont_id=cont_id, wait_after=0)
    ocolire_avertisment_http(driver, cont_id)
    inchide_popup_mesaje_gbg(driver, cont_id)
    time.sleep(2)


def naviga_si_extrage_date(driver, cont_id):
    opt = get_scan_opt(cont_id)
    scan_from = max(1, int(opt.get("scan_from", 1) or 1))
    scan_to = max(0, int(opt.get("scan_to", 0) or 0))   # 0 = fara limita
    skip_duplicate = bool(opt.get("skip_duplicate", True))

    interval_txt = (
        f"de la produsul {scan_from} pana la {scan_to}" if scan_to
        else f"de la produsul {scan_from} pana la final"
    )
    log_step(cont_id, f"Intru in catalogul GBG. Interval: {interval_txt}. "
                      f"Sar peste duplicate: {'da' if skip_duplicate else 'nu'}.", "info")
    # Dacă workflow-ul a deschis deja catalogul (marci vizibile), nu reîncărca pagina —
    # un al doilea driver.get pe HTTP GBG omoară frecvent sesiunea Chrome (invalid session id).
    marci_deja = 0
    try:
        marci_deja = len(driver.find_elements(By.CSS_SELECTOR, "a[onclick*='BrandClick']"))
    except Exception as ex:
        if _sesiune_moarta(ex):
            raise
    # Popup-ul «Μήνυμα» apare după login și acoperă catalogul — trebuie închis înainte de BrandClick.
    inchide_popup_mesaje_gbg(driver, cont_id)

    if marci_deja > 0:
        log_step(cont_id, f"Catalog deja încărcat ({marci_deja} mărci) — continui fără reload.", "ok")
        marci_count = marci_deja
    else:
        deschide_pagina_gbg(driver, get_gbg_urls(cont_id)["app"], cont_id)
        marci_count = _asteapta_marci_gbg(driver, cont_id)

    inchide_popup_mesaje_gbg(driver, cont_id)
    if marci_count <= 0:
        log_step(
            cont_id,
            "Catalog GBG fara marci — verifica login, avertisment HTTP sau deschide Chrome manual.",
            "err",
        )
        return

    gbg_structure = extrage_structura_homepage_gbg(driver, cont_id)

    date_colectate = {}

    # Contor GLOBAL peste toate piesele intalnite (pozitie stabila in catalog).
    contor = {"global": 0, "procesate": 0, "sarite": 0}

    def proceseaza_piesa(url, nume_marca, nume_submodel, preturi_grid=None):
        preturi_grid = preturi_grid or {}
        contor["global"] += 1
        pozitie = contor["global"]
        item_key = _gbg_item_key_from_href(url, driver.current_url or "")
        pret_eur = float(preturi_grid.get(item_key, 0) or 0)

        # In afara intervalului de start — sarim fara sa deschidem pagina.
        if pozitie < scan_from:
            return None
        # Am depasit capatul intervalului — oprim toata scanarea.
        if scan_to and pozitie > scan_to:
            raise _ScanDone()

        # Anti-duplicare: daca URL-ul a mai fost scanat, il sarim.
        if skip_duplicate and este_scanat(cont_id, url=url):
            contor["sarite"] += 1
            log_step(cont_id, f"Produs #{pozitie} deja scanat — sar peste.", "warn")
            return None

        log_step(cont_id, f"Scan produs #{pozitie} — deschid pagina.", "info")
        driver.get(url)
        time.sleep(10)

        # 1) Codul articol — fara el nu putem trimite nimic.
        try:
            cod_articol = driver.find_element(By.ID, "ctl00_centerPlaceHolder_labelItemID").text.strip()
        except Exception as ex:
            log_step(cont_id, f"Produs #{pozitie} fara cod articol: {str(ex)[:45]}", "warn")
            return None

        if skip_duplicate and cod_articol and este_scanat(cont_id, cod=cod_articol):
            contor["sarite"] += 1
            marcheaza_scanat(cont_id, url=url, cod=cod_articol)
            log_step(cont_id, f"Cod articol {cod_articol} deja scanat — sar peste.", "warn")
            return None

        # 2) Coduri OEM — câmpul «Γνήσιοι Κωδικοί» (ex: 60573687). Obligatoriu pentru Autodoc/TecDoc.
        coduri_oem = ""
        try:
            oem_element = driver.find_element(
                By.XPATH,
                "//span[contains(text(), 'Γνήσιοι Κωδικοί')]/parent::td/following-sibling::td",
            )
            coduri_oem = oem_element.text.strip()
        except Exception:
            coduri_oem = ""
        # Fallback: celule de tabel cu eticheta OEM
        if not coduri_oem:
            try:
                for row in driver.find_elements(By.XPATH, "//tr"):
                    cells = row.find_elements(By.TAG_NAME, "td")
                    if len(cells) < 2:
                        continue
                    label = (cells[0].text or "").strip()
                    if "Γνήσιοι" in label or "OEM" in label.upper() or "Γνησιοι" in label:
                        coduri_oem = (cells[1].text or "").strip()
                        if coduri_oem:
                            break
            except Exception:
                pass

        # 3) Descriere greacă — pentru Ollama GR→RO dacă Autodoc/TecDoc nu găsesc.
        descriere_gr = ""
        try:
            desc_el = driver.find_element(
                By.XPATH,
                "//span[contains(text(), 'Περιγραφή')]/parent::td/following-sibling::td",
            )
            descriere_gr = (desc_el.text or "").strip()
        except Exception:
            descriere_gr = ""
        if not descriere_gr:
            try:
                for row in driver.find_elements(By.XPATH, "//tr"):
                    cells = row.find_elements(By.TAG_NAME, "td")
                    if len(cells) < 2:
                        continue
                    label = (cells[0].text or "").strip()
                    if "Περιγραφή" in label or "Description" in label:
                        descriere_gr = (cells[1].text or "").strip()
                        if descriere_gr:
                            break
            except Exception:
                pass

        if not coduri_oem:
            log_step(cont_id, f"Produs #{pozitie} ({cod_articol}) fara coduri OEM — trec in «Fara OEM».", "warn")
        else:
            log_step(cont_id, f"Produs #{pozitie}: OEM «{coduri_oem[:60]}».", "ok")

        if pret_eur <= 0:
            pret_eur = _extrage_pret_eur_din_pagina(driver)

        # Imagine produs GBG — fallback când Autodoc/TecDoc nu au poză.
        imagine_url = _extrage_imagine_gbg(driver)
        if imagine_url:
            log_step(cont_id, f"Produs #{pozitie}: imagine GBG gasita.", "ok")
        else:
            log_step(cont_id, f"Produs #{pozitie}: fara imagine pe pagina GBG.", "warn")

        pret_txt = f", pret GBG {pret_eur:.2f} EUR" if pret_eur > 0 else ""
        log_step(cont_id, f"Produs #{pozitie}: cod articol {cod_articol}{pret_txt} — import BD + PieseAuto (astept finalizare)...", "info")
        raspuns = trimite_la_site(
            nume_marca, nume_submodel, cod_articol, coduri_oem, cont_id,
            pret_eur=pret_eur, descriere_gr=descriere_gr, imagine_url=imagine_url,
        )
        status = str(raspuns.get("status") or "").lower()

        if raspuns.get("ok"):
            titlu = (raspuns.get("card") or {}).get("title", "OK")
            log_step(cont_id, f"Importat in baza de date: {titlu[:55]}", "ok")
            pa = raspuns.get("pieseauto")
            if isinstance(pa, dict):
                pa_msg = str(pa.get("message") or pa.get("status") or "").strip()
                if pa_msg:
                    pa_lvl = "ok" if pa.get("ok") else "warn"
                    if pa.get("ok"):
                        pa_msg += " — trec la urmatorul produs"
                    else:
                        pa_msg += " — continuam scanarea GBG"
                    log_step(cont_id, f"PieseAuto: {pa_msg[:80]}", pa_lvl)
            contor["procesate"] += 1
            marcheaza_scanat(cont_id, url=url, cod=cod_articol)
            return {"cod_articol": cod_articol, "coduri_oem": coduri_oem}

        if status in ("no_oem", "empty"):
            # Rezultat normal pentru piesa curenta — continuam scanarea.
            msg = raspuns.get("error") or (raspuns.get("event") or {}).get("message", status)
            log_step(cont_id, f"Fara rezultat ({status}): {str(msg)[:55]}", "warn")
            marcheaza_scanat(cont_id, url=url, cod=cod_articol)
            return None

        msg = raspuns.get("error") or (raspuns.get("event") or {}).get("message", "esuat")
        hint = raspuns.get("hint", "")
        err_full = f"{str(msg)[:70]} {str(hint)[:60]}".strip()
        fatal = any(x in err_full.lower() for x in (
            "api key", "403", "niciun url api", "invalida", "forbidden",
        ))
        if fatal:
            marcheaza_scanat(cont_id, url=url, cod=cod_articol)
            raise _TecDocStop(err_full)

        log_step(cont_id, f"Produs #{pozitie} esuat la server ({str(msg)[:55]}) — continui scanarea.", "warn")
        marcheaza_scanat(cont_id, url=url, cod=cod_articol)
        return None

    log_step(cont_id, f"Listez marci — gasite {marci_count}.", "info")

    try:
        for i in range(marci_count):
            if verifica_stop(cont_id):
                return
            deschide_pagina_gbg(driver, get_gbg_urls(cont_id)["app"], cont_id)
            inchide_popup_mesaje_gbg(driver, cont_id)
            marci = driver.find_elements(By.CSS_SELECTOR, "a[onclick*='BrandClick']")
            if i >= len(marci):
                break
            nume_marca = marci[i].find_element(By.TAG_NAME, "span").text
            log_step(cont_id, f"Selectez marca «{nume_marca}».", "info")
            driver.execute_script("arguments[0].click();", marci[i])
            time.sleep(15)
            inchide_popup_mesaje_gbg(driver, cont_id)

            gbg_structure = extrage_si_salveaza_modele_marca(driver, cont_id, nume_marca, gbg_structure)

            date_colectate[nume_marca] = {}
            headers_count = len(driver.find_elements(By.XPATH, "//div[contains(@id, 'butModelHeader')]"))
            log_step(cont_id, f"Deschid sectiunea modele — {headers_count} disponibile.", "info")

            for j in range(headers_count):
                if verifica_stop(cont_id):
                    return
                headers = driver.find_elements(By.XPATH, "//div[contains(@id, 'butModelHeader')]")
                if j >= len(headers):
                    break
                driver.execute_script("arguments[0].click();", headers[j])
                time.sleep(10)

                link_uri = driver.find_elements(By.CSS_SELECTOR, ".model-panel.show-content a.linkInBlack")
                log_step(cont_id, f"Model [{j+1}]: {len(link_uri)} submodele de scanat.", "info")

                for k in range(len(link_uri)):
                    if verifica_stop(cont_id):
                        return
                    link_uri = driver.find_elements(By.CSS_SELECTOR, ".model-panel.show-content a.linkInBlack")
                    if k >= len(link_uri):
                        break
                    nume_submodel = link_uri[k].text.strip()
                    log_step(cont_id, f"Selectez submodelul «{nume_submodel}».", "info")
                    driver.execute_script("arguments[0].click();", link_uri[k])
                    time.sleep(15)

                    model_key = nume_marca + " " + nume_submodel if not nume_submodel.upper().startswith(nume_marca.upper()) else nume_submodel
                    gbg_structure = extrage_si_salveaza_categorii_model(driver, cont_id, model_key, gbg_structure)

                    piese = driver.find_elements(By.CLASS_NAME, "GenericStyle_Link")
                    urluri_piese = [p.get_attribute("href") for p in piese]
                    preturi_grid = extrage_preturi_din_grid(driver)
                    if preturi_grid:
                        log_step(cont_id, f"Preturi GBG in grid: {len(preturi_grid)} produse cu €.", "info")
                    log_step(cont_id, f"Listez piese — {len(urluri_piese)} in aceasta sectiune "
                                      f"(produse procesate pana acum: {contor['procesate']}).", "info")

                    lista_piese_detaliate = []
                    for url in urluri_piese:
                        if verifica_stop(cont_id):
                            return
                        rezultat = proceseaza_piesa(url, nume_marca, nume_submodel, preturi_grid)
                        if rezultat:
                            lista_piese_detaliate.append(rezultat)
                        driver.back()
                        time.sleep(10)

                    date_colectate[nume_marca][nume_submodel] = lista_piese_detaliate
                    salveaza_json(date_colectate)
                    log_step(cont_id, f"Salvat catalog partial pentru «{nume_submodel}».", "ok")

                    if verifica_stop(cont_id):
                        return
                    driver.get(get_gbg_urls(cont_id)["app"])
                    time.sleep(10)
                    ocolire_avertisment_http(driver, cont_id)
                    marci = driver.find_elements(By.CSS_SELECTOR, "a[onclick*='BrandClick']")
                    driver.execute_script("arguments[0].click();", marci[i])
                    time.sleep(15)
                    headers = driver.find_elements(By.XPATH, "//div[contains(@id, 'butModelHeader')]")
                    driver.execute_script("arguments[0].click();", headers[j])
                    time.sleep(10)
    except _TecDocStop as ex:
        stop_flags[cont_id] = True
        scan_active[cont_id] = False
        log_step(cont_id, f"SCANARE OPRITA — TecDoc/server a raspuns fals: {str(ex)[:90]}. "
                          f"Verifica RapidAPI / ROBOT_API_KEY in «Diagnostic API», apoi reia scanarea. "
                          f"Procesate pana acum: {contor['procesate']}.", "err")
        return
    except _ScanDone:
        log_step(cont_id, f"Am atins capatul intervalului (produsul {scan_to}). "
                          f"Procesate: {contor['procesate']}, sarite (duplicate): {contor['sarite']}.", "ok")
        return

    log_step(cont_id, f"Scanare finalizata. Produse intalnite: {contor['global']}, "
                      f"procesate: {contor['procesate']}, sarite (duplicate): {contor['sarite']}.", "ok")
    sincronizeaza_structura_gbg(driver, cont_id, gbg_structure, trimite_server=True)


def _workflow_for_cont(cont_id, workflow_inline=None):
    if workflow_inline and isinstance(workflow_inline, dict) and workflow_inline.get("steps"):
        return workflow_inline
    if HAS_WORKFLOW_ENGINE:
        wf = load_workflow_file(cont_id)
        if wf and wf.get("steps"):
            return wf
    return None


def _lanseaza_login_legacy(driver, cont_id, email, password):
    urls = get_gbg_urls(cont_id)
    deschide_pagina_gbg(driver, urls["login"], cont_id)
    log_step(cont_id, "Pagina de login GBG deschisa.", "info")

    if _pagina_are_marci(driver):
        log_step(cont_id, "Sesiune activa — catalog deja vizibil.", "ok")
        return True

    if not _login_form_vizibil(driver):
        cur = (driver.current_url or "")[:100]
        if "authenticate" not in cur.lower():
            log_step(cont_id, f"Fara formular login — URL curent: {cur}", "warn")
            return True
        log_step(cont_id, "Nu gasesc formular login — verifica avertisment HTTP.", "err")
        _salveaza_captura_gbg(driver, cont_id, "login_form_lipsa")
        return False

    log_step(cont_id, "Ne logam — completez user si parola.", "info")
    driver.find_element(By.ID, "textUsername").clear()
    driver.find_element(By.ID, "textUsername").send_keys(email)
    driver.find_element(By.ID, "textPassword").clear()
    driver.find_element(By.ID, "textPassword").send_keys(password)
    driver.find_element(By.ID, "ctl00_centerPlaceHolder_ButtonLogin").click()
    log_step(cont_id, "Login trimis — astept raspunsul site-ului.", "info")

    login_ok = False
    for _ in range(24):
        if verifica_stop(cont_id):
            return False
        ocolire_avertisment_http(driver, cont_id)
        if _pagina_are_marci(driver):
            login_ok = True
            break
        cur = (driver.current_url or "").lower()
        if "authenticate" not in cur and not _login_form_vizibil(driver):
            login_ok = True
            break
        time.sleep(1)

    if not login_ok and _login_form_vizibil(driver):
        log_step(cont_id, "Login esuat — formularul de autentificare inca e vizibil.", "err")
        _salveaza_captura_gbg(driver, cont_id, "login_esuat")
        return False

    log_step(cont_id, "Ne-am logat cu succes — merg la catalog.", "ok")
    return True


def lanseaza_instanta(cont_id, email, password, workflow_inline=None):
    reset_scan_state(cont_id)
    scan_active[cont_id] = True
    save_robot_state()
    driver = None
    try:
        log_step(cont_id, "Comanda primita — pregatesc scanarea.", "info")

        if verifica_stop(cont_id):
            return

        workflow = _workflow_for_cont(cont_id, workflow_inline)
        if workflow:
            log_step(cont_id, "Folosesc pașii configurați pentru acest furnizor.", "info")
        else:
            log_step(cont_id, "Fără workflow salvat — folosesc pașii impliciți GBG.", "warn")

        log_step(cont_id, "Pornesc Chrome (browser automat).", "info")
        profile_dir = os.path.join(os.getcwd(), f"profil_gbg_{cont_id}")
        try:
            log_step(cont_id, "Pregatesc profil Chrome (inchid procese vechi)...", "info")
            opreste_chrome_profil(profile_dir, cont_id)
            driver = porneste_chrome_gbg(cont_id)
        except Exception as ex:
            log_step(cont_id, f"Eroare pornire Chrome: {str(ex)[:120]}", "err")
            return
        try:
            driver.maximize_window()
        except Exception:
            pass
        browsere_active[cont_id] = driver
        log_step(cont_id, "Chrome pornit — verifica fereastra pe ecran (Alt+Tab daca nu o vezi).", "info")

        if verifica_stop(cont_id):
            opreste_robot(cont_id)
            return

        pending_builtin = None
        if workflow and HAS_WORKFLOW_ENGINE:
            ctx = build_context(cont_id, email, password, workflow)
            urls = get_gbg_urls(cont_id)
            if not ctx.get("login_url"):
                ctx["login_url"] = urls["login"]
            if not ctx.get("catalog_url"):
                ctx["catalog_url"] = urls["app"]
            pending_builtin = run_workflow_steps(
                driver, cont_id, workflow, ctx, log_step, verifica_stop, pauza,
            )
            if verifica_stop(cont_id):
                opreste_robot(cont_id)
                return
            if pending_builtin in ("gbg_catalog_scan", "gbg_structure_scan"):
                if not _verifica_login_dupa_workflow(driver, cont_id):
                    opreste_robot(cont_id)
                    return
                inchide_popup_mesaje_gbg(driver, cont_id)
            if pending_builtin not in ("gbg_catalog_scan", "gbg_structure_scan"):
                if pending_builtin:
                    log_step(cont_id, f"Acțiune builtin necunoscută: {pending_builtin}", "err")
                else:
                    log_step(cont_id, "Workflow terminat (fără scanare catalog).", "ok")
                browsere_active.pop(cont_id, None)
                try:
                    driver.quit()
                except Exception:
                    pass
                return
        else:
            if not _lanseaza_login_legacy(driver, cont_id, email, password):
                opreste_robot(cont_id)
                return
            pending_builtin = "gbg_catalog_scan"

        if verifica_stop(cont_id):
            opreste_robot(cont_id)
            return

        if pending_builtin == "gbg_structure_scan":
            scanare_structura_gbg(driver, cont_id)
        elif pending_builtin == "gbg_catalog_scan":
            naviga_si_extrage_date(driver, cont_id)
    except Exception as ex:
        log_step(cont_id, f"Eroare scanare: {str(ex)[:120]}", "err")
    finally:
        scan_active[cont_id] = False
        browsere_active.pop(cont_id, None)
        if driver:
            try:
                driver.quit()
            except Exception:
                pass
        if not este_oprit(cont_id):
            log_step(cont_id, "Browser inchis — sesiune terminata.", "info")
        save_robot_state()


@app.route('/verificare_sesiune', methods=['GET'])
def verificare_sesiune():
    return jsonify({"status": "ok", "mesaj": "Robot GBG activ"})


@app.route('/get_status', methods=['GET'])
def get_status():
    cont_id = (request.args.get('cont_id') or '').strip()
    if cont_id and cont_id in status_clienti:
        return jsonify({
            "status": status_clienti[cont_id],
            "running": cont_id in browsere_active and not este_oprit(cont_id),
            "jurnal": jurnal_clienti.get(cont_id, []),
            "deja_scanate": numar_scanate(cont_id),
        })
    return jsonify({"status": "Inactiv", "running": False, "jurnal": [], "deja_scanate": numar_scanate(cont_id) if cont_id else 0})


@app.route('/stop', methods=['POST', 'GET'])
@app.route('/stop_total', methods=['POST', 'GET'])
def stop_robot_route():
    data = request.json or {}
    cont_id = (data.get('cont_id') or request.args.get('cont_id') or '').strip()
    if not cont_id:
        return jsonify({"status": "error", "mesaj": "Lipseste cont_id."}), 400
    opreste_robot(cont_id)
    return jsonify({"status": "succes", "mesaj": "Robot oprit. Poti relansa."})


def _to_int(val, default=0):
    try:
        return int(str(val).strip())
    except Exception:
        return default


@app.route('/comanda', methods=['POST'])
def comanda():
    data = request.json or {}
    cont_id = (data.get('cont_id') or 'robot1').strip()
    site_url = (data.get('site_url') or data.get('site') or '').strip()
    urls = set_gbg_urls_for_cont(cont_id, site_url)

    scan_optiuni[cont_id] = {
        "scan_from": max(1, _to_int(data.get('scan_from'), 1)),
        "scan_to": max(0, _to_int(data.get('scan_to'), 0)),
        "skip_duplicate": bool(data.get('skip_duplicate', True)),
        "site_url": site_url or urls["base"],
    }

    with _launch_lock:
        alive = _launch_threads.get(cont_id)
        if alive is not None and alive.is_alive() and scan_active.get(cont_id):
            return jsonify({
                "status": "activ",
                "mesaj": "Scanare deja activa — browser existent.",
                "scan_optiuni": scan_optiuni[cont_id],
                "deja_scanate": numar_scanate(cont_id),
                "site_api": SITE_API_URL_ACTIV,
                "site_api_urls": SITE_API_URLS,
            })

        # Oprește orice sesiune/thread vechi înainte de relansare (evită 2 Chrome pe același profil).
        if (alive is not None and alive.is_alive()) or _running_map().get(cont_id) or scan_active.get(cont_id):
            opreste_robot(cont_id, silent=True)
            deadline = time.time() + 8
            while time.time() < deadline:
                t = _launch_threads.get(cont_id)
                if t is None or not t.is_alive():
                    break
                time.sleep(0.3)
            time.sleep(0.8)

        workflow_inline = data.get("workflow")
        if workflow_inline and HAS_WORKFLOW_ENGINE:
            try:
                save_workflow_file(cont_id, workflow_inline)
            except Exception as ex:
                print(f"[workflow] Nu pot salva local: {ex}")

        th = threading.Thread(
            target=lanseaza_instanta,
            args=(cont_id, data.get('user', ''), data.get('pass', ''), workflow_inline),
            daemon=True,
            name=f"gbg-scan-{cont_id}",
        )
        _launch_threads[cont_id] = th
        th.start()

    return jsonify({
        "status": "se lanseaza",
        "scan_optiuni": scan_optiuni[cont_id],
        "deja_scanate": numar_scanate(cont_id),
        "site_api": SITE_API_URL_ACTIV,
        "site_api_urls": SITE_API_URLS,
    })


@app.route('/reset_scanate', methods=['POST', 'GET'])
def reset_scanate_route():
    data = request.get_json(silent=True) or {}
    cont_id = (data.get('cont_id') or request.args.get('cont_id') or '').strip()
    if not cont_id:
        return jsonify({"status": "error", "mesaj": "Lipseste cont_id."}), 400
    reset_scanate(cont_id)
    return jsonify({
        "status": "succes",
        "mesaj": "Lista produselor deja scanate a fost golita. Urmatoarea scanare le va include din nou.",
        "deja_scanate": 0,
    })


@app.route('/scanate_info', methods=['GET'])
def scanate_info_route():
    cont_id = (request.args.get('cont_id') or '').strip()
    if not cont_id:
        return jsonify({"status": "error", "mesaj": "Lipseste cont_id."}), 400
    return jsonify({"status": "ok", "deja_scanate": numar_scanate(cont_id)})


@app.route('/este_ocupat', methods=['GET'])
def este_ocupat_route():
    cont_id = (request.args.get('cont_id') or '').strip()
    busy = bool(cont_id and _running_map().get(cont_id))
    return jsonify({"busy": busy})


@app.route('/status', methods=['GET'])
def status():
    cont_id = (request.args.get('cont_id') or '').strip()
    running_map = _running_map()
    active_cont_id = _find_active_cont_id(running_map)
    payload = {
        "status_clienti": status_clienti,
        "jurnal_clienti": jurnal_clienti,
        "running": running_map,
        "active_cont_id": active_cont_id,
        "active_running": bool(active_cont_id and running_map.get(active_cont_id)),
        "site_api": SITE_API_URL_ACTIV,
        "site_api_urls": SITE_API_URLS,
        "monitor_url": MONITOR_URL,
    }
    if cont_id:
        payload["jurnal"] = jurnal_clienti.get(cont_id, [])
        payload["is_running"] = running_map.get(cont_id, False)
        payload["deja_scanate"] = numar_scanate(cont_id)
    return jsonify(payload)


if __name__ == '__main__':
    key_src = "env" if os.environ.get("ROBOT_API_KEY", "").strip() else (
        "robot_config.json" if CONFIG_FILE.is_file() else "implicit"
    )
    print("=" * 50)
    print("Robot Blue-Car — EXPORT POST pe SERVER")
    print("=" * 50)
    for u in SITE_API_URLS:
        print(f"  POST -> {u}")
    print(f"Monitor: {MONITOR_URL}")
    print(f"API Key: {ROBOT_API_KEY} (sursa: {key_src})")
    print("Conexiune server:", "OK" if test_conexiune_server() else "ESUAT")

    port_dorit = int(os.environ.get("ROBOT_FURNIZORI_PORT", os.environ.get("ROBOT_GBG_PORT", "5000")))
    port = gaseste_port_liber(port_dorit)
    if port != port_dorit:
        print(f"ATENTIE: portul {port_dorit} este ocupat — pornesc pe portul liber {port}.")
        print(f"         Actualizeaza ROBOT_FURNIZORI_URL=http://127.0.0.1:{port} in .env.")
    print(f"Port robot (scanare GBG): {port}")
    print("=" * 50)
    app.run(host='0.0.0.0', port=port, threaded=True)
