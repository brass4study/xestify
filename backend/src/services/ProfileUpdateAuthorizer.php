<?php

declare(strict_types=1);

namespace Xestify\services;

/**
 * Decisiones de autorización sobre auto-servicio de perfil
 * (UserController::updateMe()): exige verificación de contraseña actual al
 * cambiar email/password, y bloquea ese cambio en cuentas semilla protegidas.
 * Sin I/O, sin HTTP — el llamador traduce el resultado a una respuesta
 * concreta, mismo patrón que UserAuthorizer.
 */
final class ProfileUpdateAuthorizer
{
    public const NOT_FOUND = 'not_found';
    public const PROTECTED_SEED = 'protected_seed';
    public const EMAIL_SECRET_REQUIRED = 'email_secret_required';
    public const PASSWORD_SECRET_REQUIRED = 'password_secret_required';
    public const SECRET_MISMATCH = 'secret_mismatch';

    public function __construct(private ProfileSecretVerifier $secretVerifier = new ProfileSecretVerifier())
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $profile
     */
    public function authorize(array $payload, ?array $profile): ?string
    {
        $isEmailChange = $this->secretVerifier->isEmailChange($payload);
        $isPasswordChange = $this->secretVerifier->isPasswordChange($payload);

        if (!$isEmailChange && !$isPasswordChange) {
            return null;
        }

        if ($profile === null) {
            return self::NOT_FOUND;
        }

        return $this->authorizeVerifiedChange($payload, $profile, $isEmailChange, $isPasswordChange);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $profile
     */
    private function authorizeVerifiedChange(array $payload, array $profile, bool $isEmailChange, bool $isPasswordChange): ?string
    {
        $requiresVerification = $this->secretVerifier->requiresVerification($payload, $profile, $isEmailChange, $isPasswordChange);
        if (!$requiresVerification) {
            return null;
        }

        // Cuentas semilla (admin/usuario de demostración): el email y la contraseña
        // son fijos porque los botones de acceso rápido de login dependen de ellos.
        // Reenviar el mismo email sin cambiarlo no llega hasta aquí (requiresVerification
        // ya lo filtra), así que el resto de campos propios sigue editable.
        if (($profile['is_seed'] ?? false) === true) {
            return self::PROTECTED_SEED;
        }

        return $this->verifySecret($payload, $profile, $isEmailChange);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $profile
     */
    private function verifySecret(array $payload, array $profile, bool $isEmailChange): ?string
    {
        $currentPassword = (string) ($payload['current_password'] ?? '');
        if ($currentPassword === '') {
            return $isEmailChange ? self::EMAIL_SECRET_REQUIRED : self::PASSWORD_SECRET_REQUIRED;
        }

        return $this->secretVerifier->matches($currentPassword, $profile) ? null : self::SECRET_MISMATCH;
    }
}
