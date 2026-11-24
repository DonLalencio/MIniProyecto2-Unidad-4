"""Genera las fotografías de ejemplo de la agenda (avatares PNG 400x400)."""
from PIL import Image, ImageDraw, ImageFont
from pathlib import Path

DESTINO = Path(__file__).resolve().parent.parent / "archivos"
DESTINO.mkdir(parents=True, exist_ok=True)

personas = {
    "demo-ana.png": ("AM", (99, 102, 241), (139, 92, 246)),
    "demo-bruno.png": ("BC", (14, 165, 233), (37, 99, 235)),
    "demo-carla.png": ("CR", (236, 72, 153), (219, 39, 119)),
    "demo-diego.png": ("DF", (16, 185, 129), (5, 150, 105)),
    "demo-elena.png": ("EG", (245, 158, 11), (234, 88, 12)),
    "demo-francisco.png": ("FA", (100, 116, 139), (51, 65, 85)),
}

TAM = 400
FUENTES = [
    "C:/Windows/Fonts/segoeuib.ttf",
    "C:/Windows/Fonts/arialbd.ttf",
    "C:/Windows/Fonts/arial.ttf",
]

for nombre, (iniciales, c1, c2) in personas.items():
    img = Image.new("RGB", (TAM, TAM), c1)
    px = img.load()
    for y in range(TAM):
        for x in range(TAM):
            t = (x + y) / (2 * TAM - 2)
            px[x, y] = (
                int(c1[0] + (c2[0] - c1[0]) * t),
                int(c1[1] + (c2[1] - c1[1]) * t),
                int(c1[2] + (c2[2] - c1[2]) * t),
            )

    # Círculo interior translúcido
    capa = Image.new("RGBA", (TAM, TAM), (0, 0, 0, 0))
    d = ImageDraw.Draw(capa)
    d.ellipse((52, 52, TAM - 52, TAM - 52), fill=(255, 255, 255, 28))
    img = Image.alpha_composite(img.convert("RGBA"), capa).convert("RGB")

    fuente = None
    for ruta in FUENTES:
        if Path(ruta).exists():
            fuente = ImageFont.truetype(ruta, 150)
            break
    if fuente is None:
        fuente = ImageFont.load_default()

    d = ImageDraw.Draw(img)
    caja = d.textbbox((0, 0), iniciales, font=fuente)
    ancho = caja[2] - caja[0]
    alto = caja[3] - caja[1]
    d.text(((TAM - ancho) / 2 - caja[0], (TAM - alto) / 2 - caja[1]), iniciales,
           font=fuente, fill=(255, 255, 255))

    img.save(DESTINO / nombre, "PNG", optimize=True)
    print("generado:", nombre)
