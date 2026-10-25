<?php

/*
 * Currículo oficial por classe (docs/regras-ensino.md §2). Copiado para cada escola
 * na aprovação e ajustável depois (subjects + grade_level_subjects).
 * Formato: código => [nome, categoria, créditos, horas semanais, classes, nuclear]
 */
return [
    'subjects' => [
        'LP' => ['Língua Portuguesa', 'core', 2, 5, [1, 2, 3, 4, 5, 6, 7, 8, 9], true],
        'MAT' => ['Matemática', 'core', 2, 5, [1, 2, 3, 4, 5, 6, 7, 8, 9], true],
        'EM' => ['Estudo do Meio', 'core', 1, 3, [1, 2, 3, 4], false],
        'CN' => ['Ciências da Natureza', 'core', 1, 3, [5, 6], false],
        'BIO' => ['Biologia', 'core', 1, 3, [7, 8, 9], false],
        'FIS' => ['Física', 'core', 1, 3, [7, 8, 9], false],
        'QUI' => ['Química', 'core', 1, 3, [7, 8, 9], false],
        'HIST' => ['História', 'core', 1, 2, [5, 6, 7, 8, 9], false],
        'GEO' => ['Geografia', 'core', 1, 2, [5, 6, 7, 8, 9], false],
        'EMC' => ['Educação Moral e Cívica', 'core', 1, 2, [1, 2, 3, 4, 5, 6, 7, 8, 9], false],
        'EMP' => ['Educação Manual e Plástica', 'core', 1, 2, [1, 2, 3, 4, 5, 6], false],
        'MUS' => ['Educação Musical', 'core', 1, 2, [1, 2, 3, 4, 5, 6], false],
        'EF' => ['Educação Física', 'core', 1, 2, [1, 2, 3, 4, 5, 6, 7, 8, 9], false],
        'EL' => ['Educação Laboral', 'core', 1, 2, [7, 8, 9], false],
        'LE' => ['Língua Estrangeira', 'core', 1, 3, [5, 6, 7, 8, 9], false],
        'EMPR' => ['Empreendedorismo', 'optional', 1, 2, [8, 9], false],
        'LOA' => ['Línguas de Origem Africana', 'optional', 1, 2, [5, 6, 7, 8, 9], false],
    ],

    // Iniciação (classe 0): áreas, todas obrigatórias, sem notas.
    'preschool_areas' => [
        'CL' => ['Comunicação Linguística', 3],
        'RM' => ['Representação Matemática', 3],
        'EP' => ['Expressão Plástica', 2],
        'MFS' => ['Meio Físico e Social', 2],
        'PJ' => ['Psicomotricidade e Jogos', 2],
        'HD' => ['Histórias Dramáticas', 1],
    ],
];
