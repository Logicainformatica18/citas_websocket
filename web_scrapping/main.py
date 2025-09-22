from fastapi import FastAPI
from pydantic import BaseModel
import requests
from bs4 import BeautifulSoup
import pandas as pd
from typing import List, Optional

app = FastAPI()

# 📌 Modelo para recibir parámetros del scraping
class Field(BaseModel):
    field_name: str
    selector: str
    path: Optional[str] = "/"   # por defecto home

class ScrapingRequest(BaseModel):
    url_base: str
    fields: List[Field]

# Headers que simulan un navegador real
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
    raw_results = []

    for field in request.fields:
        url = request.url_base.rstrip("/") + (field.path or "/")

        try:
            response = requests.get(url, headers=HEADERS, timeout=15)
            response.raise_for_status()  # lanza excepción si hay 403/404/etc
        except Exception as e:
            return {"error": f"❌ Error al acceder a {url}: {str(e)}"}

        soup = BeautifulSoup(response.text, "lxml")
        values = soup.select(field.selector)

        for i, v in enumerate(values, start=1):
            raw_results.append({
                "row_id": i,
                "campo": field.field_name,
                "valor": v.get_text(strip=True)
            })

    # Convertir a tabla pivot dinámica
    df_raw = pd.DataFrame(raw_results)
    if df_raw.empty:
        return {"data": []}

    df_pivot = df_raw.pivot_table(
        index="row_id", columns="campo", values="valor", aggfunc="first"
    ).reset_index()

    # Convertir DataFrame a JSON
    result = df_pivot.to_dict(orient="records")
    return {"data": result}
