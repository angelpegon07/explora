{{--
    Contenedor del mapa (#wrap > #content > #mapplic). Mapplic dibuja el mapa dentro de #mapplic.
    Fase 17F-C: #wrap es el landmark principal (`role="main"`) y #content el destino del enlace «Saltar al contenido»; no se envuelve en <main> para no alterar el DOM que esperan el CSS y Mapplic.
    La ventana `.editor-window` es un resto del demo de Mapplic: se conserva porque el script la usa (`.usage`).
--}}
<div id="wrap" role="main">
    <div id="content" id="contenido-principal" tabindex="-1">
        <section id="map-section" class="inner over">
            <div class="editor-window">
                <div class="window-mockup brown"></div>
                <div class="editor-body"> <code>
                    {<br>
                    &nbsp;&nbsp;&nbsp;"id": "newlandmark",<br>
                    &nbsp;&nbsp;&nbsp;"title": "New Landmark",<br>
                    &nbsp;&nbsp;&nbsp;"description": "Creating a new landmark is that easy!",<br>
                    &nbsp;&nbsp;&nbsp;"x": "<span class="mapplic-coordinates-x">0.0000</span>",<br>
                    &nbsp;&nbsp;&nbsp;"y": "<span class="mapplic-coordinates-y">0.0000</span>",<br>
                    &nbsp;&nbsp;&nbsp;...<br>
                    }
                </code> </div>
            </div>
            <div class="map-container">
                <div id="mapplic"></div>
            </div>
        </section>
    </div>
</div>
