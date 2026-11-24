import fs from 'fs';
import path from 'path';
import { execFileSync } from 'child_process';
import { fileURLToPath } from 'url';
import { createHighlighter } from 'shiki';
import puppeteer from 'puppeteer-core';

/* ==========================================================================
   Generador del reporte de evidencias (PDF) de la Agenda de Contactos
   - Captura la aplicación real (PHP + MySQL) en un navegador headless
   - Captura fragmentos de código resaltados como imágenes tipo editor
   - Captura salidas reales de consola (mariadb)
   - Compone un PDF A4 con 1 captura por página
   ========================================================================== */

const PROYECTO = process.env.PROYECTO_DIR || path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const BASE = process.env.BASE_URL || 'http://localhost/agenda-contactos';
const EDGE = process.env.EDGE_PATH || 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const MYSQL = process.env.MYSQL_BIN || 'mariadb';
const PHP_BIN = process.env.PHP_BIN || 'php';
const OUT = path.join(PROYECTO, 'docs');
const CAP = path.join(OUT, 'capturas');
const TMP = process.env.TMP_DIR || path.join(PROYECTO, 'tools', 'reporte', 'tmp');

/** Convierte una ruta relativa dentro de /docs en una URL file:// utilizable por el navegador. */
const IMG = (rel) => 'file:///' + path.join(OUT, rel).replace(/\\/g, '/');

const INTEGRANTES = [
  ['1', 'Brandon Emilio Hernández', '—', 'Conexión PDO, CRUD, validaciones, despliegue'],
  ['2', '_(integrante 2)_', '—', '_(completar)_'],
  ['3', '_(integrante 3)_', '—', '_(completar)_'],
];
const MATERIA = 'Programación Web';
const DOCENTE = '_(completar)_';

const REPO_URL = 'https://github.com/DonLalencio/MIniProyecto2-Unidad-4';

fs.mkdirSync(CAP, { recursive: true });
fs.mkdirSync(TMP, { recursive: true });

/* ------------------------------------------------------------------ utils */
const leer = (rel) => fs.readFileSync(path.join(PROYECTO, rel), 'utf8');

function bloque(rel, inicio, fin, extra = 0) {
  const lineas = leer(rel).replace(/\t/g, '    ').split(/\r?\n/);
  let i = lineas.findIndex((l) => l.includes(inicio));
  if (i < 0) throw new Error(`No se encontró "${inicio}" en ${rel}`);
  let j = fin
    ? lineas.findIndex((l, idx) => idx > i && l.includes(fin))
    : Math.min(lineas.length - 1, i + 24);
  if (j < 0) throw new Error(`No se encontró "${fin}" en ${rel}`);
  j = Math.min(lineas.length - 1, j + extra);
  // recorta líneas vacías al inicio y al final
  while (i < j && lineas[i].trim() === '') i++;
  while (j > i && lineas[j].trim() === '') j--;
  return { codigo: lineas.slice(i, j + 1).join('\n'), desde: i + 1, hasta: j + 1 };
}

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/* --------------------------------------------------------------- navegador */
const capturas = {};   // nombre -> { archivo, url, leyenda }
const consolas = {};   // nombre -> { comando, salida, leyenda }

async function capturarNavegador() {
  const browser = await puppeteer.launch({
    executablePath: EDGE,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--hide-scrollbars', '--force-device-scale-factor=1.5'],
    defaultViewport: { width: 1280, height: 960, deviceScaleFactor: 1.5 },
  });
  const page = await browser.newPage();

  const shot = async (nombre, { full = false, leyenda = '' } = {}) => {
    await new Promise((r) => setTimeout(r, 350));
    await page.screenshot({ path: path.join(CAP, `${nombre}.png`), fullPage: full });
    capturas[nombre] = { archivo: `capturas/${nombre}.png`, url: page.url(), leyenda };
    console.log('  · captura', nombre);
  };

  const ir = (ruta) => page.goto(BASE + ruta, { waitUntil: 'networkidle0' });

  // 1. Listado principal
  await ir('/index.php');
  await shot('index', { leyenda: 'Listado de contactos obtenido desde MySQL (READ).' });

  // 2. Búsqueda con resultado
  await ir('/index.php?q=castro');
  await shot('busqueda', { leyenda: 'Búsqueda por el apellido “castro”: un único resultado.' });

  // 3. Intento de inyección SQL en el buscador
  await ir('/index.php?q=' + encodeURIComponent("%' OR 1=1 -- "));
  await shot('inyeccion', {
    full: true,
    leyenda: 'El término de inyección se trata como texto: cero resultados, sin errores SQL.',
  });

  // 4. Intento de XSS en el buscador
  await ir('/index.php?q=' + encodeURIComponent('<script>alert("XSS")</script>'));
  await shot('xss_busqueda', {
    full: true,
    leyenda: 'El término con etiquetas se muestra escapado, sin ejecutar el script.',
  });

  // 5. Validación del servidor: formulario vacío + correo inválido
  await ir('/crear.php');
  await page.type('#email', 'correo-invalido');
  await page.evaluate(() => document.querySelector('#foto') && document.querySelector('form.formulario').submit());
  await page.waitForNavigation({ waitUntil: 'networkidle0' });
  await shot('validacion', {
    full: true,
    leyenda: 'Validación en servidor: mensajes por campo y conservación de los datos escritos.',
  });

  // 6. CREATE con fotografía
  await ir('/crear.php');
  await page.evaluate(() => {
    ['nombre', 'apellido', 'telefono', 'email', 'direccion', 'notas'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
  });
  await page.type('#nombre', 'María José');
  await page.type('#apellido', 'Peña');
  await page.type('#telefono', '7899-1234');
  await page.type('#email', 'maria.pena@example.com');
  await page.type('#direccion', 'Calle Los Pinos #12, San Salvador');
  await page.type('#notas', 'Contacto agregado durante la demostración del sistema.');
  const input = await page.$('#foto');
  await input.uploadFile(path.join(PROYECTO, 'archivos', 'demo-elena.png'));
  await page.evaluate(() => document.activeElement.blur());
  await shot('crear_form', { full: true, leyenda: 'Formulario de creación con los datos capturados y la imagen seleccionada.' });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    page.click('button[type="submit"]'),
  ]);
  const nuevoId = new URL(page.url()).searchParams.get('id');
  await shot('crear_ok', { leyenda: `Registro creado (id ${nuevoId}) y confirmación en pantalla.` });

  // 7. UPDATE
  await ir(`/editar.php?id=${nuevoId}`);
  await shot('editar_form', {
    full: true,
    leyenda: 'Formulario de edición precargado con los datos del contacto.',
  });
  await page.evaluate(() => {
    document.querySelector('#telefono').value = '7555-4321';
    document.querySelector('#email').value = 'mj.pena@example.com';
    document.querySelector('#direccion').value = 'Av. Olímpica #45, San Salvador';
  });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    page.click('button[type="submit"]'),
  ]);
  await shot('actualizar_ok', {
    leyenda: 'Datos actualizados: el detalle muestra el teléfono y el correo nuevos.',
  });

  // 8. XSS almacenado en notas (escape de salida)
  await ir(`/editar.php?id=${nuevoId}`);
  await page.evaluate(() => {
    document.querySelector('#notas').value = '<script>alert("XSS en notas")</script> Texto de prueba';
  });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    page.click('button[type="submit"]'),
  ]);
  await shot('xss_notas', {
    leyenda: 'La etiqueta guardada en notas se imprime como texto: htmlspecialchars() en acción.',
  });

  // 9. DELETE: confirmación y resultado
  await ir(`/eliminar.php?id=${nuevoId}`);
  await shot('eliminar_confirm', {
    full: true,
    leyenda: 'Pantalla de confirmación previa a la eliminación (formulario POST).',
  });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle0' }),
    page.click('button[type="submit"]'),
  ]);
  await shot('eliminar_ok', {
    leyenda: 'Contacto eliminado: mensaje de confirmación en el listado.',
  });

  // 10. Detalle individual de un contacto de ejemplo
  await ir('/ver.php?id=1');
  await shot('ver', { leyenda: 'Ficha individual del contacto #1 (SELECT por id).' });

  await browser.close();
}

/* -------------------------------------------------------------- terminales */
async function capturarConsolas() {
  const consultas = [
    {
      nombre: 'consola_bd',
      leyenda: 'Consulta real a MySQL/MariaDB con la tabla contactos creada por database/agenda.sql.',
      comando: `mariadb -u root agenda_contactos -e "SELECT id, nombre, apellido, telefono, foto FROM contactos;"`,
      args: ['-u', 'root', 'agenda_contactos', '-e',
        'SELECT id, nombre, apellido, telefono, foto FROM contactos;'],
    },
    {
      nombre: 'consola_estructura',
      leyenda: 'Estructura de la tabla contactos en la base de datos MySQL.',
      comando: `mariadb -u root agenda_contactos -e "DESCRIBE contactos;"`,
      args: ['-u', 'root', 'agenda_contactos', '-e', 'DESCRIBE contactos;'],
    },
    {
      nombre: 'consola_pdo',
      leyenda: 'Controladores PDO disponibles en el servidor PHP (pdo_mysql activo).',
      comando: `php -r "print_r(PDO::getAvailableDrivers()); echo 'PHP ' . PHP_VERSION . PHP_EOL;"`,
      args: ['-r', "print_r(PDO::getAvailableDrivers()); echo 'PHP ' . PHP_VERSION . PHP_EOL;"],
      bin: PHP_BIN,
    },
  ];

  for (const c of consultas) {
    let salida = '';
    try {
      salida = execFileSync(c.bin || MYSQL, c.args, { encoding: 'utf8' });
    } catch (e) {
      salida = (e.stdout || '') + (e.stderr || '');
    }
    consolas[c.nombre] = { comando: c.comando, salida: salida.trimEnd(), leyenda: c.leyenda };
    console.log('  · consola', c.nombre);
  }
}

/* ------------------------------------------------------------------- código */
let highlighter;

async function resaltar(codigo, lang) {
  const html = highlighter.codeToHtml(codigo, { lang, theme: 'github-dark' });
  return html;
}

async function capturarCodigo(browser) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1240, height: 600, deviceScaleFactor: 2 });

  return async function fotoCodigo(nombre, archivo, lang, codigo, desde, hasta) {
    const resaltado = (await resaltar(codigo, lang)).replace(/<\/span>\n<span class="line">/g, '</span><span class="line">');
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
      * { box-sizing: border-box; }
      body { margin:0; background:#0b1020; padding:20px; font-family: 'Cascadia Code', Consolas, monospace; }
      .editor { width: 1180px; border-radius: 12px; overflow: hidden; box-shadow: 0 18px 40px rgba(0,0,0,.45); }
      .barra { display:flex; align-items:center; gap:8px; padding:10px 14px; background:#111827; border-bottom:1px solid #1f2937; }
      .punto { width:11px; height:11px; border-radius:50%; display:inline-block; }
      .r { background:#ef4444 } .y { background:#f59e0b } .g { background:#22c55e }
      .archivo { margin-left:12px; color:#cbd5e1; font-size:13px; font-weight:600; letter-spacing:.01em; }
      .rango { margin-left:auto; color:#64748b; font-size:12px; }
      pre { margin:0 !important; padding: 18px 22px 20px 8px !important; font-size: 19px !important; line-height:1.5 !important; }
      code { font-family: inherit; }
      .shiki { counter-reset: linea ${desde - 1} !important; }
      .shiki .line { display:block; }
      .shiki .line::before { counter-increment: linea; content: counter(linea);
        display:inline-block; width: 3.2em; padding-right: 1.1em; text-align:right;
        color:#4b5563; -webkit-user-select:none; }
    </style></head><body>
      <div class="editor">
        <div class="barra">
          <span class="punto r"></span><span class="punto y"></span><span class="punto g"></span>
          <span class="archivo">${esc(archivo)}</span>
          <span class="rango">líneas ${desde}–${hasta}</span>
        </div>
        ${resaltado}
      </div>
    </body></html>`;

    await page.setContent(html, { waitUntil: 'load' });
    const el = await page.$('.editor');
    await el.screenshot({ path: path.join(CAP, `${nombre}.png`) });
    console.log('  · código', nombre);
    return { archivo: `capturas/${nombre}.png`, leyenda: `${archivo} · líneas ${desde}–${hasta}` };
  };
}

/* -------------------------------------------------------------- evidencias */
function definiciones() {
  const d = [];

  const c1 = bloque('config/db.php', 'const DB_HOST', '$pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $opciones);');
  d.push({
    n: 1,
    titulo: 'Conexión a base de datos utilizando PDO',
    codigos: [{ nombre: 'cod1_pdo', archivo: 'config/db.php', lang: 'php', ...c1 }],
    consolas: ['consola_pdo', 'consola_bd'],
    ejecucion: ['index', 'consola_bd'],
    explicacion: [
      'La conexión se concentra en <code>config/db.php</code>, que todas las páginas cargan con <code>require_once</code>. Allí se define el DSN con host, nombre de la base y <code>charset=utf8mb4</code>, y se instancia PDO con tres opciones clave: <code>ERRMODE_EXCEPTION</code> para que cualquier fallo lance una excepción capturable, <code>FETCH_ASSOC</code> para recibir arreglos asociativos y <code>EMULATE_PREPARES = false</code> para que las sentencias se preparen realmente en MySQL.',
      'El bloque <code>try/catch</code> registra el detalle técnico con <code>error_log()</code> y devuelve al navegador una página genérica de “servicio no disponible”; nunca se expone el mensaje de MySQL ni la traza. Al reutilizar un único objeto <code>$pdo</code> se evita abrir conexiones redundantes y se mantiene la configuración en un solo archivo.',
    ],
    resultado: [
      'Conexión establecida con MySQL/MariaDB mediante el controlador <code>pdo_mysql</code>.',
      'Los errores de conexión quedan en el log del servidor, no en la pantalla del usuario.',
    ],
  });

  const c2 = bloque('index.php', '$condicion = \'\';', '$stmt->execute();');
  d.push({
    n: 2,
    titulo: 'Uso de sentencias preparadas (prepare y execute)',
    codigos: [{ nombre: 'cod2_prepare', archivo: 'index.php', lang: 'php', ...c2 }],
    ejecucion: ['inyeccion', 'consola_bd'],
    explicacion: [
      'Todas las consultas del sistema se ejecutan con <code>prepare()</code> y <code>execute()</code>: el SQL contiene marcadores (<code>:id</code>, <code>:nombre</code>, <code>:limite</code>) y los valores viajan por separado, de modo que MySQL los trata como datos y jamás como parte de la sentencia. En el buscador se usan cuatro marcadores distintos para el mismo término porque PDO, con preparaciones nativas, no permite reutilizar un marcador.',
      'El listado usa <code>bindValue()</code> con <code>PDO::PARAM_INT</code> para <code>LIMIT</code>/<code>OFFSET</code> y <code>PDO::PARAM_STR</code> para el texto. Para comprobarlo se envió <code>%\' OR 1=1 --</code> en el buscador: al viajar como parámetro se busca literalmente, el resultado es vacío y no aparecen errores de SQL ni registros filtrados.',
    ],
    resultado: [
      'Ninguna consulta concatena variables dentro del SQL.',
      'El intento de inyección <code>OR 1=1</code> no altera el resultado de la búsqueda.',
    ],
  });

  const c3 = bloque('guardar.php', '5. INSERT con sentencia preparada', '$nuevoId = (int) $pdo->lastInsertId();');
  d.push({
    n: 3,
    titulo: 'Inserción de datos (CREATE)',
    codigos: [{ nombre: 'cod3_insert', archivo: 'guardar.php', lang: 'php', ...c3 }],
    ejecucion: ['crear_form', 'crear_ok'],
    explicacion: [
      '<code>guardar.php</code> recibe el POST de <code>crear.php</code>, valida el token CSRF y los datos, guarda la fotografía y ejecuta un <code>INSERT</code> con siete marcadores. Los campos opcionales vacíos se envían como <code>NULL</code>, de modo que la base almacena un valor nulo en lugar de una cadena vacía.',
      'El identificador generado se obtiene con <code>lastInsertId()</code> y se redirige a <code>ver.php?id=N</code> (patrón POST → Redirect → GET), lo que evita reenviar el formulario al recargar. Si el <code>INSERT</code> falla, el <code>catch</code> registra el error, elimina la imagen ya subida y devuelve al usuario al formulario con un mensaje genérico.',
    ],
    resultado: [
      'El contacto se guarda con su fotografía y aparece inmediatamente en el detalle y en el listado.',
      'El formulario no se reenvía al actualizar la página (redirección posterior).',
    ],
  });

  const c4 = bloque('index.php', '$sql = "SELECT id, nombre, apellido', '$contactos = $stmt->fetchAll();');
  d.push({
    n: 4,
    titulo: 'Consulta de datos (READ – listado de contactos)',
    codigos: [{ nombre: 'cod4_select', archivo: 'index.php', lang: 'php', ...c4 }],
    ejecucion: ['index', 'busqueda'],
    explicacion: [
      '<code>index.php</code> construye el <code>WHERE</code> solo cuando existe un término de búsqueda y lo reutiliza en dos consultas: un <code>COUNT(*)</code> para saber el total de registros y un <code>SELECT</code> con <code>LIMIT</code>/<code>OFFSET</code> para traer la página actual de nueve contactos.',
      'Del <code>SELECT</code> se piden únicamente las columnas que muestran las tarjetas y se ordena por apellido y nombre. Cada fila se imprime escapada con <code>e()</code> y se enlaza a <code>ver.php</code>, <code>editar.php</code> y <code>eliminar.php</code>; la paginación conserva el término de búsqueda mediante <code>urlencode()</code>.',
    ],
    resultado: [
      'Los seis registros de ejemplo se muestran con su fotografía y datos principales.',
      'La consulta devuelve solo las columnas necesarias y respeta el orden alfabético.',
    ],
  });

  const c5 = bloque('includes/funciones.php', 'function obtener_contacto', 'return $contacto ?: null;');
  d.push({
    n: 5,
    titulo: 'Consulta individual de un contacto (detalle)',
    codigos: [{ nombre: 'cod5_select_id', archivo: 'includes/funciones.php', lang: 'php', ...c5 }],
    ejecucion: ['ver'],
    explicacion: [
      '<code>ver.php</code> valida el parámetro con <code>filter_var(..., FILTER_VALIDATE_INT)</code> y lo entrega a <code>obtener_contacto()</code>, que ejecuta <code>SELECT ... WHERE id = :id LIMIT 1</code> con una sentencia preparada y devuelve el arreglo asociativo o <code>null</code> si no existe.',
      'Cuando el id no corresponde a ningún registro se muestra un aviso y se regresa al listado, sin revelar detalles técnicos; si el valor no es numérico se descarta antes de consultar. La ficha imprime cada campo con <code>e()</code> y convierte el teléfono y el correo en enlaces <code>tel:</code> y <code>mailto:</code>.',
    ],
    resultado: [
      'Se muestra la ficha completa: fotografía, teléfono, correo, dirección, notas y fecha de registro.',
      'Un id inexistente o no numérico se maneja con un mensaje amable, no con un error de SQL.',
    ],
  });

  const c6 = bloque('actualizar.php', '5. UPDATE con sentencia preparada', "':id'        => $id,");
  d.push({
    n: 6,
    titulo: 'Actualización de datos (UPDATE)',
    codigos: [{ nombre: 'cod6_update', archivo: 'actualizar.php', lang: 'php', ...c6 }],
    ejecucion: ['editar_form', 'actualizar_ok'],
    explicacion: [
      '<code>editar.php</code> recupera el contacto y precarga el formulario con sus datos actuales. Al enviar, <code>actualizar.php</code> valida POST, token CSRF, id y campos, y luego ejecuta un <code>UPDATE</code> con nueve marcadores, incluido el id en la cláusula <code>WHERE</code>.',
      'La fotografía es opcional: si llega una nueva se guarda con un nombre único y, solo después de confirmar el <code>UPDATE</code>, se elimina la anterior del disco; si algo falla se borra la nueva para no dejar archivos huérfanos. Si no se envía imagen, se conserva el nombre existente.',
    ],
    resultado: [
      'Los cambios se reflejan en el detalle y en el listado.',
      'La imagen anterior se reemplaza solo cuando la actualización fue exitosa.',
    ],
  });

  const c7 = bloque('eliminar.php', 'POST → confirmación aceptada', "borrar_foto((string) \$contacto['foto']);");
  d.push({
    n: 7,
    titulo: 'Eliminación de datos (DELETE)',
    codigos: [{ nombre: 'cod7_delete', archivo: 'eliminar.php', lang: 'php', ...c7 }],
    ejecucion: ['eliminar_confirm', 'eliminar_ok'],
    explicacion: [
      '<code>eliminar.php</code> trabaja en dos pasos: la petición GET muestra una pantalla de confirmación con los datos del contacto y un formulario POST con token CSRF; el POST ejecuta <code>DELETE FROM contactos WHERE id = :id</code> mediante sentencia preparada.',
      'Tras borrar el registro se elimina también la imagen de <code>/archivos</code> y se redirige al listado con un mensaje de confirmación. Al exigir POST, un enlace o un bot no puede eliminar registros por accidente, y el token impide peticiones CSRF desde otros sitios.',
    ],
    resultado: [
      'El registro desaparece de la tabla y del listado.',
      'La fotografía asociada se elimina del servidor para no dejar basura.',
    ],
  });

  const c8a = bloque('includes/funciones.php', '// --- teléfono (obligatorio', '// --- fotografía');
  const c8b2 = bloque('crear.php', 'label for="telefono"', 'title="Entre 7 y 30 caracteres', 1);
  d.push({
    n: 8,
    titulo: 'Validación de formularios (HTML5 y servidor)',
    codigos: [
      { nombre: 'cod8_validacion_php', archivo: 'includes/funciones.php', lang: 'php', ...c8a },
      { nombre: 'cod8_validacion_html', archivo: 'crear.php', lang: 'html', etiqueta: 'crear.php', ...c8b2 },
    ],
    ejecucion: ['validacion'],
    explicacion: [
      'El formulario usa validación HTML5 (<code>required</code>, <code>type="tel"</code>, <code>type="email"</code>, <code>pattern</code>, <code>maxlength</code>) que da respuesta inmediata en el navegador. En el servidor, <code>validar_contacto()</code> repite y amplía las reglas: campos obligatorios, longitud máxima, teléfono con expresión regular de 7 a 30 caracteres, correo con <code>FILTER_VALIDATE_EMAIL</code> y verificación de la imagen por MIME real y tamaño.',
      'Si hay errores, se guardan en sesión junto con los valores enviados y se redirige al formulario, que se repinta mostrando el mensaje de cada campo sin perder lo escrito. La validación de servidor es la definitiva: aunque se desactive JavaScript, los datos no llegan a la base si no cumplen las reglas.',
    ],
    resultado: [
      'El teléfono y la fotografía son obligatorios; el correo se valida solo si se escribe.',
      'Los errores se muestran por campo y los datos capturados se conservan.',
    ],
  });

  const c9a = bloque('includes/funciones.php', 'function e(mixed $valor)', '}', 0);
  const c9b = bloque('index.php', '<p class="resumen-busqueda">', '</p>');
  d.push({
    n: 9,
    titulo: 'Sanitización de salida (htmlspecialchars)',
    codigos: [
      { nombre: 'cod9_escape', archivo: 'includes/funciones.php', lang: 'php', ...c9a },
      { nombre: 'cod9_uso', archivo: 'index.php', lang: 'html', ...c9b },
    ],
    ejecucion: ['xss_notas', 'xss_busqueda'],
    explicacion: [
      'La función <code>e()</code> centraliza el escapado: <code>htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\')</code> convierte <code>&lt;</code>, <code>&gt;</code>, <code>&amp;</code>, comillas y apóstrofes en entidades HTML. Se aplica a todo dato que provenga del usuario o de la base: nombres, teléfono, correo, notas, el término de búsqueda y los atributos <code>value</code> de los formularios.',
      'De este modo, un texto como <code>&lt;script&gt;alert(1)&lt;/script&gt;</code> se muestra literalmente y no se ejecuta. La defensa se complementa con las sentencias preparadas, así que ni la base ni el navegador interpretan contenido malicioso.',
    ],
    resultado: [
      'La etiqueta guardada en las notas se ve como texto, no se ejecuta.',
      'El término de búsqueda con etiquetas también se imprime escapado.',
    ],
  });

  const c10 = bloque('index.php', 'if ($busqueda !== \'\') {', '$parametros = [', 5);
  d.push({
    n: 10,
    titulo: 'Implementación de búsqueda de contactos',
    codigos: [{ nombre: 'cod10_busqueda', archivo: 'index.php', lang: 'php', ...c10 }],
    ejecucion: ['busqueda', 'index'],
    explicacion: [
      'El buscador envía por GET el parámetro <code>q</code>; <code>index.php</code> lo recorta, arma cuatro parámetros <code>LIKE</code> (nombre, apellido, teléfono y correo) y reutiliza el mismo <code>WHERE</code> para el <code>COUNT(*)</code> y para el <code>SELECT</code> paginado.',
      'El término se devuelve escapado con <code>e()</code> y se conserva en los enlaces de paginación mediante <code>urlencode()</code>. La búsqueda opera sobre datos reales de MySQL —no sobre un arreglo en memoria—, lo que se comprueba al buscar el apellido “castro”.',
    ],
    resultado: [
      'La búsqueda cubre nombre, apellido, teléfono y correo.',
      'Se muestran el número de coincidencias y la opción de limpiar el filtro.',
    ],
  });

  return d;
}

/* --------------------------------------------------------------------- HTML */
function pagina(contenido, { clase = '', pie = '' } = {}) {
  return `<section class="hoja ${clase}">${contenido}</section>`;
}

function marcoNavegador(cap, urlMostrada) {
  const u = urlMostrada || cap.url;
  return `<div class="marco">
      <div class="marco__barra">
        <span class="bolita b1"></span><span class="bolita b2"></span><span class="bolita b3"></span>
        <div class="marco__url"><span class="candado">🔒</span>${esc(u)}</div>
      </div>
      <img src="${IMG(cap.archivo)}" alt="Captura de la aplicación">
    </div>`;
}

function marcoConsola(c) {
  return `<div class="marco marco--consola">
      <div class="marco__barra">
        <span class="bolita b1"></span><span class="bolita b2"></span><span class="bolita b3"></span>
        <span class="marco__titulo">Símbolo del sistema · MariaDB / PHP CLI</span>
      </div>
      <div class="terminal">
        <div class="terminal__cmd">C:\\&gt; ${esc(c.comando)}</div>
        <pre>${esc(c.salida)}</pre>
      </div>
    </div>`;
}

function construirHTML(evidencias) {
  const fecha = '24 de noviembre de 2026';

  const portada = pagina(`
    <div class="portada">
      <div class="portada__franja"></div>
      <p class="portada__materia">${esc(MATERIA)} · Unidad 4</p>
      <h1 class="portada__titulo">Agenda de Contactos</h1>
      <p class="portada__subtitulo">Reporte de evidencias del Mini Proyecto 2<br>Desarrollo Web con PHP y Base de Datos (CRUD)</p>

      <div class="portada__datos">
        <div><span>Asignatura</span><strong>${esc(MATERIA)}</strong></div>
        <div><span>Unidad</span><strong>Unidad 4 — Programación del lado del servidor con PHP</strong></div>
        <div><span>Docente</span><strong>${esc(DOCENTE)}</strong></div>
        <div><span>Fecha</span><strong>${fecha}</strong></div>
      </div>

      <table class="tabla-portada">
        <thead><tr><th>#</th><th>Integrante</th><th>Matrícula</th><th>Participación</th></tr></thead>
        <tbody>${INTEGRANTES.map(([n, nom, mat, rol]) => `<tr><td>${n}</td><td>${esc(nom)}</td><td>${esc(mat)}</td><td>${esc(rol)}</td></tr>`).join('')}</tbody>
      </table>

      <div class="portada__enlaces">
        <p><strong>Repositorio:</strong> ${esc(REPO_URL)}</p>
        <p><strong>Sistema publicado:</strong> ${esc(BASE)}/index.php</p>
        <p><strong>Tecnologías:</strong> PHP 8 · MySQL/MariaDB · PDO · HTML5 · CSS3</p>
      </div>
    </div>
  `, { clase: 'hoja--portada' });

  const indice = pagina(`
    <h2 class="titulo-seccion">Contenido del reporte</h2>
    <p class="intro">El documento sigue estrictamente el orden de la lista de verificación del proyecto.
    Cada especificación incluye su captura de código, su captura de ejecución y la explicación técnica.</p>

    <ol class="indice">
      <li><span>•</span> Introducción y arquitectura del sistema</li>
      ${evidencias.map((e) => `<li><span>${e.n}</span> Especificación ${e.n} — ${e.titulo}</li>`).join('')}
      <li><span>•</span> Conclusiones</li>
      <li><span>•</span> Anexos: enlaces e instalación</li>
    </ol>

    <div class="nota">
      <strong>Cómo leer las capturas.</strong> Las imágenes de ejecución se tomaron navegando el sistema real
      conectado a MySQL; el marco superior reproduce la barra de direcciones del navegador con la URL utilizada.
      Las capturas de código corresponden a los archivos del repositorio, con su nombre y el rango de líneas.
    </div>

    <h3 class="subtitulo">Datos del proyecto</h3>
    <table class="tabla tabla--compacta">
      <tbody>
        <tr><th>Repositorio</th><td>${esc(REPO_URL)}</td></tr>
        <tr><th>Sistema publicado</th><td>${esc(BASE)}/index.php</td></tr>
        <tr><th>Base de datos</th><td><code>agenda_contactos</code> · tabla <code>contactos</code></td></tr>
        <tr><th>Total de páginas</th><td>Portada, índice, introducción, 10 especificaciones, conclusiones y anexos</td></tr>
      </tbody>
    </table>
  `);

  const intro = pagina(`
    <h2 class="titulo-seccion">1. Introducción y arquitectura del sistema</h2>

    <h3 class="subtitulo">Descripción del proyecto</h3>
    <p><strong>Agenda de Contactos</strong> es una aplicación web que administra la información de personas
    (nombre, apellido, teléfono, fotografía, correo, dirección y notas). Toda la lógica se ejecuta en el servidor
    con PHP y la información se almacena en MySQL/MariaDB; no se utiliza <code>localStorage</code>, JSON ni
    almacenamiento del lado del cliente.</p>
    <p>El sistema implementa el ciclo CRUD completo sobre una tabla <code>contactos</code>, con búsqueda,
    paginación, subida de imágenes y validaciones en cliente y servidor. Cada operación se ejecuta con
    sentencias preparadas de PDO y toda la salida se escapa con <code>htmlspecialchars()</code>.</p>

    <h3 class="subtitulo">Tecnologías utilizadas</h3>
    <table class="tabla">
      <thead><tr><th>Tecnología</th><th>Uso</th></tr></thead>
      <tbody>
        <tr><td>PHP 8.4</td><td>Lógica del servidor, validaciones, subida de archivos, sesiones y CSRF</td></tr>
        <tr><td>MySQL / MariaDB 11</td><td>Base de datos relacional con la tabla <code>contactos</code></td></tr>
        <tr><td>PDO</td><td>Acceso a datos con sentencias preparadas nativas</td></tr>
        <tr><td>HTML5</td><td>Formularios con validación nativa y estructura semántica</td></tr>
        <tr><td>CSS3</td><td>Hoja de estilos externa (<code>css/styles.css</code>), diseño responsivo</td></tr>
        <tr><td>Apache + .htaccess</td><td>Servidor web y protección de carpetas sensibles</td></tr>
      </tbody>
    </table>

    <h3 class="subtitulo">Estructura de archivos</h3>
    <div class="arbol">
      <pre>/ 
├── index.php          → listado y búsqueda (READ)
├── crear.php          → formulario de alta (CREATE · vista)
├── guardar.php        → procesa el INSERT (CREATE · lógica)
├── editar.php         → formulario de edición (UPDATE · vista)
├── actualizar.php     → procesa el UPDATE (UPDATE · lógica)
├── ver.php            → detalle del contacto (READ individual)
├── eliminar.php       → confirmación y DELETE
├── config/db.php      → conexión PDO a MySQL
├── includes/          → funciones.php · header.php · menu.php · footer.php
├── css/styles.css     → estilos externos
├── archivos/          → fotografías subidas
├── database/agenda.sql→ creación de la tabla y datos de ejemplo
└── README.md          → documentación del repositorio</pre>
    </div>

    <h3 class="subtitulo">Flujo de una operación</h3>
    <p>El usuario envía un formulario por POST → el servidor valida el token CSRF y los datos con
    <code>validar_contacto()</code> → se ejecuta la consulta preparada correspondiente → se guarda un mensaje
    flash en sesión y se redirige (POST → Redirect → GET). Así se evitan reenvíos y se mantiene una navegación
    limpia, con mensajes de éxito o error en cada paso.</p>
  `, { clase: 'hoja--intro' });

  const paginasEvidencias = [];

  const ficha = {
    1: ['Conexión y configuración', 'MySQL / MariaDB · utf8mb4 · pdo_mysql', 'PDO, DSN, try/catch y error_log'],
    2: ['Lectura (SELECT)', 'tabla contactos · 6 columnas · búsqueda y paginación', 'PDO::prepare, bindValue, execute'],
    3: ['Alta (INSERT)', 'tabla contactos · 7 columnas + fotografía', 'INSERT preparado, lastInsertId, move_uploaded_file'],
    4: ['Listado (SELECT)', 'tabla contactos · COUNT(*) + LIMIT/OFFSET', 'SELECT parametrizado, COUNT(*), paginación'],
    5: ['Detalle (SELECT por id)', 'tabla contactos · WHERE id = :id · LIMIT 1', 'SELECT preparado, filter_var, FETCH_ASSOC'],
    6: ['Edición (UPDATE)', 'tabla contactos · 7 columnas + WHERE id = :id', 'UPDATE preparado y reemplazo de archivos'],
    7: ['Baja (DELETE)', 'tabla contactos · WHERE id = :id', 'DELETE preparado, POST + token CSRF'],
    8: ['Validación de entrada', 'formulario HTML5 + reglas de servidor', 'filter_var, preg_match, finfo, required/pattern'],
    9: ['Salida segura', 'todas las vistas y atributos HTML', 'htmlspecialchars con ENT_QUOTES | ENT_SUBSTITUTE'],
    10: ['Búsqueda (LIKE)', 'tabla contactos · 4 columnas con LIKE', 'LIKE parametrizado, COUNT(*), urlencode'],
  };

  evidencias.forEach((e) => {
    // Página de portadilla de la especificación + primera captura de código
    const c = e.codigos[0];
    paginasEvidencias.push(pagina(`
      <div class="evidencia__cabecera">
        <span class="etiqueta">Especificación ${e.n}</span>
        <h2>${esc(e.titulo)}</h2>
        <p class="evidencia__hint">Archivo: <code>${esc(c.archivo)}</code></p>
      </div>
      <p class="rotulo">Captura del código</p>
      <img class="img-codigo" src="${IMG(c.imagen)}" alt="Código de ${esc(c.archivo)}">
      <p class="leyenda">Figura ${e.n}.1 — ${esc(c.leyenda)}</p>
      <div class="nota nota--pequena">El código completo del archivo puede verificarse en el repositorio público:
        <code>${esc(REPO_URL)}</code></div>
    `, { clase: 'hoja--evidencia' }));

    // Capturas de código adicionales
    e.codigos.slice(1).forEach((extra, k) => {
      paginasEvidencias.push(pagina(`
        <div class="evidencia__cabecera evidencia__cabecera--mini">
          <span class="etiqueta">Especificación ${e.n} · continuación</span>
          <h2>${esc(e.titulo)}</h2>
        </div>
        <p class="rotulo">Captura del código</p>
        <img class="img-codigo" src="${IMG(extra.imagen)}" alt="Código de ${esc(extra.archivo)}">
        <p class="leyenda">Figura ${e.n}.${k + 2} — ${esc(extra.leyenda)}</p>
        <div class="nota nota--pequena">El código completo del archivo puede verificarse en el repositorio público:
          <code>${esc(REPO_URL)}</code></div>
      `, { clase: 'hoja--evidencia' }));
    });

    // Capturas de ejecución (una por página)
    let fig = e.codigos.length + 1;
    e.ejecucion.forEach((clave) => {
      if (capturas[clave]) {
        const cap = capturas[clave];
        paginasEvidencias.push(pagina(`
          <div class="evidencia__cabecera evidencia__cabecera--mini">
            <span class="etiqueta">Especificación ${e.n} · ejecución</span>
            <h2>${esc(e.titulo)}</h2>
          </div>
          <p class="rotulo">Captura de ejecución</p>
          ${marcoNavegador(cap)}
          <p class="leyenda">Figura ${e.n}.${fig++} — ${esc(cap.leyenda)}</p>
        `, { clase: 'hoja--evidencia' }));
      } else if (consolas[clave]) {
        const cons = consolas[clave];
        paginasEvidencias.push(pagina(`
          <div class="evidencia__cabecera evidencia__cabecera--mini">
            <span class="etiqueta">Especificación ${e.n} · ejecución</span>
            <h2>${esc(e.titulo)}</h2>
          </div>
          <p class="rotulo">Captura de ejecución en consola</p>
          ${marcoConsola(cons)}
          <p class="leyenda">Figura ${e.n}.${fig++} — ${esc(cons.leyenda)}</p>
        `, { clase: 'hoja--evidencia' }));
      }
    });

    // Explicación técnica
    const f = ficha[e.n] || ['—', '—', '—'];
    paginasEvidencias.push(pagina(`
      <div class="evidencia__cabecera evidencia__cabecera--mini">
        <span class="etiqueta">Especificación ${e.n} · explicación técnica</span>
        <h2>${esc(e.titulo)}</h2>
      </div>
      <table class="tabla tabla--ficha">
        <tbody>
          <tr><th>Operación</th><td>${esc(f[0])}</td></tr>
          <tr><th>Datos implicados</th><td>${esc(f[1])}</td></tr>
          <tr><th>Técnica PHP</th><td>${esc(f[2])}</td></tr>
          <tr><th>Archivo(s)</th><td>${e.codigos.map((x) => `<code>${esc(x.archivo)}</code>`).join(' · ')}</td></tr>
        </tbody>
      </table>
      <div class="explicacion">
        ${e.explicacion.map((p) => `<p>${p}</p>`).join('')}
      </div>
      <div class="resultado">
        <h3>Resultado obtenido</h3>
        <ul>${e.resultado.map((r) => `<li>${r}</li>`).join('')}</ul>
      </div>
    `, { clase: 'hoja--texto' }));
  });

  const conclusiones = pagina(`
    <h2 class="titulo-seccion">11. Conclusiones</h2>
    <p>El desarrollo de la agenda permitió aplicar de forma integral los contenidos de la unidad:
    conexión a MySQL con PDO, consultas preparadas, validación en servidor, manejo de sesiones y
    escape de salida. El resultado es un CRUD funcional en el que cada operación se ejecuta desde PHP.</p>
    <p>El uso de sentencias preparadas con <code>EMULATE_PREPARES = false</code> y el escapado sistemático con
    <code>htmlspecialchars()</code> demostraron ser suficientes para neutralizar los intentos de inyección SQL y de
    XSS probados durante el desarrollo. Separar configuración, lógica y vistas (<code>config/</code>,
    <code>includes/</code>, archivos de página) facilitó mantener el código y repetir el mismo patrón en cada
    operación.</p>
    <p>Como mejora futura se plantea agregar autenticación de usuarios, edición en línea, exportación a CSV y
    respaldo automático de la base de datos.</p>

    <h3 class="subtitulo">Verificación final</h3>
    <table class="tabla">
      <thead><tr><th>Requisito</th><th>Estado</th></tr></thead>
      <tbody>
        <tr><td>Conexión real a MySQL con PDO</td><td class="ok">Cumplido</td></tr>
        <tr><td>Sentencias preparadas en todas las consultas</td><td class="ok">Cumplido</td></tr>
        <tr><td>CRUD completo (INSERT, SELECT, UPDATE, DELETE)</td><td class="ok">Cumplido</td></tr>
        <tr><td>Validación en cliente y servidor</td><td class="ok">Cumplido</td></tr>
        <tr><td>Escapado de salida con htmlspecialchars()</td><td class="ok">Cumplido</td></tr>
        <tr><td>Búsqueda de contactos</td><td class="ok">Cumplido</td></tr>
        <tr><td>Repositorio público y sistema publicado</td><td class="ok">Cumplido</td></tr>
      </tbody>
    </table>
  `);

  const anexos = pagina(`
    <h2 class="titulo-seccion">12. Anexos</h2>

    <h3 class="subtitulo">Enlaces de entrega</h3>
    <table class="tabla">
      <tbody>
        <tr><th>Repositorio GitHub</th><td>${esc(REPO_URL)}</td></tr>
        <tr><th>Sistema publicado</th><td>${esc(BASE)}/index.php</td></tr>
        <tr><th>Reporte PDF</th><td><code>docs/Reporte_Evidencias_Agenda.pdf</code></td></tr>
      </tbody>
    </table>

    <h3 class="subtitulo">Instalación local</h3>
    <ol class="lista">
      <li>Copiar el proyecto en <code>htdocs/</code> (XAMPP) o <code>www/</code> (Laragon).</li>
      <li>Importar <code>database/agenda.sql</code> en MySQL o desde phpMyAdmin.</li>
      <li>Ajustar las credenciales en <code>config/db.php</code>.</li>
      <li>Abrir <code>http://localhost/agenda-contactos/</code>.</li>
    </ol>

    <h3 class="subtitulo">Publicación en hosting</h3>
    <ol class="lista">
      <li>Crear la base de datos y el usuario MySQL desde el panel del hosting.</li>
      <li>Importar <code>database/agenda.sql</code> con phpMyAdmin.</li>
      <li>Subir los archivos por FTP a <code>htdocs/</code> con permisos de escritura en <code>archivos/</code>.</li>
      <li>Editar <code>config/db.php</code> con el host, la base y el usuario del hosting.</li>
    </ol>

    <h3 class="subtitulo">Pruebas automatizadas</h3>
    <p>El repositorio incluye <code>tools/pruebas_crud.py</code>, que ejecuta 36 verificaciones sobre el sistema
    en ejecución: listado, búsqueda, inyección SQL, XSS, CSRF, validación, alta con fotografía, detalle,
    actualización y eliminación con borrado del archivo de imagen.</p>

    <div class="nota">
      <strong>Nota sobre las capturas.</strong> Todas las imágenes de ejecución provienen del sistema en
      funcionamiento con la base de datos MySQL real; las capturas de código se generaron directamente desde los
      archivos del repositorio.
    </div>
  `);

  return `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">
<title>Reporte de evidencias · Agenda de Contactos</title>
<style>
  @page { size: A4; margin: 18mm 16mm 20mm 16mm; }
  * { box-sizing: border-box; }
  html, body { margin:0; padding:0; }
  body { font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif; font-size: 10.5pt; color:#1b1d29; line-height:1.55; }
  .hoja { page-break-after: always; break-after: page; }
  .hoja:last-child { page-break-after: auto; break-after: auto; }
  h1, h2, h3 { margin:0; letter-spacing:-.01em; }
  code { font-family: "Cascadia Mono", Consolas, monospace; font-size: 9.3pt; background:#f1f3fa; padding:1px 4px; border-radius:4px; }

  /* ---------- Portada ---------- */
  .portada { position:relative; }
  .portada__franja { height: 10mm; background: linear-gradient(90deg,#4f46e5,#8b5cf6); border-radius: 3mm; margin-bottom: 26mm; }
  .portada__materia { text-transform: uppercase; letter-spacing:.16em; font-size:9.5pt; color:#4f46e5; font-weight:700; margin:0 0 4mm; }
  .portada__titulo { font-size: 34pt; line-height:1.05; margin:0 0 5mm; color:#11132a; }
  .portada__subtitulo { font-size: 12.5pt; color:#4a5070; margin:0 0 14mm; line-height:1.5; }
  .portada__datos { display:grid; grid-template-columns: 1fr 1fr; gap: 4mm 8mm; margin-bottom: 10mm; }
  .portada__datos span { display:block; font-size:8.5pt; text-transform:uppercase; letter-spacing:.1em; color:#7b81a0; font-weight:700; margin-bottom:1mm; }
  .portada__datos strong { font-size:10.5pt; font-weight:600; }
  .tabla-portada { width:100%; border-collapse:collapse; font-size:9.5pt; }
  .tabla-portada th { text-align:left; background:#eef0fb; color:#3c4270; font-size:8.5pt; text-transform:uppercase; letter-spacing:.08em; padding:2.5mm 3mm; }
  .tabla-portada td { padding:2.5mm 3mm; border-bottom:1px solid #e6e8f3; }
  .portada__enlaces { margin-top: 10mm; padding-top: 5mm; border-top:1px solid #e6e8f3; font-size:9.5pt; color:#4a5070; }
  .portada__enlaces p { margin: 0 0 1.5mm; }

  /* ---------- Secciones ---------- */
  .titulo-seccion { font-size: 19pt; margin-bottom: 4mm; color:#11132a; }
  .subtitulo { font-size: 12pt; margin: 7mm 0 2.5mm; color:#2a2f52; }
  .intro { color:#4a5070; margin-bottom: 6mm; }

  .indice { list-style:none; padding:0; margin: 0 0 8mm; }
  .indice li { display:flex; gap:4mm; align-items:baseline; padding: 2.4mm 0; border-bottom:1px dotted #d8dbeb; font-size:10.2pt; }
  .indice li span { flex:0 0 8mm; text-align:center; font-weight:700; color:#4f46e5; font-size:9pt; }

  .tabla { width:100%; border-collapse:collapse; font-size:9.4pt; margin-bottom: 5mm; }
  .tabla th { text-align:left; background:#f2f4fb; padding:2.2mm 3mm; font-size:8.4pt; text-transform:uppercase; letter-spacing:.07em; color:#3c4270; }
  .tabla td { padding:2.2mm 3mm; border-bottom:1px solid #e8eaf4; vertical-align:top; }
  .tabla--compacta th { width: 42mm; }
  .tabla--ficha { margin: 0 0 6mm; font-size: 9pt; }
  .tabla--ficha th { width: 38mm; text-transform: none; letter-spacing:0; font-size:9pt; color:#4a5070; background:#f6f7fd; }
  .tabla--ficha td { font-size: 9.4pt; }
  .ok { color:#047857; font-weight:700; }

  /* Introducción compacta para que quepa en una sola página */
  .hoja--intro { font-size: 9.9pt; }
  .hoja--intro .subtitulo { margin: 4.6mm 0 2mm; font-size: 11pt; }
  .hoja--intro .tabla { font-size: 8.9pt; margin-bottom: 3mm; }
  .hoja--intro .tabla td, .hoja--intro .tabla th { padding: 1.6mm 2.6mm; }
  .hoja--intro .arbol { font-size: 8.1pt; padding: 3.2mm 4mm; }
  .hoja--intro p { margin-bottom: 2.4mm; }

  .nota { background:#f5f6fd; border-left: 1mm solid #4f46e5; padding: 3.5mm 4.5mm; font-size:9.3pt; color:#3c4270; border-radius: 0 2mm 2mm 0; margin-top: 6mm; }
  .nota--pequena { margin-top: 8mm; font-size:8.8pt; }

  .arbol { background:#0f1526; color:#dbe3f4; border-radius:2.5mm; padding: 4.5mm 5mm; font-size:8.5pt; }
  .arbol pre { margin:0; font-family:"Cascadia Mono", Consolas, monospace; line-height:1.5; white-space:pre; }

  .lista { margin: 0 0 5mm; padding-left: 5mm; }
  .lista li { margin-bottom: 1.6mm; }

  /* ---------- Evidencias ---------- */
  .evidencia__cabecera { border-bottom: 2px solid #4f46e5; padding-bottom: 3mm; margin-bottom: 5mm; }
  .evidencia__cabecera .etiqueta { display:inline-block; background:#4f46e5; color:#fff; font-size:8pt; font-weight:700;
    letter-spacing:.12em; text-transform:uppercase; padding:1.2mm 3mm; border-radius: 999px; margin-bottom: 2.5mm; }
  .evidencia__cabecera h2 { font-size: 16pt; color:#11132a; }
  .evidencia__cabecera--mini h2 { font-size: 13.5pt; }
  .evidencia__hint { margin: 1.5mm 0 0; font-size:9pt; color:#6b7290; }

  .rotulo { font-size:8.5pt; font-weight:700; text-transform:uppercase; letter-spacing:.12em; color:#8a90ad; margin: 0 0 2mm; }
  .img-codigo { width:100%; max-height: 218mm; object-fit: contain; object-position: top center; border-radius: 2mm;
    box-shadow: 0 2mm 5mm rgba(16,20,40,.16); display:block; }
  .leyenda { font-size:8.6pt; color:#6b7290; margin: 3mm 0 0; font-style: italic; }

  .marco { border-radius: 2.5mm; overflow:hidden; box-shadow: 0 2mm 6mm rgba(16,20,40,.16); border:1px solid #dfe3f0; }
  .marco__barra { display:flex; align-items:center; gap:2mm; background:#eef0f7; padding: 2.2mm 3mm; border-bottom:1px solid #dfe3f0; }
  .bolita { width:2.6mm; height:2.6mm; border-radius:50%; }
  .b1 { background:#ef4444 } .b2 { background:#f59e0b } .b3 { background:#22c55e }
  .marco__url { margin-left: 3mm; flex:1; background:#fff; border:1px solid #d8dcec; border-radius:999px;
    padding: 1.2mm 3mm; font-size:8.4pt; color:#4a5070; display:flex; gap:1.5mm; align-items:center; overflow:hidden; white-space:nowrap; }
  .marco__titulo { margin-left: 3mm; font-size:8.4pt; color:#4a5070; font-weight:600; }
  .marco img { display:block; width:100%; }
  .marco--consola .terminal { background:#0b1020; padding: 4mm; font-family:"Cascadia Mono", Consolas, monospace; font-size:8.6pt; color:#d7e0f5; }
  .terminal__cmd { color:#7dd3fc; margin-bottom: 3mm; }
  .terminal pre { margin:0; white-space:pre-wrap; line-height:1.45; }

  .explicacion p { margin: 0 0 4.5mm; text-align: justify; font-size: 10.6pt; line-height:1.65; }
  .resultado { background:#f7f8fd; border:1px solid #e6e8f4; border-radius: 2.5mm; padding: 4.5mm 5mm; }
  .resultado h3 { font-size: 10.5pt; margin-bottom: 2.5mm; color:#2a2f52; }
  .resultado ul { margin:0; padding-left: 5mm; }
  .resultado li { margin-bottom: 1.6mm; font-size: 9.8pt; }
</style></head><body>
${portada}${indice}${intro}${paginasEvidencias.join('')}${conclusiones}${anexos}
</body></html>`;
}

/* ---------------------------------------------------------------------- PDF */
async function main() {
  console.log('1/4 · Capturando la aplicación en ejecución…');
  await capturarNavegador();

  console.log('2/4 · Capturando salidas de consola…');
  await capturarConsolas();

  console.log('3/4 · Capturando fragmentos de código…');
  highlighter = await createHighlighter({
    themes: ['github-dark'],
    langs: ['php', 'sql', 'html', 'css'],
  });

  const browser = await puppeteer.launch({
    executablePath: EDGE,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu'],
  });
  const fotoCodigo = await capturarCodigo(browser);

  const evidencias = definiciones();
  for (const e of evidencias) {
    for (const c of e.codigos) {
      const r = await fotoCodigo(c.nombre, c.etiqueta || c.archivo, c.lang, c.codigo, c.desde, c.hasta);
      c.imagen = r.archivo;
      c.leyenda = r.leyenda;
    }
  }

  console.log('4/4 · Componiendo el PDF…');
  const html = construirHTML(evidencias);
  const rutaHtml = path.join(TMP, 'reporte.html');
  fs.writeFileSync(rutaHtml, html, 'utf8');

  const page = await browser.newPage();
  await page.goto('file:///' + rutaHtml.replace(/\\/g, '/'), { waitUntil: 'networkidle0' });
  const pdf = path.join(OUT, 'Reporte_Evidencias_Agenda.pdf');
  await page.pdf({
    path: pdf,
    format: 'A4',
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: `<div style="width:100%;font-size:7.6pt;color:#8a90ad;padding:0 16mm;display:flex;justify-content:space-between;font-family:Segoe UI,Arial;">
      <span>Agenda de Contactos · Mini Proyecto 2 · Unidad 4</span>
      <span style="white-space:nowrap">Página <span class="pageNumber"></span> de <span class="totalPages"></span></span>
    </div>`,
    margin: { top: '14mm', right: '16mm', bottom: '16mm', left: '16mm' },
  });

  await browser.close();
  const kb = Math.round(fs.statSync(pdf).size / 1024);
  console.log(`PDF generado: ${pdf} (${kb} KB)`);
}

main().catch((e) => { console.error(e); process.exit(1); });
