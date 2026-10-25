import '@testing-library/jest-dom/vitest'
import { configure } from '@testing-library/react'

// Os componentes carregam dados por fetch simulado; o limite por omissão (1 s) é apertado em máquinas ocupadas.
configure({ asyncUtilTimeout: 5000 })
