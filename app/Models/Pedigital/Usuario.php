<?php

namespace App\Models\Pedigital;

use App\Contracts\IdentidadUsuario;
use App\Models\Concerns\SoloLectura;
use App\Models\Explora\PerfilExplora;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuario de la plataforma pednia/ambientes (base de datos `pedigital`, tabla `users`).
 *
 * Es la ÚNICA fuente de identidad de Explora: no existe tabla de usuarios propia. Por eso:
 *  - La conexión `pedigital` es de SOLO LECTURA (usuario MySQL con únicamente SELECT) y este modelo usa el trait
 *    {@see SoloLectura} como segunda barrera: cualquier `save()` o `delete()` lanza una excepción.
 *  - Es `Authenticatable`, de modo que Laravel puede iniciar sesión con él (guard `web`, proveedor `usuarios`).
 *  - Los datos propios de Explora del usuario (p. ej. el avatar) se guardan en `explora.perfiles_explora`
 *    ({@see PerfilExplora}), nunca aquí.
 *
 * Columnas de la tabla (nombres reales, algunos con errores ortográficos que NO se deben «corregir»):
 * id, nombre_usuario, login_usuario, pasword_usuario (hash bcrypt, sic), tipo_usuario (Estudiante / Profesor /
 * Administrador / root), email_usuario, remember_token, estado_usuario (p. ej. «ACTIVO»), grado_usuario y
 * avatar_explora (valor heredado del legado; ver PerfilExplora::avatarDe()).
 *
 * @property int $id
 * @property string $nombre_usuario
 * @property string $login_usuario
 * @property string $pasword_usuario Hash de la contraseña; nunca debe salir en una respuesta (SEC-04).
 * @property string $tipo_usuario
 * @property string|null $email_usuario
 * @property string $estado_usuario
 * @property string|null $grado_usuario
 * @property int|null $avatar_explora
 */
class Usuario extends Authenticatable implements IdentidadUsuario
{
    use SoloLectura;

    /** Conexión de solo lectura definida en config/database.php. */
    protected $connection = 'pedigital';

    protected $table = 'users';

    /**
     * Atributos que NUNCA se serializan (toArray/toJson). Es una defensa adicional: las respuestas de la API
     * se construyen con Resources de lista blanca y no devuelven el modelo tal cual.
     *
     * @var array<int, string>
     */
    protected $hidden = ['pasword_usuario', 'remember_token', 'email_usuario'];

    /** Los datos son de solo lectura: se permite hidratar cualquier columna, pero no guardar (ver SoloLectura). */
    protected $guarded = [];

    /**
     * Hash de la contraseña, que Laravel compara con la clave escrita.
     *
     * La columna se llama `pasword_usuario` (sic), no `password`, así que se indica aquí.
     *
     * Los prefijos bcrypt `$2a$` y `$2b$` se devuelven como `$2y$`: son el MISMO algoritmo (solo cambia la etiqueta
     * según quién generó el hash) y PHP los verifica igual, pero Laravel solo reconoce `$2y$` y lanzaría una excepción.
     * En los datos reales hay al menos un hash `$2a$`: el legado lo aceptaba y Laravel dejaría fuera a esa persona.
     * (`$2x$` NO se convierte: es una variante con un defecto histórico.)
     *
     * @return string Hash listo para comparar; cadena vacía si la cuenta no tiene contraseña.
     */
    public function getAuthPassword(): string
    {
        $hash = (string) $this->pasword_usuario;

        // Se reemplaza por posición (no con preg_replace): en una cadena de reemplazo, «$2» sería una referencia de grupo.
        return preg_match('/^\$2[ab]\$/', $hash) === 1 ? '$2y$'.substr($hash, 4) : $hash;
    }

    /**
     * Explora no usa la opción «recordarme». Devolver siempre null evita que, al cerrar sesión, Laravel intente
     * «rotar» el remember_token y escriba en `pedigital` (lo que está prohibido y lanzaría una excepción).
     *
     * @return string|null Siempre null.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * No-op: ver {@see self::getRememberToken()}.
     *
     * @param  mixed  $value  Valor que Laravel intentaría guardar; se descarta.
     */
    public function setRememberToken($value): void
    {
        // Intencionalmente vacío: no se guarda ningún token de «recordarme».
    }

    /**
     * Indica si la cuenta está habilitada (`estado_usuario` = «ACTIVO», sin distinguir mayúsculas).
     * Cierra el hallazgo DAT-01: el legado no consultaba este campo y una cuenta deshabilitada podía iniciar sesión.
     *
     * @return bool true si la cuenta está habilitada.
     */
    public function esActivo(): bool
    {
        return strtoupper((string) $this->estado_usuario) === 'ACTIVO';
    }

    /** {@inheritDoc} Columna `nombre_usuario`. */
    public function nombreCompleto(): string
    {
        return (string) $this->nombre_usuario;
    }

    /** {@inheritDoc} Columna `login_usuario`. */
    public function login(): string
    {
        return (string) $this->login_usuario;
    }

    /** {@inheritDoc} Columna `grado_usuario` (null se entrega como cadena vacía). */
    public function grado(): string
    {
        return (string) $this->grado_usuario;
    }

    /** {@inheritDoc} Columna `tipo_usuario`. */
    public function rol(): string
    {
        return (string) $this->tipo_usuario;
    }
}
