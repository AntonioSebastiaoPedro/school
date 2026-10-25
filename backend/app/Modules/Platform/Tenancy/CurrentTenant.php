<?php

namespace App\Modules\Platform\Tenancy;

use App\Models\User;
use RuntimeException;

/**
 * Instituição activa do pedido. O identificador nunca vem do frontend como
 * autoridade: o middleware ResolveTenant só o aceita se o utilizador tiver
 * uma associação activa com essa instituição.
 */
class CurrentTenant
{
    private ?int $id = null;

    private ?string $role = null;

    public function id(): int
    {
        return $this->id ?? throw new RuntimeException('Nenhuma instituição activa neste contexto.');
    }

    public function idOrNull(): ?int
    {
        return $this->id;
    }

    public function set(int $institutionId, ?string $role = null): void
    {
        $this->id = $institutionId;
        $this->role = $role;
    }

    public function clear(): void
    {
        $this->id = null;
        $this->role = null;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    /** Executa um bloco no contexto de uma instituição (provisionamento, filas, comandos). */
    public function runAs(int $institutionId, callable $callback): mixed
    {
        [$previousId, $previousRole] = [$this->id, $this->role];
        $this->set($institutionId);

        try {
            return $callback();
        } finally {
            $this->id = $previousId;
            $this->role = $previousRole;
        }
    }

    public function can(User $user, string $ability): bool
    {
        if ($this->id === null || $this->role === null) {
            return false;
        }

        $granted = config("permissions.roles.{$this->role}", []);

        return in_array('*', $granted, true) || in_array($ability, $granted, true);
    }
}
