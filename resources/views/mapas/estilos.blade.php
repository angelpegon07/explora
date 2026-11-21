{{--
    Estilos propios de una página de mapa temático. Se incluye dentro de @push('estilos').

    Parámetros: $css (arreglo `css` del mapa en config/mapas.php) y $datoInteres (bool).
    El ORDEN de las reglas es el del HTML original (algunas se repiten con otros valores y gana la última):
    no reordenar sin comprobar el aspecto. Todos los valores salen de la configuración y se imprimen con {{ }}.
--}}
<style>
    @if ($css['tooltip_descripcion'])
        {{-- Etnias y parques naturales: tipografía del contenido del globo informativo. --}}
        .mapplic-tooltip-description figure {
            text-align: center;
        }

        .mapplic-tooltip-description span {
            font-family: 'Comic Sans MS', cursive, sans-serif !important;
        }

        .mapplic-tooltip-description p {
            font-family: 'Comic Sans MS', cursive, sans-serif !important;
            font-size: 15px;
        }

        .mapplic-tooltip-content {
            scroll-behavior: inherit !important;
        }

        .mapplic-tooltip-title {
            color: #515050;
            font-size: 18px;
            margin: 0 0px 5px 0;
            font-weight: bold;
        }

    @endif
    .mapplic-clickable:not(g):hover, g.mapplic-clickable:hover > * {
        stroke: #ff0000be !important;
        opacity: 0.8;
        fill: #ffffff78 !important;
        stroke-width: {{ $css['trazo'] }} !important;
    }

    @if ($css['regla_activa'])
        .mapplic-active, a.mapplic-active > path, g.mapplic-active > * {
            stroke: #ff0000be !important;
            opacity: 0.8;
            fill: #ffffff78 !important;
            stroke-width: {{ $css['trazo'] }} !important;
        }

    @endif
    .mapplic-container {
        background-color: #f8f8f8;
        display: inline-block;
        overflow: hidden;
        position: relative;
        width: {{ $css['paneles'][1] }}%;
        height: 100%;
    }

    .mapplic-sidebar {
        background-color: #f8f8f8;
        width: {{ $css['paneles'][0] }}%;
        height: 100%;
        float: left;
        position: relative;
    }


    .mapplic-tooltip {
        background-color: #fff;
        border-radius: 2px;
        box-shadow: 0 0 6px rgba(0, 0, 0, 0.2);
        display: none;
        max-width: {{ $css['tooltip_ancho'] }};
        min-width: 120px;
        margin-top: -76px;
        padding: 8px;
        position: absolute;
        -webkit-transition: margin 0.1s;
        -moz-transition: margin 0.1s;
        transition: margin 0.1s;
    }

    .multimedia-div {
        width: 71px;
        height: 100%;
        background-color: #ffffff00;
        position: absolute;
        right: -75px;
        z-index: 333;
        top: 0%;
        display: flex;
        justify-content: center !important;
        flex-direction: column !important;
        align-items: center !important;
        box-shadow: none;
        padding-right: 10px;
    }
    @if ($css['paneles_ajuste'])

        {{-- Segundo bloque: sobrescribe los anchos anteriores (la barra lateral pasa a 30 % y el mapa a 70 %). --}}
        .mapplic-sidebar {
            background-color: #f8f8f8;
            width: {{ $css['paneles_ajuste'][0] }}%;
            height: 100%;
            float: left;
            position: relative;
        }

        .mapplic-container {
            background-color: #f8f8f8;
            display: inline-block;
            overflow: hidden;
            position: relative;
            width: {{ $css['paneles_ajuste'][1] }}%;
            height: 100%;
        }
    @endif
    @if ($datoInteres)

        {{-- Panel «Dato de interés» del mapa climático (ver mapas/dato-interes). --}}
        .dato_interes {
            right: -600px;
            background-color: #ffffff;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
            width: 30%;
            position: absolute;
            top: 20%;
            z-index: 1000;
            font-size: 13px !important;
            transition: right 2.3s ease-in-out;
        }

        .dato_interes_body p{
            font-size: 13px ;
            line-height: 1.2 ;
            margin-bottom: 5px ;
        }

        .btn_cerrar_dato_interes{
            cursor: pointer;
            color: #c94141;
            font-size: 25px;
            position: absolute;
            right: 5px;
            top: 6px;
        }

        .dato_interes ul {
            margin-bottom: 0px !important;
            padding-left: 10px !important;
        }

        #pensando_gif {
            position: absolute;
            top: 64%;
            right: 92%;
        }
    @endif
</style>
