from fastapi import FastAPI
from pydantic import BaseModel
import requests
from bs4 import BeautifulSoup
import pandas as pd
from typing import List, Optional
import time
import logging

app = FastAPI()

logging.basicConfig(level=logging.INFO)

# 📌 Modelo para recibir parámetros del scraping
class Field(BaseModel):
    field_name: str
    selector_type: str   # id, class, tag, attribute, text, css
    selector_value: str
    attr: Optional[str] = None   # 👈 nuevo (ej: "href", "src")
    path: Optional[str] = "/"    # por defecto home


class ScrapingRequest(BaseModel):
    url_base: str
    fields: List[Field]

# Headers
HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/123.0.0.0 Safari/537.36"
    ),
    "Accept-Language": "es-ES,es;q=0.9,en;q=0.8",
    "Referer": "https://www.google.com/",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Connection": "keep-alive",
}
@app.post("/scrape")
def scrape(request: ScrapingRequest):
    results = []

    url = request.url_base.rstrip("/")
    response = requests.get(url, headers=HEADERS, timeout=15)
    response.raise_for_status()
    soup = BeautifulSoup(response.text, "lxml")

    # identificar menu (padre)
    menus = soup.select("a.tit_sbmenutab")
    for menu in menus:
        menu_text = menu.get_text(strip=True)
        parent_block = menu.find_parent("li") or menu.find_parent("div")

        # buscar carreras dentro del mismo bloque
        carreras = parent_block.select("ul.content_sbmenutab li a") if parent_block else []
        if not carreras:
            results.append({"menu": menu_text, "carrera": None, "carrera_url": None})
        else:
            for carrera in carreras:
                results.append({
                    "menu": menu_text,
                    "carrera": carrera.get_text(strip=True),
                    "carrera_url": carrera.get("href")  # 👈 aquí se captura el link
                })

    # agregar row_id
    for i, r in enumerate(results, start=1):
        r["row_id"] = i

    return {"data": results}

