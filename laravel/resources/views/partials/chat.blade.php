{{--
    Ventana del chat (#chat). La lógica está en public/mapplic/js/chat.js; mostrarChat()/cerrarChat() la abren y cierran.

    Parámetro opcional:
      $chatCompleto (bool, por defecto false)  Variante de la barra superior:
        false = la de los mapas temáticos (tres puntos de color sin iconos; el botón .maximize no tiene acción).
        true  = la de la portada: iconos «x» y «-» (Font Awesome), estilos en línea de los botones y `minimizarChat()`
                en el botón de minimizar; sin botón .maximize.
      Las dos variantes se conservan idénticas a los HTML originales (compuerta de equivalencia).
--}}
@php($chatCompleto = $chatCompleto ?? false)
<div id="chat">
    <div class="chat_window">
        <div class="top_menu">
            <div class="buttons">
                @if ($chatCompleto)
                    <div style="cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0px;  margin-left: 10px;"
                        class="button close" onclick="cerrarChat()">
                        <i class="fa-solid fa-xmark"></i>
                    </div>
                    <div style="cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0px; "
                        class="button minimize" onclick="minimizarChat()">
                        <i class="fa-solid fa-minus"></i>
                    </div>
                @else
                    <div class="button close" onclick="cerrarChat()"></div>
                    <div class="button minimize"></div>
                    <div class="button maximize"></div>
                @endif
            </div>
            <div class="title">Chat</div>
        </div>
        <ul style="height: 80%; overflow-y: auto;" class="messages"></ul>
        <div class="bottom_wrapper">
            <div class="message_input_wrapper">
                <input type="text" id="message_input" class="message_input" placeholder="Escribe un mensaje..." />
            </div>
            <div class="send_message" id="enviar_mensaje">
                <i class="fa fa-paper-plane" aria-hidden="true"></i>
            </div>
        </div>
    </div>
</div>
