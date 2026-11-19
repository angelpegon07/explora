# Graph Report - explora  (2026-11-19)

## Corpus Check
- Large corpus: 861 files · ~500,527 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 619 nodes · 898 edges · 44 communities (29 shown, 15 thin omitted)
- Extraction: 95% EXTRACTED · 5% INFERRED · 0% AMBIGUOUS · INFERRED: 42 edges (avg confidence: 0.82)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Resultados y pantalla completa de juegos
- Menú circular y portal de juegos
- Mapplic genérico (mapas temáticos)
- Mapplic principal
- Página de cordilleras
- Juego Completa
- Juego Laberinto
- Juego Ahorcado
- Juego de nubes/categorías
- Página climático
- Página principal (atlas)
- Estilos y librerías del atlas
- Cliente API (explora-api)
- Quiz del departamento
- Juego Banderas
- Línea de tiempo
- ViajaPED (épocas)
- Página línea de tiempo
- Mi perfil
- Juego Puzzle
- Inicio de sesión (cliente)
- Chat (cliente)
- Sopa de letras (wordfind)
- Chat legado PHP (copia)
- Dependencias React
- Tutorial guiado
- Ver más (fichas)
- Utilidad unir JSON
- Corrector de texto
- Nubes
- Datos ViajaPED

## God Nodes (most connected - your core abstractions)
1. `Mapa Climatico de Colombia` - 41 edges
2. `Parques Naturales de Colombia` - 41 edges
3. `Explora Colombia y aprende (main atlas)` - 40 edges
4. `Laberinto Espacial game` - 22 edges
5. `Sopa de letras game` - 22 edges
6. `La Bandera de Colombia game` - 16 edges
7. `Juegos Educativos Explora (portal de juegos)` - 15 edges
8. `Mapa de Coordilleras de Colombia (sistema montanoso)` - 14 edges
9. `Etnias de Colombia (mapa)` - 14 edges
10. `Hidrografia de Colombia (vertientes)` - 14 edges

## Surprising Connections (you probably didn't know these)
- `ViajaPED - Epocas Historicas (Viaja y explora el tiempo)` --semantically_similar_to--> `Linea de Tiempo Historica de Colombia`  [INFERRED] [semantically similar]
  ViajaPED/index.html → lineaTiempo/index.html
- `Menu circular flotante (atlas, juegos, test, chat, linea de tiempo, ViajaPED)` --references--> `Juegos Educativos Explora (portal de juegos)`  [EXTRACTED]
  coordilleras.html → juegos/index.html
- `Menu circular flotante` --references--> `Juegos Educativos Explora (portal de juegos)`  [EXTRACTED]
  etnias.html → juegos/index.html
- `Menu circular flotante` --references--> `Juegos Educativos Explora (portal de juegos)`  [EXTRACTED]
  vertientes.html → juegos/index.html
- `Seccion Mi Historial (juego, categoria, nivel, puntaje, tiempo, fecha, hora)` --conceptually_related_to--> `Juegos Educativos Explora (portal de juegos)`  [INFERRED]
  miPerfil.html → juegos/index.html

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Juegos que comparten terminar_juego.js, pantalla_completa.js, explora-api.js y modal de ranking** — juegos_juegos_arrastra_index, juegos_juegos_completa_index, juegos_juegos_juego_ahorcado_index, juegos_juegos_puzzle_index [EXTRACTED 1.00]
- **Paginas de mapa con plantilla Mapplic y mismos modulos (chat, quiz, menu circular, accesibilidad)** — coordilleras, etnias, vertientes [EXTRACTED 1.00]
- **Paginas de exploracion historica enlazadas desde el menu circular del atlas** — viajaped_index, lineatiempo_index [INFERRED 0.75]
- **Games sharing result-saving and fullscreen scripts** — juegos_juegos_banderas_color_index, juegos_juegos_laberinto_index, juegos_juegos_sopa_letras_index, juegos_js_terminar_juego, juegos_js_pantalla_completa [INFERRED 0.85]
- **Pages sharing the Mapplic map template** — index, climatico, parques_naturales [INFERRED 0.95]

## Communities (44 total, 15 thin omitted)

### Community 0 - "Resultados y pantalla completa de juegos"
Cohesion: 0.05
Nodes (50): pantalla_completa.css, terminar_juego.css, avisar_jugador(), bloquear_avatares(), cerrar_modal_guardar_resultado(), consultar_ranking(), crear_elemento(), guardar_resultado() (+42 more)

### Community 1 - "Menú circular y portal de juegos"
Cohesion: 0.05
Nodes (48): ViajaPED page, Circular floating menu (atlas, juegos, test, chat, linea de tiempo, ViajaPED), Menu circular flotante, Circular floating menu (atlas, juegos, test, chat, linea de tiempo, ViajaPED), Juegos Educativos Explora (portal de juegos), Juego Conoce las banderas (banderas-color), Nubes animadas de fondo (clouds.css/clouds.js), js/explora-api.js (API cliente) (+40 more)

### Community 2 - "Mapplic genérico (mapas temáticos)"
Cohesion: 0.05
Nodes (10): ClearButton(), Deeplinking(), DeeplinkingHash(), DevTools(), FullScreen(), HoverTooltip(), Minimap(), Sidebar() (+2 more)

### Community 3 - "Mapplic principal"
Cohesion: 0.05
Nodes (10): ClearButton(), Deeplinking(), DeeplinkingHash(), DevTools(), FullScreen(), HoverTooltip(), Minimap(), Sidebar() (+2 more)

### Community 4 - "Página de cordilleras"
Cohesion: 0.05
Nodes (43): Mapa de Coordilleras de Colombia (sistema montanoso), Menu de accesibilidad (texto, dislexia, tutorial), Modal de seleccion de tipo de mapa (general, hidrico, montanoso, climatico, parques, etnico), Widget de chat, Panel CONTENIDO (acordeon: sitios de interes, festividades, etnias), driver.js y tutorial.js (tour guiado), js/explora-api.js (API cliente), Modal de recomendacion de pantalla completa (+35 more)

### Community 5 - "Juego Completa"
Cohesion: 0.08
Nodes (37): actualizarContadorPreguntas(), btnActualizar, btnReiniciar, btnSiguiente, btnVolver, cargarCategorias(), cargarNiveles(), categoriasContainer (+29 more)

### Community 6 - "Juego Laberinto"
Cohesion: 0.11
Nodes (33): actualizarMapa(), actualizarMapadragones(), BuscarCaminoMasCercanoAJugador(), cargarCategorias(), createMap(), desordenar_opciones(), detener_contador_juego(), dragonPositions (+25 more)

### Community 7 - "Juego Ahorcado"
Cohesion: 0.17
Nodes (19): acentos, cargar_categorias(), cargarPalabras(), cerrar_anuncio(), compruebaFin(), elegir(), generaABC(), generaPalabra() (+11 more)

### Community 8 - "Juego de nubes/categorías"
Cohesion: 0.13
Nodes (15): categoriesGrid, categoryModal, categoryTitle, elem, gameArea, iniciar_contador_juego(), iniciarContador(), initializeGame() (+7 more)

### Community 9 - "Página climático"
Cohesion: 0.11
Nodes (18): Bootstrap, Bootstrap JS, Mapa Climatico de Colombia, Data source climatico.json, Dato de interes info panel about climate regions, Mapplic interactive map, Quiz unavailable notice: only for general map, Accessibility menu (font size, dyslexia mode, tutorial) (+10 more)

### Community 10 - "Página principal (atlas)"
Cohesion: 0.11
Nodes (18): jQuery, css/chat.css, css/utilidad.css, Driver.js tour library, Explora Colombia y aprende (main atlas), Data source departamentos.json, Mapplic interactive map, Accessibility menu (font size, dyslexia mode, tutorial) (+10 more)

### Community 11 - "Estilos y librerías del atlas"
Cohesion: 0.11
Nodes (18): css/style.css, Font Awesome, jquery.mousewheel, mapplic/js/iniciarSesion.js (login), mapplic/quiz.css, Parques Naturales de Colombia, Data source parques_naturales.json, Park image gallery from maps_parques/imagenes_parques (+10 more)

### Community 12 - "Cliente API (explora-api)"
Cohesion: 0.20
Nodes (12): conAlmacenamiento(), guardarSesionLocal(), hayJQuery(), leerCookie(), limpiarSesionLocal(), mensajeDelCuerpo(), mensajeError(), peticion() (+4 more)

### Community 13 - "Quiz del departamento"
Cohesion: 0.24
Nodes (16): Quiz test overlay with timer and 10 questions, Quiz test overlay with timer and 10 questions, abortarTest(), actualizarNumeroPregunta(), buscarPreguntas(), cerrarTest(), comenzarTest(), iniciarContador() (+8 more)

### Community 14 - "Juego Banderas"
Cohesion: 0.25
Nodes (13): cargarBanderas(), cargarBanderasAdivinar(), cargarColores(), desordenarBanderas(), iniciar_contador_juego(), iniciarJuego(), iniciarTimer(), MapearBanderas() (+5 more)

### Community 15 - "Línea de tiempo"
Cohesion: 0.22
Nodes (15): abrirModal(), cargarDatos(), cerrarModal(), crearEventoHTML(), crearPeriodoHTML(), eventosData, generarMenuNavegacion(), inicializarAnimaciones() (+7 more)

### Community 16 - "ViajaPED (épocas)"
Cohesion: 0.23
Nodes (9): cargarElementos(), getRandomPosition(), interactuarConElementosSVG(), setRandomTarget(), setTarget(), showEraDetails(), showTimeTunnel(), updateFontSize() (+1 more)

### Community 17 - "Página línea de tiempo"
Cohesion: 0.19
Nodes (13): Menu circular flotante (atlas, juegos, test, chat, linea de tiempo, ViajaPED), Linea de Tiempo Historica de Colombia, GSAP y ScrollTrigger (animacion por scroll), Modal de informacion detallada, Boton buho y menu de navegacion, boton ir arriba, lineaTiempo script.js, Linea de tiempo cargada dinamicamente, ViajaPED - Epocas Historicas (Viaja y explora el tiempo) (+5 more)

### Community 18 - "Mi perfil"
Cohesion: 0.26
Nodes (9): cargarHistorial(), cerrarModalAvatar(), consultarHistorial(), crearCelda(), formatearHoraDoce(), mostrarAvisoSinSesion(), mostrarModalAvatar(), segundos_a_tiempo() (+1 more)

### Community 19 - "Juego Puzzle"
Cohesion: 0.29
Nodes (10): cargar_categorias(), checkPuzzle(), createPuzzle(), elegir(), formatTime(), iniciarTiempo(), reproducir_audio(), reproducir_audio_loop() (+2 more)

### Community 20 - "Inicio de sesión (cliente)"
Cohesion: 0.32
Nodes (9): cargar_opciones_accesibilidad(), cerrarModalIniciarSesion(), cerrarSesion(), esPaginaRaiz(), iniciarSesion(), mostrarModalIniciarSesion(), refrescarInterfazSesion(), toggleMenuAccesibilidadSeguro() (+1 more)

### Community 21 - "Chat (cliente)"
Cohesion: 0.27
Nodes (7): Chat widget, Chat widget, buscarRespuesta(), mostrarChat(), pensando(), sendMessage(), Chat widget

### Community 22 - "Sopa de letras (wordfind)"
Cohesion: 0.22
Nodes (8): Word Find - pagina de ejemplo, wordfind.js, wordfindgame.js, jQuery 1.7.1 (Google CDN), style.css, Boton Solve Puzzle (wordfindgame.solve), Palabras de ejemplo: Develoteca, cursos, ayuda, Videos, Tutoriales, Plugins, Wordfind Readme (libreria de sopas de letras), API wordfind: newPuzzle, fillBlanks, solve, print, orientations, wordfindgame.js (juego de ejemplo, depende de jQuery), Opciones: height, width, orientations, fillBlanks, maxAttempts, preferOverlap

### Community 23 - "Chat legado PHP (copia)"
Cohesion: 0.28
Nodes (3): corregirTexto2(), procesarConsulta(), quitarTildes()

### Community 24 - "Dependencias React"
Cohesion: 0.36
Nodes (7): @babel/preset-react, react, react-dom, dependencies, @babel/preset-react@^7.27.1, react@^19.1.0, react-dom@^19.1.0

### Community 25 - "Tutorial guiado"
Cohesion: 0.40
Nodes (3): Tutorial tour climatico, Tutorial tour inicio, Tutorial tour parques_naturales

### Community 26 - "Ver más (fichas)"
Cohesion: 0.70
Nodes (4): idSeguro(), mostrarDescripcion(), quitarActive(), verMas()

### Community 27 - "Utilidad unir JSON"
Cohesion: 0.50
Nodes (3): unir.html - utilidad para unir JSON de departamentos, 32 archivos JSON de departamentos (amazonas ... vichada), jQuery 3.6.0 (code.jquery.com)

### Community 29 - "Corrector de texto"
Cohesion: 0.67
Nodes (3): Corrector de texto page, Correction form and copy-result button, Ollama API localhost:11434/api/generate (qwen2.5:7b)

## Knowledge Gaps
- **131 isolated node(s):** `Maquina del tiempo y tunel del tiempo`, `Modal de detalles de elemento con botones de aumentar/disminuir fuente`, `ViajaPED styles.css`, `coordilleras.json (datos del mapa)`, `Widget de chat` (+126 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 273 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **15 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Juegos Educativos Explora (portal de juegos)` connect `Menú circular y portal de juegos` to `Página línea de tiempo`?**
  _High betweenness centrality (0.269) - this node is a cross-community bridge._
- **Are the 4 inferred relationships involving `Laberinto Espacial game` (e.g. with `La Bandera de Colombia game` and `Game-Laberinto README`) actually correct?**
  _`Laberinto Espacial game` has 4 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Maquina del tiempo y tunel del tiempo`, `Modal de detalles de elemento con botones de aumentar/disminuir fuente`, `ViajaPED styles.css` to the rest of the system?**
  _131 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Resultados y pantalla completa de juegos` be split into smaller, more focused modules?**
  _Cohesion score 0.05454545454545454 - nodes in this community are weakly interconnected._
- **Why does `Mapa Climatico de Colombia` connect `Página climático` to `Menú circular y portal de juegos`, `Selector de tipo de mapa`, `Mapplic principal`, `Mapplic genérico (mapas temáticos)`, `Página principal (atlas)`, `Estilos y librerías del atlas`, `Cliente API (explora-api)`, `Quiz del departamento`, `Chat (cliente)`, `Tutorial guiado`, `Ver más (fichas)`?**
  _High betweenness centrality (0.162) - this node is a cross-community bridge._
- **Should `Menú circular y portal de juegos` be split into smaller, more focused modules?**
  _Cohesion score 0.04964539007092199 - nodes in this community are weakly interconnected._
- **Why does `Parques Naturales de Colombia` connect `Estilos y librerías del atlas` to `Selector de tipo de mapa`, `Mapplic genérico (mapas temáticos)`, `Mapplic principal`, `Menú circular y portal de juegos`, `Página climático`, `Página principal (atlas)`, `Cliente API (explora-api)`, `Quiz del departamento`, `Chat (cliente)`, `Tutorial guiado`, `Ver más (fichas)`?**
  _High betweenness centrality (0.162) - this node is a cross-community bridge._