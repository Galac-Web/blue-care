"""
Motor pași configurabili per furnizor (navigare, click, fill, banere, builtin).
Folosit de robot1.py — workflow JSON din PHP sau fișier local workflows/{cont_id}.json
"""
from __future__ import annotations

import json
import time
from pathlib import Path
from typing import Any, Callable, Optional

from selenium.webdriver.common.by import By

SCRIPT_DIR = Path(__file__).resolve().parent
WORKFLOWS_DIR = SCRIPT_DIR / "workflows"
PROJECT_ROOT = SCRIPT_DIR.parent


def snapshots_dirs(cont_id: str) -> list:
    """Directoare unde salvăm HTML + meta (proiect + folder robot)."""
    safe = _safe_cont_id(cont_id)
    dirs = [
        PROJECT_ROOT / "data" / "robot_snapshots" / safe,
        SCRIPT_DIR / "snapshots" / safe,
    ]
    cfg_file = SCRIPT_DIR / "robot_config.json"
    if cfg_file.is_file():
        try:
            with open(cfg_file, "r", encoding="utf-8") as fh:
                cfg = json.load(fh)
            extra = (cfg.get("snapshots_dir") or "").strip()
            if extra:
                dirs.insert(0, Path(extra) / safe)
        except Exception:
            pass
    out = []
    for d in dirs:
        if d not in out:
            out.append(d)
    return out


def _find_html_page(workflow: dict, page_key: str) -> Optional[dict]:
    key = (page_key or "").strip()
    if not key:
        return None
    for p in workflow.get("html_pages") or []:
        if isinstance(p, dict) and (p.get("key") or "").strip() == key:
            return p
    return None


def analyze_and_snapshot(
    driver,
    cont_id: str,
    step: dict,
    workflow: dict,
    ctx: dict,
    log_fn: Callable,
    order: Any,
) -> dict:
    """Deschide URL (opțional), verifică selectori, salvează HTML + meta, log detaliat."""
    page_key = (step.get("page_key") or "").strip()
    page_def = _find_html_page(workflow, page_key) if page_key else None

    url = substitute(step.get("url") or (page_def or {}).get("url") or "", ctx)
    if url and url != "(dinamic — fiecare link .GenericStyle_Link)":
        already = False
        try:
            cur = (driver.current_url or "").strip().rstrip("/").lower().split("#", 1)[0]
            want = url.strip().rstrip("/").lower().split("#", 1)[0]
            already = bool(cur and want and cur == want)
        except Exception:
            already = False
        if already:
            log_fn(cont_id, f"Pas {order}: Deja pe pagina țintă — sar reload HTML.", "info")
        else:
            log_fn(cont_id, f"Pas {order}: Deschid HTML pentru analiză → {url[:90]}", "info")
            driver.get(url)
            time.sleep(float(step.get("wait_after") or 4))

    purpose = step.get("html_purpose") or (page_def or {}).get("name") or step.get("label") or "Pagină"
    what_scans = step.get("what_scans") or (page_def or {}).get("what_scans") or ""
    verify = step.get("verify_selectors") or (page_def or {}).get("verify_selectors") or []
    save_html = step.get("save_html", True)
    if page_def and page_def.get("save_html"):
        save_html = True

    cur_url = driver.current_url or ""
    title = ""
    try:
        title = (driver.title or "").strip()
    except Exception:
        pass

    html = ""
    try:
        html = driver.page_source or ""
    except Exception:
        pass

    checks = []
    for sel in verify:
        sel = (sel or "").strip()
        if not sel:
            continue
        try:
            by = By.XPATH if sel.startswith("//") or sel.startswith("(") else By.CSS_SELECTOR
            n = len(driver.find_elements(by, sel))
            ok = n > 0
            checks.append({"selector": sel, "found": n, "ok": ok})
            lvl = "ok" if ok else "warn"
            log_fn(cont_id, f"Pas {order}: Verificare «{sel[:50]}» → {n} element(e)", lvl)
        except Exception as ex:
            checks.append({"selector": sel, "found": 0, "ok": False, "error": str(ex)[:80]})
            log_fn(cont_id, f"Pas {order}: Selector invalid «{sel[:40]}»: {str(ex)[:50]}", "warn")

    link_count = 0
    try:
        link_count = len(driver.find_elements(By.TAG_NAME, "a"))
    except Exception:
        pass

    report = {
        "cont_id": cont_id,
        "step_order": order,
        "page_key": page_key,
        "label": step.get("label") or "",
        "html_purpose": purpose,
        "what_scans": what_scans,
        "url": cur_url,
        "title": title,
        "html_length": len(html),
        "links_count": link_count,
        "verify_checks": checks,
        "saved_at": time.strftime("%Y-%m-%d %H:%M:%S"),
    }

    log_fn(
        cont_id,
        f"Pas {order}: Analiză HTML «{purpose}» — titlu: {title[:60] or '-'} | "
        f"{len(html)} caractere | {link_count} linkuri",
        "info",
    )
    if what_scans:
        log_fn(cont_id, f"Pas {order}: Ce scanează: {what_scans[:200]}", "info")

    if save_html and html:
        stamp = time.strftime("%Y%m%d_%H%M%S")
        base = f"{stamp}_pas{order}" + (f"_{page_key}" if page_key else "")
        for snap_dir in snapshots_dirs(cont_id):
            try:
                snap_dir.mkdir(parents=True, exist_ok=True)
                html_path = snap_dir / f"{base}.html"
                meta_path = snap_dir / f"{base}.meta.json"
                with open(html_path, "w", encoding="utf-8") as fh:
                    fh.write(html)
                with open(meta_path, "w", encoding="utf-8") as fh:
                    json.dump(report, fh, ensure_ascii=False, indent=2)
                log_fn(
                    cont_id,
                    f"Pas {order}: Snapshot HTML salvat ({html_path.name}, {len(html)} bytes)",
                    "ok",
                )
                report["snapshot_id"] = base
                report["snapshot_path"] = str(html_path)
                break
            except Exception as ex:
                log_fn(cont_id, f"Pas {order}: Nu pot salva HTML: {str(ex)[:60]}", "warn")

    return report


def _safe_cont_id(cont_id: str) -> str:
    s = "".join(c for c in str(cont_id) if c.isalnum() or c in ("_", "-"))
    return s or "default"


def load_workflow_file(cont_id: str) -> Optional[dict]:
    f = WORKFLOWS_DIR / f"{_safe_cont_id(cont_id)}.json"
    if not f.is_file():
        return None
    try:
        with open(f, "r", encoding="utf-8") as fh:
            data = json.load(fh)
        return data if isinstance(data, dict) else None
    except Exception:
        return None


def save_workflow_file(cont_id: str, workflow: dict) -> None:
    WORKFLOWS_DIR.mkdir(parents=True, exist_ok=True)
    f = WORKFLOWS_DIR / f"{_safe_cont_id(cont_id)}.json"
    with open(f, "w", encoding="utf-8") as fh:
        json.dump(workflow, fh, ensure_ascii=False, indent=2)


def substitute(text: str, ctx: dict) -> str:
    if not text:
        return text
    out = str(text)
    for key, val in ctx.items():
        out = out.replace("{{" + key + "}}", str(val or ""))
    return out


def _by_selector(driver, selector: str, selector_type: str):
    st = (selector_type or "css").lower()
    if st == "xpath":
        return driver.find_elements(By.XPATH, selector)
    if st == "id":
        return driver.find_elements(By.ID, selector.lstrip("#"))
    return driver.find_elements(By.CSS_SELECTOR, selector)


def close_banner_selectors(driver, selectors: list, cont_id: str, log_fn: Callable, banner_name: str = "") -> int:
    closed = 0
    for xp in selectors:
        xp = (xp or "").strip()
        if not xp:
            continue
        try:
            by = By.XPATH if xp.startswith("//") or xp.startswith("(") else By.CSS_SELECTOR
            elems = driver.find_elements(by, xp)
            for el in elems:
                if el.is_displayed():
                    # Bifă «nu mai afișa» pe popup-ul GBG, dacă e vizibil
                    try:
                        chk = driver.find_elements(By.CSS_SELECTOR, "#chkMsgRead")
                        if chk and chk[0].is_displayed() and not chk[0].is_selected():
                            driver.execute_script(
                                "arguments[0].checked=true;"
                                "if(typeof SetPopupMsgRead==='function'){try{SetPopupMsgRead();}catch(e){}}",
                                chk[0],
                            )
                    except Exception:
                        pass
                    driver.execute_script("arguments[0].click();", el)
                    closed += 1
                    label = banner_name or xp[:60]
                    log_fn(cont_id, f"Banner închis: {label}", "ok")
                    time.sleep(1.5)
                    break
        except Exception:
            pass
    return closed


def close_gbg_greeting_popup(driver, cont_id: str, log_fn: Callable, max_rounds: int = 6) -> int:
    """Închide popup-ul GBG DIVGreetingsMsg (#btnMsgClose / ShowNextPopupMsg)."""
    closed = 0
    for _ in range(max_rounds):
        try:
            visible = False
            for sel in ("#DIV1.DIVGreetingsMsg", "div.DIVGreetingsMsg", "#btnMsgClose"):
                for el in driver.find_elements(By.CSS_SELECTOR, sel):
                    try:
                        if el.is_displayed():
                            visible = True
                            break
                    except Exception:
                        continue
                if visible:
                    break
            if not visible:
                break

            try:
                chk = driver.find_elements(By.CSS_SELECTOR, "#chkMsgRead")
                if chk and chk[0].is_displayed() and not chk[0].is_selected():
                    driver.execute_script(
                        "arguments[0].checked=true;"
                        "if(typeof SetPopupMsgRead==='function'){try{SetPopupMsgRead();}catch(e){}}",
                        chk[0],
                    )
            except Exception:
                pass

            clicked = False
            for sel in ("#btnMsgClose", "button#btnMsgClose", ".GrMsgFooter button"):
                for el in driver.find_elements(By.CSS_SELECTOR, sel):
                    try:
                        if el.is_displayed():
                            driver.execute_script("arguments[0].click();", el)
                            clicked = True
                            break
                    except Exception:
                        continue
                if clicked:
                    break
            if not clicked:
                try:
                    driver.execute_script(
                        "if(typeof ShowNextPopupMsg==='function'){ShowNextPopupMsg();}"
                        "else if(typeof ClosePopupMsg==='function'){ClosePopupMsg();}"
                    )
                    clicked = True
                except Exception:
                    pass
            if not clicked:
                try:
                    driver.execute_script(
                        "var p=document.querySelector('#DIV1.DIVGreetingsMsg,div.DIVGreetingsMsg,#DIV1');"
                        "if(p){p.style.display='none';}"
                        "var o=document.querySelector('.ui-widget-overlay,.blockUI');"
                        "if(o){o.style.display='none';}"
                    )
                    clicked = True
                except Exception:
                    pass
            if clicked:
                closed += 1
                log_fn(cont_id, f"Popup mesaje GBG închis (#{closed}).", "ok")
                time.sleep(1.2)
            else:
                break
        except Exception:
            break
    return closed


def close_all_banners(driver, workflow: dict, cont_id: str, log_fn: Callable, extra_selectors: Optional[list] = None) -> int:
    total = 0
    for b in workflow.get("banners") or []:
        if not isinstance(b, dict):
            continue
        name = b.get("name") or "Banner"
        sels = b.get("selectors") or []
        total += close_banner_selectors(driver, sels, cont_id, log_fn, str(name))
    if extra_selectors:
        total += close_banner_selectors(driver, extra_selectors, cont_id, log_fn, "Pas custom")
    # Mereu încearcă popup-ul de mesaje GBG (apare după login, nu e în lista Chrome HTTP).
    total += close_gbg_greeting_popup(driver, cont_id, log_fn)
    return total


def run_workflow_steps(
    driver,
    cont_id: str,
    workflow: dict,
    ctx: dict,
    log_fn: Callable,
    stop_check: Callable,
    pause_fn: Optional[Callable] = None,
    builtin_handlers: Optional[dict] = None,
) -> Optional[str]:
    """
    Execută pașii în ordine. Returnează acțiunea builtin de rulat la final (ex. gbg_catalog_scan)
    sau None dacă s-a terminat fără builtin.
    """
    builtin_handlers = builtin_handlers or {}
    steps = sorted(workflow.get("steps") or [], key=lambda s: int(s.get("order") or 0))
    pending_builtin = None

    for step in steps:
        if stop_check(cont_id):
            return None

        stype = (step.get("type") or "").strip().lower()
        label = substitute(step.get("label") or f"Pas {step.get('order')}", ctx)
        order = step.get("order", "?")
        log_fn(cont_id, f"Pas {order}: {label}", "info")

        if stype == "navigate":
            url = substitute(step.get("url") or "", ctx)
            if not url:
                log_fn(cont_id, f"Pas {order}: URL lipsă — sar.", "warn")
                continue
            purpose = step.get("html_purpose") or ""
            if purpose:
                log_fn(cont_id, f"Pas {order}: Scop pagină HTML — {purpose[:120]}", "info")
            already = False
            try:
                cur = (driver.current_url or "").strip().rstrip("/").lower().split("#", 1)[0]
                want = url.strip().rstrip("/").lower().split("#", 1)[0]
                already = bool(cur and want and cur == want)
            except Exception:
                already = False
            if already:
                log_fn(cont_id, f"Pas {order}: Deja pe {url[:60]} — fără reload.", "info")
            else:
                log_fn(cont_id, f"Pas {order}: Navighez → {url[:80]}", "info")
                driver.get(url)
                time.sleep(float(step.get("wait_after") or 3))
            if step.get("close_banners_after"):
                close_all_banners(driver, workflow, cont_id, log_fn, step.get("selectors"))
            if step.get("save_html") or step.get("verify_selectors") or step.get("what_scans"):
                analyze_and_snapshot(driver, cont_id, step, workflow, ctx, log_fn, order)

        elif stype in ("analyze_html", "analyze_page", "snapshot_html"):
            analyze_and_snapshot(driver, cont_id, step, workflow, ctx, log_fn, order)

        elif stype in ("close_banners", "close_banner"):
            if step.get("use_global_banners"):
                n = close_all_banners(driver, workflow, cont_id, log_fn, None)
            else:
                n = close_banner_selectors(driver, step.get("selectors") or [], cont_id, log_fn, label)
            if n == 0:
                log_fn(cont_id, f"Pas {order}: Niciun banner vizibil de închis.", "info")
            time.sleep(float(step.get("wait_after") or 1))

        elif stype == "fill":
            sel = step.get("selector") or ""
            val = substitute(step.get("value") or "", ctx)
            st = step.get("selector_type") or "css"
            elems = _by_selector(driver, sel, st)
            if not elems:
                log_fn(cont_id, f"Pas {order}: Câmp negăsit ({sel})", "warn")
            else:
                elems[0].clear()
                elems[0].send_keys(val)
                log_fn(cont_id, f"Pas {order}: Completat câmp.", "ok")
            # Default mai lent — Chrome/GBG pe PC-uri lente nu țin pasul cu 0.5s.
            time.sleep(float(step.get("wait_after") if step.get("wait_after") is not None else 1.2))

        elif stype == "click":
            sel = step.get("selector") or ""
            st = step.get("selector_type") or "css"
            elems = _by_selector(driver, sel, st)
            if not elems:
                log_fn(cont_id, f"Pas {order}: Buton negăsit ({sel})", "err")
            else:
                driver.execute_script("arguments[0].click();", elems[0])
                log_fn(cont_id, f"Pas {order}: Click efectuat.", "ok")
            wait = float(step.get("wait_after") if step.get("wait_after") is not None else (step.get("seconds") or 3))
            if pause_fn and pause_fn(cont_id, wait):
                return None
            elif not pause_fn:
                time.sleep(wait)

        elif stype == "wait":
            sec = float(step.get("seconds") or 5)
            log_fn(cont_id, f"Pas {order}: Pauză {sec}s.", "info")
            if pause_fn and pause_fn(cont_id, sec):
                return None
            # pause_fn deja a așteptat; dacă lipsește, sleep aici
            if not pause_fn:
                time.sleep(sec)

        elif stype == "builtin":
            action = (step.get("action") or "").strip()
            log_fn(cont_id, f"Pas {order}: Pornesc acțiune integrată «{action}».", "info")
            pending_builtin = action
            handler = builtin_handlers.get(action)
            if handler:
                handler(driver, cont_id, workflow, ctx)
                pending_builtin = None
            # dacă nu e handler aici, returnăm pentru caller (ex. gbg_catalog_scan)

        else:
            log_fn(cont_id, f"Pas {order}: Tip necunoscut «{stype}» — sar.", "warn")

    return pending_builtin


def build_context(cont_id: str, user: str, password: str, workflow: dict) -> dict:
    login = workflow.get("login_url") or ""
    catalog = workflow.get("catalog_url") or ""
    return {
        "cont_id": cont_id,
        "user": user,
        "pass": password,
        "login_url": login,
        "catalog_url": catalog,
    }
