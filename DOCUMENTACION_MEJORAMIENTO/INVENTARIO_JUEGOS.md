# Inventario de los juegos (Fase 13)

Solo documentación: **no se borró ni se movió ningún archivo** en esta fase. Medido sobre `laravel/public/juegos/` tras migrar a Blade las 7 páginas (los `index.html` ya no existen; cada juego añade `estilo-pagina.css` y `sopa_letras` además `juego.js`). Los tamaños son bytes reales de los archivos (MB = 1.048.576 B).

## 1. Por juego

Todos los juegos cargan además `juegos/css/terminar_juego.css`, `js/explora-api.js`, `juegos/js/terminar_juego.js` y Font Awesome (`juegos/librerias/fontawesome`); todos menos `arrastra` cargan `juegos/css/pantalla_completa.css`, y todos `juegos/js/pantalla_completa.js`.

| Juego (`juegos/juegos/…`) | Archivos | Tamaño | Bootstrap | jQuery | SweetAlert2 | Otras librerías propias |
|---|---:|---:|---|---|---|---|
| `laberinto` | 35 | 6,60 MB | 5.3.5 compartido (`juegos/librerias/bootstrap-5.3`) | 3.3.1 (`assets/bootstrap/jquery.min.js`) | 11.7.1 (`assets/bootstrap/sweetalert.js`) | fuente DS-Digital (`ds_digital/`) |
| `banderas-color` | 69 | 2,44 MB | **4.6.2 propio** (`bootstrap/bootstrap.min.css` + `bootstrap.bundle.min.js`) | **3.7.1 propio** (`jquery.min.js`) | **11.17.2 propio** (`assets/sweetalert/sweetalert.js`) | `bootstrap/popper.min.js` (Popper 1.x, Zivolo 2020) |
| `puzzle` | 130 | 68,60 MB | 5.3.5 compartido | 3.3.1 compartido | 11.7.1 compartido | `popper.min.js` (idéntico al de banderas), `jquery-ui.min.js` 1.12.1 |
| `sopa_letras` | 20 | 2,75 MB | 5.3.5 compartido | 3.3.1 compartido | 11.7.1 compartido | `tag-it.js` v2.0 (2011), `wordfind.js`, `wordfindgame.js` |
| `juego_ahorcado` | 22 | 1,93 MB | 5.3.5 compartido | 3.3.1 compartido | 11.7.1 compartido | — |
| `arrastra` | 26 | 10,70 MB | 5.3.5 compartido | 3.3.1 compartido | 11.7.1 compartido | `panzoom.min.js` 4.6.0 |
| `completa` | 17 | 1,40 MB | 5.3.5 compartido | 3.3.1 compartido | 11.7.1 compartido | fuente `TitanOne` (`completa/fuente/`) |

Carpetas compartidas por los juegos (no son páginas): `juegos/assets` (10 archivos, 21,34 MB: `assets/bootstrap/{jquery.min.js,sweetalert.js}` y `assets/images/*.webp`), `juegos/sounds` (10 archivos, 9,07 MB) y `juegos/mapas` (15 archivos, 0,28 MB; solo imágenes `gris/` y `marron/` que usan 6 juegos: **no es una página**). `banderas-color` NO tiene 2.217 archivos: tiene **69** (la cifra era anterior a la Fase 7B).

## 2. Archivos sin uso (confirmados con evidencia)

Método: `grep -rIl -e "<nombre>" public/juegos resources app routes tests` (sin `node_modules`/`vendor`), y, para rutas que el JS podría construir, lectura del código que las genera. «Sin uso» = ninguna coincidencia fuera del propio archivo.

| Archivo | Tamaño | Evidencia |
|---|---:|---|
| `banderas-color/assets/sweetalert/sweetalert2.all.min.js` | 74.925 B | La página carga `…/assets/sweetalert/sweetalert.js`; el grep del nombre `sweetalert2.all` solo coincide con `perfil`, `portal` y `layouts/sitio` (que usan la copia de `public/sweetalert/`). Es byte a byte igual (`md5` aca8f942) a `banderas-color/assets/sweetalert/sweetalert.js` y a `public/sweetalert/sweetalert2.all.min.js`. |
| `banderas-color/assets/sweetalert/sweetalert2.min.css` | 26.306 B | Ninguna referencia (`sweetalert2.min.css` solo aparece en `perfil` y `layouts/sitio`, apuntando a `public/sweetalert/`). Igual (`md5` 83b54381) a `public/sweetalert/sweetalert2.min.css`. |
| `banderas-color/image` | 0 B | Archivo vacío sin extensión; ninguna referencia a `banderas-color/image`. |
| `banderas-color/assets/pincel.png` | 2.247 B | El grep de `pincel` solo encuentra `script.js`, que usa `pincel.cur` (cursor), no el `.png`. |
| `laberinto/ds_digital/DS-DIGIB.TTF`, `DS-DIGII.TTF`, `DS-DIGIT.TTF` | 24.896 + 24.676 + 25.480 B | `css/css2.css` declara un único `@font-face 'DS-Digital'` con `../ds_digital/DS-DIGI.TTF`; las otras variantes (negrita, cursiva, «T») no se citan en ninguna parte. |
| `laberinto/ds_digital/DIGITAL.TXT` | 1.654 B | Texto de licencia de la fuente; no se carga. |
| `laberinto/css/Recurso_2.png`, `laberinto/css/Recurso 7.png` | 19.517 + 6.217 B | Sin referencias (la página usa `Recurso_1.png` y `Recurso_3.png`). |
| `juego_ahorcado/img/{verbos,narracion,medios}.png` | 83.112 + 120.326 + 53.636 B | Sin referencias; `ahorcado.js` solo construye `img/nivel_N.png`, `ahorcado_N.png`, etc., y las categorías de `pistas.json` (mapa_politico, etapas_bandera_colombia, parques_nacionales…) no coinciden con esos nombres. |
| `arrastra/img/card.png` | 28.772 B | Sin referencias (`data.js` solo usa `etnias/capitales/parques/colombia.webp`). |
| `sopa_letras/wordfind-master/` (5 archivos, 48 KB: `Readme.md`, `example/index.html`, `example/scripts/wordfindgame.js`, `example/styles/style.css`, `src/wordfind.js`) | ~48 KB | Ninguna referencia a la ruta `wordfind-master`: es la copia de ejemplo de la librería; el juego usa su propio `sopa_letras/wordfind.js` (que ya difiere del de `src/`). **Ojo:** `example/index.html` sigue siendo una página estática accesible por URL. |

Descartado a propósito: `puzzle/etnias/37-40.png` y `puzzle/geografia/37-41.png` NO son huérfanos aunque su nombre no aparezca escrito: `puzzle/script.js` forma la ruta con `categoria.id + "/" + aleatorio(1..categoria.imagenes) + ".png"` y `categorias.json` declara 40 y 41 imágenes.

## 3. Referencias rotas heredadas (solo reportadas)

- `laberinto`: `css/perdio_gano.png` (en el HTML, `#perdio_gano`) no existe; el JS lo reemplaza al jugar (`recursos.spec.js` ya lo tolera).
- `sopa_letras/style.css`, línea 19: `url(../portadas/sopa_letras.png)` apunta a `juegos/juegos/portadas/`, que no existe.
- El «volver» `../../index.html` de los juegos apunta a `/juegos/index.html`, que ya no es un archivo: lo redirige Laravel (301) a `/juegos`.

`tests/Feature/JuegosRutasTest` exige que estas dos sean EXACTAMENTE las únicas rutas literales rotas.

## 4. Copias duplicadas de jQuery, SweetAlert y Bootstrap

| Biblioteca | Versión | Ruta | Bytes | Quién la usa |
|---|---|---|---:|---|
| jQuery | 3.3.1 | `juegos/juegos/assets/bootstrap/jquery.min.js` | 86.927 | 6 juegos (todos menos banderas) |
| jQuery | 3.7.1 | `juegos/juegos/banderas-color/jquery.min.js` | 87.533 | banderas-color |
| jQuery | 3.5.1 | `bootstrap/jquery.min.js` (raíz) | 89.476 | portada, mapas (`layouts/sitio`) y perfil |
| SweetAlert2 | 11.7.1 | `juegos/juegos/assets/bootstrap/sweetalert.js` | 64.348 | 6 juegos |
| SweetAlert2 | 11.17.2 | `juegos/juegos/banderas-color/assets/sweetalert/sweetalert.js` | 74.925 | banderas-color |
| SweetAlert2 | 11.17.2 | `juegos/juegos/banderas-color/assets/sweetalert/sweetalert2.all.min.js` | 74.925 | **nadie** (copia exacta de la anterior) |
| SweetAlert2 | 11.17.2 | `sweetalert/sweetalert2.all.min.js` (raíz) | 74.925 | portal, perfil y mapas (copia exacta de las dos anteriores) |
| SweetAlert2 (CSS) | 11.17.2 | `sweetalert/sweetalert2.min.css` y `banderas-color/assets/sweetalert/sweetalert2.min.css` | 26.306 ×2 | la raíz; la de banderas, nadie |
| Bootstrap | 5.3.5 | `juegos/librerias/bootstrap-5.3/` (44 archivos, 8,3 MB: incluye `.rtl`, `grid`, `reboot`, `utilities`, `.esm` y sin minificar; las páginas solo cargan `bootstrap.min.css` y `bootstrap.bundle.min.js`, 231.913 y 80.721 B) | — | portal y 6 juegos |
| Bootstrap | 4.6.2 | `juegos/juegos/banderas-color/bootstrap/{bootstrap.min.css 162.264, bootstrap.bundle.min.js 83.376}` | — | banderas-color |
| Bootstrap | 4.5.2 | `bootstrap/{bootstrap.min.css 160.302, bootstrap.min.js 60.044}` (raíz) | — | páginas del mapa |
| Popper 1.x | 2020 | `juegos/puzzle/popper.min.js` y `juegos/banderas-color/bootstrap/popper.min.js` | 21.233 ×2 | puzzle; banderas (que ya trae Popper dentro de `bootstrap.bundle.min.js`) |

**Recomendación (NO ejecutada; para una fase posterior, p. ej. la 7B pendiente):**
1. Un único jQuery y un único SweetAlert2 (el más reciente, 3.7.1 y 11.17.2) en una carpeta compartida (p. ej. `public/librerias/`), usados por los juegos, el portal, el perfil y los mapas; probar cada juego, porque pasar de jQuery 3.3.1 a 3.7.1 y de SweetAlert 11.7.1 a 11.17 puede cambiar detalles (y los estilos `swal2-*` que sobrescriben `estilo-pagina.css`).
2. Pasar `banderas-color` al Bootstrap 5.3 compartido (migrar su marcado de BS4: `data-dismiss`→`data-bs-dismiss`, `ml-/mr-`→`ms-/me-`, etc.) y retirar su Bootstrap 4.6.2, su jQuery y su Popper; es la única razón de que banderas lleve librerías propias.
3. Dejar en `librerias/bootstrap-5.3` solo los archivos usados (hoy 2 de 44) o servirlos desde Vite.
4. Borrar las copias sin uso de la sección 2 (aprox. 0,5 MB) y `wordfind-master/`.
5. Comprimir las imágenes grandes (sección 5): `correcto/incorrecto/victoria.webp` (6,2 + 6,1 + 4,4 MB) y los `arrastra/img/*.webp` (3,2 + 3,0 + 2,8 MB) son muy superiores a lo razonable para una página escolar.

## 5. Los 10 archivos más pesados (`public/juegos`)

| # | Archivo | Bytes |
|--:|---|---:|
| 1 | `juegos/assets/images/incorrecto.webp` | 6.186.476 |
| 2 | `juegos/assets/images/correcto.webp` | 6.123.974 |
| 3 | `juegos/sounds/fondo.mp3` | 5.011.330 |
| 4 | `juegos/assets/images/victoria.webp` | 4.379.968 |
| 5 | `juegos/arrastra/img/etnias.webp` | 3.217.540 |
| 6 | `juegos/arrastra/img/colombia.webp` | 3.009.526 |
| 7 | `juegos/arrastra/img/parques.webp` | 2.831.120 |
| 8 | `juegos/sopa_letras/images/fondo.png` | 2.346.622 |
| 9 | `juegos/laberinto/css/nave2.webp` | 2.089.762 |
| 10 | `juegos/assets/images/pensando.webp` | 2.045.340 |

Por carpeta, el mayor peso es `puzzle` (130 archivos, 68,6 MB: `etnias/`, `geografia/` y `fauna_flora/` con ~41 PNG de 0,1 a 0,7 MB cada una).
