<?php

namespace App\Modules\Schools\Services;

use RuntimeException;

/** Conflito de regra de negócio (duplicação, capacidade, transição inválida): responde 409. */
class ConflictException extends RuntimeException {}
