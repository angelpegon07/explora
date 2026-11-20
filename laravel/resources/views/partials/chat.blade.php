{{-- Ventana del chat (#chat). La lógica está en public/mapplic/js/chat.js; mostrarChat()/cerrarChat() la abren y cierran. --}}
<div id="chat">
    <div class="chat_window">
        <div class="top_menu">
            <div class="buttons">
                <div class="button close" onclick="cerrarChat()"></div>
                <div class="button minimize"></div>
                <div class="button maximize"></div>
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
