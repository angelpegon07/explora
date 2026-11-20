{{--
    Contenedor del mapa (#wrap > #content > #mapplic). Mapplic dibuja el mapa dentro de #mapplic.
    La ventana `.editor-window` es un resto del demo de Mapplic: se conserva porque el script la usa (`.usage`).
--}}
<div id="wrap">
    <div id="content">
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
