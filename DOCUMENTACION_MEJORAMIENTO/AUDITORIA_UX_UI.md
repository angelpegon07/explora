# AUDITORÍA UX/UI Y FRONTEND

Alcance real: revisión estática de HTML/JS. **No se realizaron pruebas visuales** (no se abrió la aplicación en navegador), por lo que gran parte queda "pendiente de validación".

## Comprobado por código
- Las páginas inspeccionadas (`index`, `juegos/index`, `miPerfil`) incluyen `meta viewport` → base responsive presente.
- `index.html` sin atributo `lang`; 1 de 3 imágenes sin `alt` (juegos: 20/20 con `alt`).
- Quiz: opciones como `<label onclick>` → no accesibles por teclado ni lectores de pantalla (UX-01).
- Tutorial guiado con driver.js en las páginas de mapas (buena práctica de onboarding).
- SweetAlert2 como sistema de alertas común; consistencia de estilos compartidos (`css/general.css`, `style.css`).
- Contenido HTML inyectado con `.html()`; mezcla de estilos en línea en `chat.php`.

## Pendiente de validación visual
Jerarquía, tipografía, contraste, comportamiento en tablet/móvil, estados de carga, consistencia entre módulos y juegos (cada juego usa su propio Bootstrap/estilos).

## Oportunidades (sin rediseño total)
1. Botones reales en el quiz y navegación por teclado.
2. `lang="es"` y textos alternativos.
3. Unificar Bootstrap (4.5.2 vs 5.3) durante la migración, evitando romper estilos: migrar por módulo.
4. Mantener la identidad visual actual; no se propone cambiar a React/Vue.

## Siguiente paso
Recorrido visual en 3 tamaños (desktop, tablet, móvil) con la aplicación en ejecución.
