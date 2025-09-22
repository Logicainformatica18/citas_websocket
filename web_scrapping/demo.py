import requests
from bs4 import BeautifulSoup
import pandas as pd

url = "https://isil.pe/"
html = requests.get(url).text
soup = BeautifulSoup(html, "html.parser")

data = []

for bloque in soup.select("div.sbmenu_list"):
    area = bloque.select_one("a.tit_sbmenutab")
    area_text = area.get_text(strip=True) if area else "Sin área"

    for hijo in bloque.select("ul.content_sbmenutab li a"):
        carrera = hijo.get_text(strip=True)
        data.append({
            "menu": "menu",        # fijo
            "area": area_text,     # columna padre
            "carrera": carrera     # columna hijo
        })

df = pd.DataFrame(data)

# Mostrar en pantalla
print(df.to_string(index=False))
