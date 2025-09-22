import requests
from bs4 import BeautifulSoup

URL = "https://isil.pe/areas-academicas"

# Hacemos la petición
resp = requests.get(URL)
soup = BeautifulSoup(resp.text, "lxml")

# Selector del label
elements = soup.select("label.label-submenu-items")

print("Total encontrados:", len(elements))
for e in elements:
    print("-", e.get_text(strip=True))
