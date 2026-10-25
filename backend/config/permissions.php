<?php

/*
 * Papéis institucionais e as habilitações que concedem ('*' = todas).
 * A habilitação só vale dentro da instituição activa (ver CurrentTenant).
 */
return [
    'abilities' => [
        'users.manage',
        'profile.manage',
        'rules.manage',
        'curriculum.manage',
        'structure.manage',
        'students.view',
        'students.manage',
        'guardians.manage',
        'enrollments.manage',
        'grades.view',
        'grades.write',
        'grades.lock',
        'results.close',
        'documents.issue',
        'finance.view',
        'finance.manage',
        'finance.pay',
        'finance.reverse',
    ],

    'roles' => [
        'owner' => ['*'],
        'director' => ['*'],
        'secretary' => [
            'curriculum.manage', 'structure.manage',
            'students.view', 'students.manage', 'guardians.manage', 'enrollments.manage',
            'grades.view', 'documents.issue',
        ],
        'teacher' => ['students.view', 'grades.view', 'grades.write'],
        'finance' => ['students.view', 'finance.view', 'finance.manage', 'finance.pay', 'finance.reverse'],
        'cashier' => ['students.view', 'finance.view', 'finance.pay'],
        'trainer' => ['students.view'],
    ],
];
