<?php

namespace App\Http\Controllers\Api;

use App\Contracts\IdentidadUsuario;
use App\Contracts\ProveedorIdentidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UsuarioSesionResource;
use App\Support\NormalizaLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Sesión de usuario de Explora (reemplaza a mapplic/php/iniciarSesion.php y al «estado de sesión» que el legado
 * guardaba en localStorage del navegador).
 *
 * Rutas (grupo `web`: cookie de sesión HttpOnly + protección CSRF):
 *  - POST   /api/sesion  → {@see self::store()}    inicia sesión
 *  - GET    /api/sesion  → {@see self::show()}     devuelve el usuario de la sesión
 *  - DELETE /api/sesion  → {@see self::destroy()}  cierra la sesión
 *
 * Hallazgos que cierra: SEC-03 (la identidad viene de una sesión de servidor, no de un id del cliente),
 * SEC-04 (sin hash en la respuesta, sin enumeración de usuarios, con límite de intentos) y DAT-01 (se respeta
 * `estado_usuario`).
 */
class SesionController extends Controller
{
    /**
     * Hash bcrypt de una cadena aleatoria sin valor secreto. Se compara contra la clave escrita cuando el usuario
     * NO existe, para que el tiempo de respuesta sea parecido al de un usuario real y no se pueda enumerar
     * usuarios midiendo la demora (ataque de temporización).
     */
    private const HASH_SENUELO = '$2y$10$Sh9zNY3tg/o6cHOGG9LIBu8n.pIhcoeJeSNiebUTPgQQnv2lsOCjm';

    /**
     * Intentos fallidos permitidos por CUENTA (no por IP) dentro de {@see self::VENTANA_CUENTA}. Complementa al límite
     * por usuario+IP de la ruta (que se esquiva cambiando de IP) y se cuenta por el id de la cuenta, o por el nombre
     * normalizado si no existe, para que tampoco delate qué usuarios existen.
     */
    private const MAX_FALLOS_CUENTA = 20;

    /** Segundos que se recuerdan los fallos de una cuenta (15 minutos). */
    private const VENTANA_CUENTA = 900;

    /** Mensaje único para cualquier fallo de credenciales: no revela si el usuario existe (SEC-04). */
    private const MENSAJE_FALLO = 'Usuario o contraseña incorrectos.';

    /**
     * Inicia sesión.
     *
     * Flujo: valida el formulario ({@see LoginRequest}); busca el usuario por `login_usuario`; comprueba la clave
     * con bcrypt; exige que la cuenta esté activa; y, si todo es correcto, abre la sesión del guard `web` y
     * REGENERA el identificador de sesión (previene la fijación de sesión).
     *
     * Límites de intentos (SEC-04): 5/min por pareja usuario+IP y 30/min por IP los aplica el middleware
     * `throttle:login` de la ruta; además, este método limita los FALLOS por cuenta a
     * {@see self::MAX_FALLOS_CUENTA} cada 15 minutos (sirve contra quien cambia de IP). Un inicio correcto reinicia
     * el contador de la cuenta.
     *
     * @return JsonResponse 200 `{success:true, mensaje, usuario_objeto}`; 401 `{success:false, mensaje}` ante
     *                      cualquier fallo de credenciales o cuenta inactiva (mismo cuerpo en todos los casos);
     *                      429 si la cuenta acumuló demasiados fallos.
     */
    public function store(LoginRequest $request, ProveedorIdentidad $identidades): JsonResponse
    {
        $login = $request->validated('usuario');
        $usuario = $identidades->buscarPorLogin($login);

        // Límite por cuenta: se consulta ANTES de comparar claves para que, una vez bloqueada, no se pueda seguir
        // probando contraseñas. La clave es el id de la cuenta (o el nombre normalizado si no existe).
        $claveCuenta = 'login-cuenta:'.($usuario?->getAuthIdentifier() ?? 'x:'.NormalizaLogin::clave($login));
        if (RateLimiter::tooManyAttempts($claveCuenta, self::MAX_FALLOS_CUENTA)) {
            return response()->json(
                ['success' => false, 'mensaje' => 'Demasiados intentos. Espere un momento e inténtelo de nuevo.'],
                429,
                ['Retry-After' => RateLimiter::availableIn($claveCuenta)],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (! $this->credencialesValidas($usuario, $request->validated('contrasena'))) {
            RateLimiter::hit($claveCuenta, self::VENTANA_CUENTA);

            return response()->json(
                ['success' => false, 'mensaje' => self::MENSAJE_FALLO],
                401,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        RateLimiter::clear($claveCuenta);
        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'mensaje' => 'Inicio de sesión exitoso',
            // Nombre de clave conservado del legado para poder conmutar el frontend sin reescribirlo.
            'usuario_objeto' => (new UsuarioSesionResource($usuario))->resolve(),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Devuelve el usuario de la sesión actual. Sustituye a leer `localStorage` (que cualquiera podía editar).
     * También sirve para que el navegador reciba la cookie `XSRF-TOKEN` antes de enviar peticiones de escritura.
     *
     * @return JsonResponse 200 `{success:true, usuario_objeto}`; 401 `{success:false, mensaje}` sin sesión.
     */
    public function show(Request $request): JsonResponse
    {
        $usuario = $request->user('web');

        if (! $usuario) {
            return response()->json(
                ['success' => false, 'mensaje' => 'No hay una sesión iniciada.'],
                401,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        return response()->json([
            'success' => true,
            'usuario_objeto' => (new UsuarioSesionResource($usuario))->resolve(),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Cierra la sesión: olvida al usuario, invalida la sesión del servidor y renueva el token CSRF.
     * No escribe en `pedigital` (el modelo no usa «recordarme»; ver Usuario::getRememberToken()).
     *
     * @return JsonResponse 200 `{success:true}` (también si no había sesión: la operación es idempotente).
     */
    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true, 'mensaje' => 'Sesión cerrada.'], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Comprueba la clave y el estado de la cuenta SIN delatar cuál de las dos cosas falló.
     *
     * Siempre se ejecuta una comparación bcrypt (con el hash señuelo si el usuario no existe) para igualar los
     * tiempos de respuesta. Si el hash almacenado no es bcrypt, Laravel lanza una excepción: se trata como fallo
     * de credenciales en vez de devolver un error 500.
     *
     * @param  IdentidadUsuario|null  $usuario  Usuario encontrado por `login_usuario`, o null si no existe.
     * @param  string  $clave  Contraseña escrita por la persona.
     */
    private function credencialesValidas(?IdentidadUsuario $usuario, string $clave): bool
    {
        $hash = $usuario?->getAuthPassword();
        // Una cuenta sin hash NO es utilizable: nunca se debe aceptar una clave comparándola con el señuelo.
        $utilizable = is_string($hash) && $hash !== '';

        try {
            $claveCorrecta = Hash::check($clave, $utilizable ? $hash : self::HASH_SENUELO);
        } catch (\RuntimeException) {
            return false;
        }

        return $utilizable && $claveCorrecta && $usuario->esActivo();
    }
}
