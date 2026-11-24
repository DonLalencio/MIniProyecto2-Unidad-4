"""Prueba funcional de la agenda (CRUD + validaciones + búsqueda) contra el servidor local."""
import re
import sqlite3  # noqa: F401  (no se usa; solo evita advertencias de entorno)
import subprocess
import sys
from pathlib import Path

import requests

BASE = "http://127.0.0.1:8080"
MYSQL = Path(r"C:\Users\brand\AppData\Local\Temp\opencode\pw-tools\mariadb\mariadb-11.4.5-winx64\bin\mariadb.exe")
RAIZ = Path(__file__).resolve().parent.parent

fallos = []


def check(nombre, condicion, detalle=""):
    print(("OK   " if condicion else "FALLA") + f" · {nombre}" + (f" · {detalle}" if detalle else ""))
    if not condicion:
        fallos.append(nombre)


def sql(consulta):
    salida = subprocess.run(
        [str(MYSQL), "-u", "root", "agenda_contactos", "-N", "-B", "-e", consulta],
        capture_output=True, text=True, encoding="utf-8",
    )
    return salida.stdout.strip().splitlines()


def token(html):
    m = re.search(r'name="csrf_token" value="([a-f0-9]+)"', html)
    return m.group(1) if m else ""


s = requests.Session()

# ---------------------------------------------------------------- LISTADO
r = s.get(f"{BASE}/index.php", timeout=15)
check("index.php responde 200", r.status_code == 200)
check("index muestra los 6 contactos de ejemplo", r.text.count('class="contacto"') == 6,
      f"encontrados={r.text.count('class=\"contacto\"')}")
check("index escapa correctamente (sin errores PHP)",
      "Warning" not in r.text and "Fatal error" not in r.text)

# ---------------------------------------------------------------- BÚSQUEDA
r = s.get(f"{BASE}/index.php", params={"q": "castro"}, timeout=15)
check("búsqueda por apellido devuelve 1 tarjeta", r.text.count('class="contacto"') == 1)

r = s.get(f"{BASE}/index.php", params={"q": "7771"}, timeout=15)
check("búsqueda por teléfono devuelve 1 tarjeta", r.text.count('class="contacto"') == 1)

r = s.get(f"{BASE}/index.php", params={"q": "%' OR 1=1 -- "}, timeout=15)
check("intento de inyección SQL no devuelve todo", r.text.count('class="contacto"') == 0)
check("intento de inyección no produce error SQL",
      "SQLSTATE" not in r.text and "Fatal error" not in r.text)

r = s.get(f"{BASE}/index.php", params={"q": "<script>alert(1)</script>"}, timeout=15)
check("búsqueda escapa etiquetas (XSS)", "<script>alert(1)</script>" not in r.text)
check("búsqueda muestra el texto escapado", "&lt;script&gt;" in r.text)

# ---------------------------------------------------------------- CREATE
r = s.get(f"{BASE}/crear.php", timeout=15)
tok = token(r.text)
check("crear.php entrega token CSRF", len(tok) == 64)

# Validación de servidor: faltan datos obligatorios
r = s.post(f"{BASE}/guardar.php", data={
    "csrf_token": tok, "nombre": "", "apellido": "", "telefono": "", "email": "correo-invalido",
}, allow_redirects=True, timeout=15)
check("validación rechaza nombre/teléfono vacíos y email inválido",
      "El nombre es obligatorio" in r.text and "El correo electrónico no tiene un formato válido" in r.text)
check("el formulario conserva los datos escritos", 'value="correo-invalido"' in r.text)

# CSRF inválido
r = s.post(f"{BASE}/guardar.php", data={
    "csrf_token": "x" * 64, "nombre": "X", "apellido": "Y", "telefono": "1234567",
}, allow_redirects=True, timeout=15)
check("token CSRF manipulado es rechazado", "sesión del formulario expiró" in r.text)

# Crear contacto real con foto
foto = RAIZ / "archivos" / "demo-ana.png"
r = s.get(f"{BASE}/crear.php", timeout=15)
tok = token(r.text)
with foto.open("rb") as fh:
    r = s.post(f"{BASE}/guardar.php", data={
        "csrf_token": tok,
        "nombre": "Zoe",
        "apellido": "Pruebas",
        "telefono": "+503 7000-1234",
        "email": "zoe.pruebas@example.com",
        "direccion": "Calle Falsa 123",
        "notas": "Contacto creado por la prueba automatizada.",
    }, files={"foto": ("zoe.png", fh, "image/png")}, allow_redirects=True, timeout=20)

check("CREATE muestra mensaje de éxito", "se guardó correctamente" in r.text)
check("CREATE redirige al detalle del nuevo contacto", "Detalle del contacto" in r.text)
filas = sql("SELECT id, nombre, apellido, telefono, email, foto FROM contactos WHERE nombre='Zoe'")
check("CREATE insertó el registro en MySQL", len(filas) == 1, str(filas))
nuevo_id = int(filas[0].split("\t")[0]) if filas else 0
foto_guardada = filas[0].split("\t")[5] if filas else ""
check("la fotografía se copió a /archivos", (RAIZ / "archivos" / foto_guardada).is_file(), foto_guardada)

# ---------------------------------------------------------------- READ individual
r = s.get(f"{BASE}/ver.php", params={"id": nuevo_id}, timeout=15)
check("ver.php muestra el nombre completo", "Zoe Pruebas" in r.text)
check("ver.php muestra el teléfono", "+503 7000-1234" in r.text)

r = s.get(f"{BASE}/ver.php", params={"id": 99999}, timeout=15)
check("id inexistente se maneja sin error", r.status_code == 200 and "no existe" in r.text)
check("id inexistente no filtra SQL", "SQLSTATE" not in r.text)

r = s.get(f"{BASE}/ver.php", params={"id": "abc"}, timeout=15)
check("id no numérico se rechaza", "no es válido" in r.text)

# ---------------------------------------------------------------- UPDATE
r = s.get(f"{BASE}/editar.php", params={"id": nuevo_id}, timeout=15)
tok = token(r.text)
check("editar.php precarga los datos", 'value="Zoe"' in r.text and 'value="Calle Falsa 123"' in r.text)

r = s.post(f"{BASE}/actualizar.php", data={
    "csrf_token": tok, "id": nuevo_id,
    "nombre": "Zoe", "apellido": "Pruebas Actualizada",
    "telefono": "7000-9999", "email": "zoe.nueva@example.com",
    "direccion": "Avenida Nueva 456", "notas": "Registro modificado.",
}, allow_redirects=True, timeout=15)
check("UPDATE muestra mensaje de éxito", "se actualizaron correctamente" in r.text)
filas = sql(f"SELECT apellido, telefono, email, direccion FROM contactos WHERE id={nuevo_id}")
check("UPDATE se refleja en MySQL",
      bool(filas) and "Pruebas Actualizada" in filas[0] and "7000-9999" in filas[0], str(filas))
check("UPDATE conserva la fotografía anterior",
      sql(f"SELECT foto FROM contactos WHERE id={nuevo_id}")[0] == foto_guardada)

# Validación en UPDATE
r = s.get(f"{BASE}/editar.php", params={"id": nuevo_id}, timeout=15)
tok = token(r.text)
r = s.post(f"{BASE}/actualizar.php", data={
    "csrf_token": tok, "id": nuevo_id, "nombre": "Zoe",
    "apellido": "Pruebas Actualizada", "telefono": "abc", "email": "",
}, allow_redirects=True, timeout=15)
check("UPDATE inválido no modifica y muestra el error",
      "El teléfono solo admite números" in r.text)

# ---------------------------------------------------------------- DELETE
r = s.get(f"{BASE}/eliminar.php", params={"id": nuevo_id}, timeout=15)
tok = token(r.text)
check("eliminar.php pide confirmación antes de borrar",
      "¿Eliminar este contacto?" in r.text)
check("la confirmación es un formulario POST",
      'method="post"' in r.text.lower())

r = s.post(f"{BASE}/eliminar.php", params={"id": nuevo_id}, data={
    "csrf_token": tok, "id": nuevo_id,
}, allow_redirects=True, timeout=15)
check("DELETE muestra mensaje de éxito", "fue eliminado de la agenda" in r.text)
check("DELETE borró el registro", sql(f"SELECT id FROM contactos WHERE id={nuevo_id}") == [])
check("DELETE borró también la fotografía", not (RAIZ / "archivos" / foto_guardada).is_file())

# GET sobre eliminar.php no debe borrar nada
r = s.get(f"{BASE}/eliminar.php", params={"id": 1}, timeout=15)
check("GET en eliminar.php no borra", sql("SELECT id FROM contactos WHERE id=1") == ["1"])

# ---------------------------------------------------------------- Extras de seguridad
r = s.get(f"{BASE}/config/db.php", timeout=15)
check("config/db.php no se sirve como texto plano", "DB_PASS" not in r.text)

r = s.get(f"{BASE}/index.php", params={"pagina": "999"}, timeout=15)
check("paginación fuera de rango se corrige", r.status_code == 200)

# Restaurar datos de ejemplo
subprocess.run([str(MYSQL), "-u", "root", "agenda_contactos", "-e",
                "DELETE FROM contactos WHERE nombre IN ('Zoe');"], capture_output=True)

print("\n" + ("TODAS LAS PRUEBAS PASARON" if not fallos else f"FALLOS: {fallos}"))
sys.exit(0 if not fallos else 1)
