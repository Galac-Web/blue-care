#!/usr/bin/env python3
"""
Blue-Car — punte de scraping stealth pentru PHP.

Foloseste motorul `nodriver` (acelasi ca stealth-browser-mcp) pentru a descarca
HTML-ul unei pagini trecand de protectiile anti-bot (Cloudflare etc.), acolo unde
scrape.do esueaza sau lipseste tokenul.

Utilizare:
    python stealth_fetch.py <URL> [--wait 6] [--timeout 60] [--selector CSS]

Iesire (stdout): JSON  {"ok": true, "html": "...", "final_url": "...", "elapsed": 4.2}
                        {"ok": false, "error": "mesaj"}

Codul de iesire este 0 la succes, 1 la eroare — ca PHP sa poata decide fallback-ul.
"""
from __future__ import annotations

import argparse
import asyncio
import json
import sys
import time

# Fortam UTF-8 pe stdout/stderr — altfel consola Windows (cp1251) crapa la caractere
# gen ➤, diacritice etc. din HTML.
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass

# nodriver este instalat in venv-ul stealth-browser-mcp (acelasi interpretor).
try:
    import nodriver as uc
except Exception as exc:  # pragma: no cover
    print(json.dumps({"ok": False, "error": f"nodriver indisponibil: {exc}"}))
    sys.exit(1)


CHALLENGE_MARKERS = (
    "just a moment",
    "checking your browser",
    "cf-challenge",
    "cf-browser-verification",
    "verifying you are human",
    "enable javascript and cookies",
)


def _looks_like_challenge(html: str) -> bool:
    low = (html or "").lower()
    if len(low) < 3000 and "just a moment" in low:
        return True
    return any(m in low for m in CHALLENGE_MARKERS)


async def fetch(
    url: str,
    wait: float,
    timeout: float,
    selector: str | None,
    headless: bool,
    selector_timeout: float,
) -> dict:
    start = time.time()
    browser = None
    try:
        browser = await asyncio.wait_for(
            uc.start(headless=headless, sandbox=False),
            timeout=timeout,
        )
        page = await asyncio.wait_for(browser.get(url), timeout=timeout)

        # Asezare initiala scurta.
        await page.sleep(min(wait, 3.0))

        # Asteapta pana dispare challenge-ul Cloudflare (nodriver il rezolva singur).
        deadline = time.time() + timeout
        html = await page.get_content()
        while _looks_like_challenge(html) and time.time() < deadline:
            await page.sleep(1.5)
            try:
                html = await page.get_content()
            except Exception:
                pass

        # Daca s-a cerut un selector: poll SCURT pana apare. Daca nu apare in
        # `selector_timeout`, pagina nu are rezultate — ne intoarcem imediat,
        # ca apelantul (PHP) sa treaca rapid la urmatorul cod OEM.
        selector_found = None
        if selector:
            sel_deadline = min(deadline, time.time() + max(3.0, selector_timeout))
            while time.time() < sel_deadline:
                try:
                    found = await page.query_selector(selector)
                    if found:
                        selector_found = True
                        break
                except Exception:
                    pass
                await page.sleep(0.5)
            if selector_found is None:
                selector_found = False
            try:
                html = await page.get_content()
            except Exception:
                pass

        final_url = url
        try:
            final_url = await page.evaluate("window.location.href") or url
        except Exception:
            pass

        if _looks_like_challenge(html):
            return {
                "ok": False,
                "error": "Cloudflare challenge nerezolvat in timpul alocat",
                "html": html or "",
                "final_url": final_url,
                "elapsed": round(time.time() - start, 2),
            }

        return {
            "ok": True,
            "html": html or "",
            "final_url": final_url,
            "selector_found": selector_found,
            "elapsed": round(time.time() - start, 2),
        }
    except asyncio.TimeoutError:
        return {"ok": False, "error": f"timeout dupa {timeout}s"}
    except Exception as exc:  # pragma: no cover
        return {"ok": False, "error": str(exc)}
    finally:
        if browser is not None:
            try:
                browser.stop()
            except Exception:
                pass


def main() -> int:
    parser = argparse.ArgumentParser(description="Stealth fetch pentru Blue-Car")
    parser.add_argument("url", help="URL-ul de descarcat")
    parser.add_argument("--wait", type=float, default=6.0, help="secunde de asteptare dupa load")
    parser.add_argument("--timeout", type=float, default=60.0, help="timeout total (secunde)")
    parser.add_argument("--selector", default=None, help="asteapta acest selector CSS")
    parser.add_argument("--selector-timeout", type=float, default=15.0, help="cat asteptam selectorul inainte sa renuntam (secunde)")
    parser.add_argument("--headful", action="store_true", help="ruleaza cu fereastra vizibila (mai greu de detectat)")
    parser.add_argument("--out", default=None, help="scrie JSON-ul in acest fisier (recomandat pentru PHP)")
    args = parser.parse_args()

    try:
        result = uc.loop().run_until_complete(
            fetch(args.url, args.wait, args.timeout, args.selector, not args.headful, args.selector_timeout)
        )
    except Exception as exc:  # pragma: no cover
        result = {"ok": False, "error": f"eroare loop: {exc}"}

    payload = json.dumps(result, ensure_ascii=False)

    # Daca s-a cerut --out, scriem JSON curat in fisier (fara zgomotul din stdout).
    if args.out:
        try:
            with open(args.out, "w", encoding="utf-8") as fh:
                fh.write(payload)
        except Exception as exc:  # pragma: no cover
            sys.stderr.write(f"nu pot scrie --out: {exc}\n")
            return 1
        return 0 if result.get("ok") else 1

    # Altfel incadram JSON-ul intre markere unice, ca PHP sa-l extraga din stdout.
    sys.stdout.write("\n<<<BLU_JSON>>>" + payload + "<<<END_BLU_JSON>>>\n")
    sys.stdout.flush()
    return 0 if result.get("ok") else 1


if __name__ == "__main__":
    sys.exit(main())
