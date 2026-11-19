# Graph Report - laravel  (2026-11-19)

## Corpus Check
- Corpus is ~36,695 words - fits in a single context window. You may not need a graph.

## Summary
- 730 nodes · 1156 edges · 42 communities (15 shown, 27 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS · INFERRED: 2 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Sesión y login
- Pruebas de perfil/avatar
- Migraciones y esquema
- Dependencias Composer
- Ficha de departamento (API)
- Dependencias npm/Playwright
- Modelo Departamento
- Modelos de ficha
- Kernel y middleware HTTP
- Cliente del legado
- Validación de juegos
- Manejo de excepciones
- Service providers
- Tests de endpoints legados
- Tests de juegos
- Tests servidor desarrollo
- Build del sitio
- Usuario y perfil
- Tests de defectos QA
- Tests de modelos
- Tests sin choques
- Console Kernel
- TrustHosts
- Seeders
- serve.sh

## God Nodes (most connected - your core abstractions)
1. `AutenticacionTest` - 38 edges
2. `DepartamentoDescripcion` - 28 edges
3. `JuegosTest` - 28 edges
4. `LegacyEndpointsTest` - 25 edges
5. `TestCase` - 24 edges
6. `Controller` - 18 edges
7. `SeccionDepartamento` - 18 edges
8. `Usuario` - 17 edges
9. `SoloLectura` - 16 edges
10. `QuizContractTest` - 14 edges

## Surprising Connections (you probably didn't know these)
- `{closure#1}()` --references--> `ResultadoJuego`  [EXTRACTED]
  app/Http/Controllers/Api/HistorialController.php → app/Models/Explora/ResultadoJuego.php
- `{closure#1}()` --references--> `ResultadoJuego`  [EXTRACTED]
  app/Http/Controllers/Api/RankingController.php → app/Models/Explora/ResultadoJuego.php
- `SesionController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Api/SesionController.php → app/Http/Controllers/Controller.php
- `SeccionDepartamento` --mixes_in--> `SoloLectura`  [EXTRACTED]
  app/Models/Explora/SeccionDepartamento.php → app/Models/Concerns/SoloLectura.php
- `Usuario` --mixes_in--> `SoloLectura`  [EXTRACTED]
  app/Models/Pedigital/Usuario.php → app/Models/Concerns/SoloLectura.php

## Import Cycles
- None detected.

## Communities (42 total, 27 thin omitted)

### Community 0 - "Sesión y login"
Cohesion: 0.06
Nodes (14): SesionController, Authenticate, CuentaActiva, MemoriaParaFichas, RedirectIfAuthenticated, FichaDepartamentoResource, PreguntasDepartamentoResource, UsuarioSesionResource (+6 more)

### Community 1 - "Pruebas de perfil/avatar"
Cohesion: 0.06
Nodes (7): RespaldaPerfiles, AutenticacionTest, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), PerfilExploraTest

### Community 2 - "Migraciones y esquema"
Cohesion: 0.07
Nodes (40): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+32 more)

### Community 3 - "Dependencias Composer"
Cohesion: 0.04
Nodes (47): ignore-id, pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config (+39 more)

### Community 4 - "Ficha de departamento (API)"
Cohesion: 0.08
Nodes (10): FichaDepartamentoController, {closure#1}(), HistorialController, PerfilAvatarController, PreguntasDepartamentoController, {closure#1}(), RankingController, ResultadoJuegoController (+2 more)

### Community 5 - "Dependencias npm/Playwright"
Cohesion: 0.06
Nodes (27): devDependencies, axios@^1.6.4, laravel-vite-plugin@^1.0.0, @playwright/test@1.60.0, vite@^5.0.0, private, scripts, build (+19 more)

### Community 6 - "Modelo Departamento"
Cohesion: 0.10
Nodes (6): SoloLectura, Departamento, DepartamentoDescripcion, OpcionMultiple, OpcionVerfal, Pregunta

### Community 7 - "Modelos de ficha"
Cohesion: 0.05
Nodes (20): DepartamentoCapital, DepartamentoClima, DepartamentoCultura, DepartamentoDemografia, DepartamentoEconomia, DepartamentoEtnografia, DepartamentoFaunaFlora, DepartamentoGastronomia (+12 more)

### Community 8 - "Kernel y middleware HTTP"
Cohesion: 0.06
Nodes (7): Kernel, EncryptCookies, PreventRequestsDuringMaintenance, TrimStrings, TrustProxies, ValidateSignature, VerifyCsrfToken

### Community 9 - "Cliente del legado"
Cohesion: 0.08
Nodes (5): {closure#1}(), {closure#2}(), ContractoLegado, FichaContractTest, QuizContractTest

### Community 10 - "Validación de juegos"
Cohesion: 0.11
Nodes (5): CambiarAvatarRequest, ReglasDeJuego, GuardarResultadoRequest, LoginRequest, RankingRequest

### Community 11 - "Manejo de excepciones"
Cohesion: 0.10
Nodes (8): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), Handler, terminar(), verificarBasesDePrueba(), CreatesApplication

### Community 12 - "Service providers"
Cohesion: 0.10
Nodes (4): AppServiceProvider, AuthServiceProvider, BroadcastServiceProvider, EventServiceProvider

### Community 16 - "Build del sitio"
Cohesion: 0.16
Nodes (7): CARPETAS_EXCLUIDAS, EXTENSIONES_EXCLUIDAS, RAIZ, recorrer(), SALIDA, fixtures(), globalSetup()

### Community 18 - "Tests de defectos QA"
Cohesion: 0.21
Nodes (3): DefectosQaTest, ExampleTest, TestCase

## Knowledge Gaps
- **27 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `TestCase` connect `Tests de defectos QA` to `Pruebas de perfil/avatar`, `Cliente del legado`, `Manejo de excepciones`, `Tests de endpoints legados`, `Tests de juegos`, `Usuario y perfil`, `Tests de modelos`, `Tests sin choques`, `Pest juegos`, `Solo lectura`?**
  _High betweenness centrality (0.162) - this node is a cross-community bridge._
- **Should `Sesión y login` be split into smaller, more focused modules?**
  _Cohesion score 0.05605499735589635 - nodes in this community are weakly interconnected._
- **Why does `Usuario` connect `Usuario y perfil` to `Sesión y login`, `Pruebas de perfil/avatar`, `Modelo Departamento`, `Pest juegos`, `Solo lectura`?**
  _High betweenness centrality (0.082) - this node is a cross-community bridge._
- **Should `Pruebas de perfil/avatar` be split into smaller, more focused modules?**
  _Cohesion score 0.05928614640048397 - nodes in this community are weakly interconnected._
- **Why does `SeccionDepartamento` connect `Modelos de ficha` to `Modelo Departamento`?**
  _High betweenness centrality (0.079) - this node is a cross-community bridge._
- **Should `Migraciones y esquema` be split into smaller, more focused modules?**
  _Cohesion score 0.07215686274509804 - nodes in this community are weakly interconnected._
- **Should `Dependencias Composer` be split into smaller, more focused modules?**
  _Cohesion score 0.041666666666666664 - nodes in this community are weakly interconnected._