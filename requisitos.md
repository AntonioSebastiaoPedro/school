# Documento de Requisitos do Produto — SaaS de Gestão Educativa para Angola

# Plataforma de Gestão Educativa

Documento de visão, requisitos funcionais, regras de negócio, arquitectura e MVP · Versão 1.0 · Outubro de 2026

SaaS multi-instituição

Laravel + React

PostgreSQL

Mercado angolano

## 1. Introdução

### 1.1. Objectivo do documento

Este documento define os requisitos iniciais de uma plataforma SaaS destinada à gestão administrativa, académica e financeira de instituições de ensino em Angola, abrangendo:

- Escolas do ensino primário.
- Escolas do I ciclo do ensino secundário.
- Centros de formação profissional.

O documento servirá como referência para a análise do negócio, desenho da arquitectura, modelação da base de dados, implementação da API, desenvolvimento das interfaces e validação do produto.

O desenvolvimento será realizado individualmente, pelo que a solução deve privilegiar uma arquitectura organizada, segura, testável e sustentável, sem complexidade operacional desnecessária.

### 1.2. Visão do produto

Criar uma plataforma web responsiva através da qual escolas e centros de formação possam gerir os seus processos institucionais num único sistema, desde a candidatura e matrícula dos alunos até à gestão académica, cobrança de propinas, controlo de pagamentos e emissão de documentos.

A plataforma será comercializada como Software as a Service (SaaS). Cada instituição solicitará adesão, será submetida à aprovação do administrador central e, após a aprovação e activação comercial, poderá configurar e utilizar o sistema de acordo com o plano contratado.

### 1.3. Objectivos de negócio

1. Digitalizar processos administrativos e académicos.
2. Reduzir a dependência de registos manuais e folhas de cálculo.
3. Melhorar o controlo financeiro e a rastreabilidade dos pagamentos.
4. Facilitar o acompanhamento dos alunos pelos encarregados de educação.
5. Permitir a gestão de cursos, formadores e certificados nos centros de formação.
6. Criar uma fonte de receita recorrente através de subscrições.
7. Oferecer uma solução que possa crescer sem exigir uma instalação independente para cada cliente.

### 1.4. Decisões já estabelecidas

| Decisão                                 | Definição                                      |
| --------------------------------------- | ---------------------------------------------- |
| Mercado inicial                         | Angola                                         |
| Modelo                                  | SaaS multi-tenant                              |
| Segmentos                               | Escolas e centros de formação                  |
| Âmbito escolar                          | Ensino primário e I ciclo                      |
| Adesão                                  | Solicitação sujeita a aprovação manual         |
| Pagamentos educativos                   | Registo manual na primeira versão              |
| Pagamentos electrónicos                 | Fase posterior                                 |
| Monetização                             | Planos com funcionalidades e limites distintos |
| Frontend                                | React + TypeScript                             |
| Backend                                 | Laravel API                                    |
| Base de dados                           | PostgreSQL                                     |
| Plataforma                              | Aplicação web responsiva                       |
| Equipa                                  | Um desenvolvedor                               |
| Funcionalidades adicionais prioritárias | Portal dos encarregados e certificados         |

## 2. Âmbito e limites do produto

### 2.1. Incluído no âmbito

A plataforma deverá contemplar:

- Gestão central da plataforma SaaS.
- Registo, verificação e aprovação de instituições.
- Gestão de subscrições e planos comerciais.
- Gestão de utilizadores, papéis e permissões.
- Gestão de alunos, formandos, encarregados e funcionários.
- Inscrições e matrículas.
- Anos lectivos, classes, turmas e disciplinas.
- Catálogo de cursos, edições, formadores e turmas de formação.
- Registo de presenças.
- Gestão de cobranças, pagamentos manuais, recibos e dívidas.
- Portal de consulta para encarregados de educação.
- Emissão e verificação de certificados de formação.
- Relatórios administrativos, académicos e financeiros.
- Auditoria, segurança, cópias de segurança e gestão documental.

### 2.2. Fora do âmbito inicial

Para manter o projecto executável por um único desenvolvedor, ficam fora do MVP:

- Aplicações móveis nativas para Android e iOS.
- Integração automática com bancos, carteiras digitais ou gateways de pagamento.
- Envio de SMS através de operadoras.
- Contabilidade geral e processamento salarial.
- Gestão de bibliotecas, transportes e cantinas.
- Ensino totalmente online com aulas em vídeo.
- Integração automática com sistemas governamentais.
- Gestão de instituições de ensino fora dos segmentos definidos.
- Aplicação de inteligência artificial para previsão de desempenho.

Estes elementos poderão ser avaliados posteriormente, mediante validação da procura e análise de custos.

### 2.3. Premissas e dependências

- Cada instituição terá uma conta institucional própria.
- Uma instituição poderá ter vários utilizadores com permissões diferentes.
- Os dados de cada instituição estarão isolados dos restantes clientes.
- Os preços, taxas e regras de cobrança serão configuráveis por instituição.
- O sistema usará o kwanza (AOA) como moeda inicial.
- A aprovação administrativa não equivale automaticamente à activação de uma subscrição paga.
- As regras legais aplicáveis a instituições de ensino, centros de formação, dados pessoais de menores, documentos e cobranças terão de ser confirmadas antes da produção.

## 3. Actores e perfis de utilizador

O sistema deverá distinguir os actores da plataforma dos actores de cada instituição. A autorização deverá resultar da combinação entre o papel do utilizador, as permissões atribuídas e o contexto institucional.

| Actor                      | Responsabilidades principais                                                       |
| -------------------------- | ---------------------------------------------------------------------------------- |
| Administrador SaaS         | Aprovar instituições, gerir planos, subscrições e operações globais.               |
| Proprietário institucional | Gerir a configuração e os utilizadores da instituição.                             |
| Director                   | Supervisionar as actividades administrativas e académicas.                         |
| Secretário académico       | Gerir alunos, inscrições, matrículas e turmas.                                     |
| Responsável financeiro     | Configurar cobranças, consultar dívidas e supervisionar pagamentos.                |
| Caixa                      | Registar pagamentos e emitir recibos, dentro das permissões atribuídas.            |
| Professor                  | Consultar as turmas atribuídas, registar presenças e avaliações.                   |
| Formador                   | Gerir as actividades das edições de cursos que lhe foram atribuídas.               |
| Encarregado de educação    | Consultar informações dos alunos sob a sua responsabilidade.                       |
| Aluno ou formando          | Consultar os seus dados e documentos, se a instituição disponibilizar esse acesso. |

### 3.1. Requisitos de autorização

- RF-001: O sistema deve permitir atribuir papéis e permissões aos utilizadores.
- RF-002: O proprietário institucional deve poder convidar utilizadores para a sua instituição.
- RF-003: Um utilizador não deve conseguir aceder a funções para as quais não tem autorização.
- RF-004: Um utilizador associado a várias instituições deve ter um contexto institucional activo em cada operação.
- RF-005: A alteração de papéis, permissões e acessos privilegiados deve ser auditável.
- RF-006: O acesso dos encarregados deve estar limitado aos alunos que lhes estão associados e cuja relação tenha sido validada.

Um papel não deve ser considerado prova suficiente de acesso a um recurso. Por exemplo, ser professor não deve permitir consultar as notas de qualquer aluno de qualquer turma.

## 4. Requisitos funcionais

Os requisitos funcionais descrevem o comportamento que o sistema deverá disponibilizar. Cada requisito possui um identificador para facilitar a implementação, os testes e a rastreabilidade.

### 4.1. Módulo A — Administração da plataforma

| ID     | Requisito funcional                                                                                             | Prioridade |
| ------ | --------------------------------------------------------------------------------------------------------------- | ---------- |
| RF-007 | O sistema deve disponibilizar um formulário público de adesão institucional.                                    | P0         |
| RF-008 | O formulário deve recolher o tipo de instituição, identificação, contactos, localização e dados do responsável. | P0         |
| RF-009 | O sistema deve permitir submeter documentos de suporte à verificação institucional.                             | P1         |
| RF-010 | O sistema deve manter os pedidos nos estados pendente, em análise, aprovado ou rejeitado.                       | P0         |
| RF-011 | O administrador deve poder aprovar, rejeitar ou solicitar informações adicionais.                               | P0         |
| RF-012 | O sistema deve registar o responsável, a data e o resultado de cada decisão administrativa.                     | P0         |
| RF-013 | A instituição aprovada deve poder concluir a configuração inicial do seu espaço.                                | P0         |
| RF-014 | O administrador deve poder suspender e reactivar instituições, com motivo registado.                            | P0         |
| RF-015 | O administrador deve consultar indicadores globais sobre instituições e subscrições.                            | P1         |

Critério de aceitação: submeter um pedido não concede acesso operacional à instituição. A aprovação deve ser efectuada por um utilizador autorizado e ficar registada no histórico.

### 4.2. Módulo B — Planos e subscrições SaaS

| ID     | Requisito funcional                                                                          | Prioridade |
| ------ | -------------------------------------------------------------------------------------------- | ---------- |
| RF-016 | O sistema deve permitir criar, editar e desactivar planos comerciais.                        | P0         |
| RF-017 | Cada plano deve possuir preço, periodicidade, funcionalidades e limites configuráveis.       | P0         |
| RF-018 | O administrador deve poder associar uma subscrição a uma instituição.                        | P0         |
| RF-019 | O sistema deve controlar os estados da subscrição e as respectivas datas.                    | P0         |
| RF-020 | O sistema deve verificar os limites do plano antes de permitir operações sujeitas a limites. | P0         |
| RF-021 | O sistema deve permitir alterar o plano de uma instituição de forma controlada.              | P1         |
| RF-022 | O sistema deve apresentar avisos sobre subscrições próximas do vencimento.                   | P1         |
| RF-023 | O sistema deve permitir registar manualmente o pagamento da subscrição comercial.            | P0         |

Os limites poderão incidir, por exemplo, sobre alunos activos, utilizadores administrativos, unidades ou funcionalidades disponíveis.

Uma mudança de plano não deve apagar dados nem eliminar o histórico da instituição. Quando o novo plano tiver limites inferiores à utilização actual, o sistema deve impedir novas utilizações que excedam os limites ou aplicar a política de transição previamente definida.

### 4.3. Módulo C — Identidade, instituições e permissões

- RF-024: O sistema deve permitir autenticação e encerramento de sessão.
- RF-025: O sistema deve disponibilizar recuperação de palavra-passe.
- RF-026: O sistema deve permitir gerir o perfil da instituição.
- RF-027: O sistema deve permitir gerir utilizadores institucionais.
- RF-028: O sistema deve permitir atribuir e revogar papéis.
- RF-029: O sistema deve aplicar o isolamento institucional no backend.
- RF-030: O sistema deve disponibilizar o histórico das operações relevantes.
- RF-031: O sistema deve permitir configurar informações como nome institucional, logótipo, contactos e dados a incluir nos documentos emitidos.

### 4.4. Módulo D — Gestão de alunos, formandos e encarregados

| ID     | Requisito funcional                                                                               | Prioridade |
| ------ | ------------------------------------------------------------------------------------------------- | ---------- |
| RF-032 | O sistema deve permitir registar alunos e formandos.                                              | P0         |
| RF-033 | O sistema deve permitir actualizar os dados pessoais de acordo com as permissões.                 | P0         |
| RF-034 | O sistema deve permitir registar contactos e dados de emergência.                                 | P1         |
| RF-035 | O sistema deve permitir associar um ou vários encarregados a um aluno.                            | P0         |
| RF-036 | O sistema deve permitir que um encarregado esteja associado a vários alunos da mesma instituição. | P0         |
| RF-037 | O sistema deve permitir consultar o histórico académico da pessoa.                                | P1         |
| RF-038 | O sistema deve permitir registar documentos necessários ao processo educativo.                    | P1         |
| RF-039 | O sistema deve permitir pesquisar e filtrar registos.                                             | P0         |
| RF-040 | O sistema deve permitir desactivar registos sem eliminar o histórico necessário.                  | P0         |

O cadastro da pessoa deve ser independente da matrícula ou inscrição. Isso permite que o aluno continue a ter um único cadastro dentro da instituição ao longo de vários anos lectivos.

Os dados de menores devem ter acesso restrito. Documentos de identificação, contactos e outras informações pessoais não devem ser expostos desnecessariamente.

### 4.5. Módulo E — Inscrições e matrículas escolares

- RF-041: O sistema deve permitir configurar anos lectivos.
- RF-042: O sistema deve permitir configurar classes, turmas, turnos e salas.
- RF-043: O sistema deve permitir configurar disciplinas e planos curriculares.
- RF-044: O sistema deve permitir registar uma candidatura ou pedido de matrícula.
- RF-045: O sistema deve permitir validar os requisitos documentais da matrícula.
- RF-046: O sistema deve permitir aprovar ou rejeitar uma candidatura.
- RF-047: O sistema deve permitir atribuir o aluno a uma classe e turma.
- RF-048: O sistema deve gerar um identificador de matrícula único no âmbito definido.
- RF-049: O sistema deve permitir renovar a matrícula para um novo ano lectivo.
- RF-050: O sistema deve manter o histórico das matrículas anteriores.
- RF-051: O sistema deve impedir matrículas duplicadas para a mesma combinação de aluno, ano lectivo e contexto académico, salvo excepções explicitamente definidas.

A matrícula deverá ter um ciclo de vida próprio, por exemplo: `pending`, `approved`, `active`, `cancelled` e `completed`, com transições controladas.

### 4.6. Módulo F — Gestão de cursos de formação

| ID     | Requisito funcional                                                          | Prioridade |
| ------ | ---------------------------------------------------------------------------- | ---------- |
| RF-052 | O sistema deve permitir criar e actualizar cursos.                           | P0         |
| RF-053 | Cada curso deve permitir definir duração, descrição, pré-requisitos e preço. | P0         |
| RF-054 | O sistema deve permitir criar edições de um curso com datas próprias.        | P0         |
| RF-055 | O sistema deve permitir criar turmas e definir a sua capacidade.             | P0         |
| RF-056 | O sistema deve permitir atribuir formadores às edições e turmas.             | P0         |
| RF-057 | O sistema deve permitir inscrever formandos numa edição.                     | P0         |
| RF-058 | O sistema deve permitir acompanhar o estado da inscrição e da formação.      | P0         |
| RF-059 | O sistema deve permitir registar a conclusão de uma formação.                | P0         |
| RF-060 | O sistema deve permitir consultar o histórico de cursos de cada formando.    | P1         |

Um curso representa a definição do programa de formação; uma edição representa uma realização concreta desse curso. As inscrições devem estar associadas à edição, e não apenas ao catálogo geral.

### 4.7. Módulo G — Presenças e acompanhamento académico

- RF-061: O sistema deve permitir registar presenças e faltas.
- RF-062: O registo deve identificar a sessão, turma, data e utilizador responsável.
- RF-063: O sistema deve permitir consultar o histórico de assiduidade.
- RF-064: O sistema deve permitir registar avaliações e resultados, de acordo com o módulo educativo.
- RF-065: O sistema deve permitir gerar pautas e relatórios académicos, conforme as permissões.
- RF-066: O sistema deve permitir corrigir registos, mantendo o histórico das alterações relevantes.

Na escola, a avaliação deverá suportar disciplinas e períodos lectivos. No centro de formação, deverá suportar critérios associados ao curso ou módulo formativo.

### 4.8. Módulo H — Gestão financeira educativa

| ID     | Requisito funcional                                                                                  | Prioridade |
| ------ | ---------------------------------------------------------------------------------------------------- | ---------- |
| RF-067 | O sistema deve permitir configurar taxas, propinas e mensalidades.                                   | P0         |
| RF-068 | O sistema deve permitir gerar cobranças associadas a alunos, matrículas ou inscrições.               | P0         |
| RF-069 | O sistema deve permitir definir vencimentos e condições de pagamento.                                | P0         |
| RF-070 | O sistema deve permitir registar pagamentos manuais.                                                 | P0         |
| RF-071 | O sistema deve permitir pagamentos parciais.                                                         | P0         |
| RF-072 | O sistema deve permitir associar pagamentos a uma ou mais cobranças, segundo as regras configuradas. | P0         |
| RF-073 | O sistema deve calcular saldos pendentes.                                                            | P0         |
| RF-074 | O sistema deve emitir recibos com identificadores únicos.                                            | P0         |
| RF-075 | O sistema deve permitir consultar o histórico de pagamentos.                                         | P0         |
| RF-076 | O sistema deve permitir efectuar estornos ou anulações autorizadas.                                  | P0         |
| RF-077 | O sistema deve manter a identificação do operador que registou ou alterou uma operação financeira.   | P0         |
| RF-078 | O sistema deve gerar relatórios de cobranças, recebimentos e dívidas.                                | P0         |
| RF-079 | O sistema deve permitir configurar descontos e isenções.                                             | P1         |
| RF-080 | O sistema deve permitir a reconciliação dos registos de caixa.                                       | P1         |
| RF-081 | O sistema deve permitir integrar fornecedores de pagamentos electrónicos numa fase posterior.        | P2         |

Os recibos devem identificar claramente a instituição, o pagador ou beneficiário aplicável, a data, o valor, o método de pagamento e as cobranças liquidadas, conforme os requisitos legais e operacionais confirmados.

### 4.9. Módulo I — Portal dos encarregados de educação

- RF-082: O sistema deve permitir criar ou activar o acesso de um encarregado validado.
- RF-083: O encarregado deve poder consultar apenas os alunos a si associados.
- RF-084: O portal deve apresentar as informações académicas autorizadas.
- RF-085: O portal deve permitir consultar presenças e faltas.
- RF-086: O portal deve apresentar cobranças, pagamentos e saldos pendentes.
- RF-087: O portal deve permitir consultar ou descarregar recibos e documentos autorizados.
- RF-088: O sistema deve permitir consultar avisos publicados pela instituição.
- RF-089: O sistema deve permitir suspender o acesso sem apagar o histórico académico ou financeiro.

A associação entre encarregado e aluno deve ser validada pela instituição. Conhecer o número de matrícula ou o nome do aluno não deve permitir a terceiros obter acesso ao respectivo processo.

### 4.10. Módulo J — Certificados de formação

- RF-090: O sistema deve permitir configurar modelos de certificados.
- RF-091: O sistema deve permitir emitir certificados para formandos que satisfaçam os critérios de conclusão.
- RF-092: Cada certificado deve possuir um identificador único.
- RF-093: O sistema deve permitir gerar um documento digital para impressão ou consulta.
- RF-094: O sistema deve permitir verificar a autenticidade de um certificado através de um código ou endereço público de verificação.
- RF-095: O sistema deve impedir a emissão duplicada não autorizada para a mesma conclusão.
- RF-096: O sistema deve permitir anular um certificado, registando o motivo e mantendo o histórico.
- RF-097: O sistema deve permitir configurar os dados institucionais e formativos apresentados no certificado.

A verificação pública deve revelar apenas os dados necessários à confirmação da autenticidade. Não deve expor o processo académico completo do formando.

A emissão de certificados pelo sistema não garante, por si só, o reconhecimento oficial da formação. A utilização de expressões como certificado acreditado ou reconhecido deve depender da situação legal da instituição e do curso.

### 4.11. Módulo K — Relatórios e documentos

- RF-098: O sistema deve disponibilizar um dashboard institucional.
- RF-099: O dashboard deve apresentar indicadores relevantes ao tipo de instituição.
- RF-100: O sistema deve permitir filtrar relatórios por datas, turmas, cursos e estados.
- RF-101: O sistema deve permitir exportar relatórios autorizados para formatos adequados, como PDF e CSV.
- RF-102: O sistema deve permitir gerar listas de alunos, formandos e matrículas.
- RF-103: O sistema deve permitir gerar mapas de cobranças e recebimentos.
- RF-104: O sistema deve permitir consultar operações recentes e alterações relevantes.

Os indicadores do dashboard devem ser derivados dos dados operacionais, evitando guardar totais redundantes sem uma estratégia de actualização consistente.

### 4.12. Módulo L — Auditoria e suporte

- RF-105: O sistema deve registar operações críticas, como alterações de permissões, decisões de aprovação e operações financeiras.
- RF-106: O registo deve incluir o actor, a data, a operação, o recurso afectado e o contexto institucional.
- RF-107: O sistema deve permitir consultar os registos de auditoria segundo as permissões.
- RF-108: O administrador central deve poder apoiar instituições sem obter acesso irrestrito e permanente aos seus dados.
- RF-109: O acesso de suporte a dados institucionais, quando necessário, deve ser autorizado, limitado e auditado.

## 5. Requisitos não funcionais

Os requisitos não funcionais definem as qualidades e restrições técnicas da solução.

| ID      | Requisito               | Critério ou orientação                                                                             |
| ------- | ----------------------- | -------------------------------------------------------------------------------------------------- |
| RNF-001 | Segurança               | Aplicar autenticação, autorização e validação no servidor.                                         |
| RNF-002 | Isolamento multi-tenant | Impedir acessos cruzados entre instituições.                                                       |
| RNF-003 | Integridade financeira  | Garantir consistência transaccional dos pagamentos e das cobranças.                                |
| RNF-004 | Privacidade             | Restringir o tratamento e a exposição dos dados pessoais.                                          |
| RNF-005 | Responsividade          | Disponibilizar uma interface utilizável em computador, tablet e telemóvel.                         |
| RNF-006 | Desempenho              | Estabelecer metas de resposta e medi-las com testes representativos.                               |
| RNF-007 | Disponibilidade         | Definir objectivos de disponibilidade compatíveis com o orçamento e o ambiente de alojamento.      |
| RNF-008 | Recuperação             | Implementar cópias de segurança e testar a recuperação.                                            |
| RNF-009 | Manutenibilidade        | Organizar o código por módulos de negócio e responsabilidades.                                     |
| RNF-010 | Testabilidade           | Cobrir regras críticas com testes automatizados.                                                   |
| RNF-011 | Auditabilidade          | Preservar o histórico das operações críticas.                                                      |
| RNF-012 | Usabilidade             | Utilizar linguagem clara, formulários simples e mensagens de erro úteis.                           |
| RNF-013 | Compatibilidade         | Suportar navegadores modernos, com testes nos ambientes prioritários.                              |
| RNF-014 | Escalabilidade          | Permitir aumentar recursos e optimizar consultas sem redesenhar todo o sistema.                    |
| RNF-015 | Observabilidade         | Disponibilizar logs de aplicação, monitorização de erros e métricas operacionais.                  |
| RNF-016 | Acessibilidade          | Adoptar boas práticas de acessibilidade para navegação, formulários, contraste e leitores de ecrã. |
| RNF-017 | Segurança documental    | Impedir o acesso público directo a documentos privados.                                            |
| RNF-018 | Portabilidade           | Permitir implantação em ambientes compatíveis com a arquitectura escolhida.                        |

### 5.1. Metas iniciais mensuráveis

As metas abaixo são propostas para orientar testes, não resultados já garantidos.

- Respostas de leitura comuns: objectivo inicial de p95 inferior a 500 ms sob uma carga de referência definida, excluindo operações externas e geração de documentos pesados.
- Integridade financeira: nenhuma duplicação de pagamento em cenários de concorrência e repetição de pedidos testados.
- Isolamento institucional: 100% dos testes de autorização entre tenants devem passar.
- Recuperação: definir um objectivo inicial de perda máxima de dados (RPO) e tempo de recuperação (RTO) antes do piloto.
- Testes automatizados: cobrir obrigatoriamente aprovação institucional, permissões, matrículas, cobranças, pagamentos, estornos e certificados.

As metas de desempenho deverão ser revistas após os primeiros testes de carga e a escolha da infraestrutura de produção.

## 6. Regras de negócio

As regras de negócio representam condições que devem ser respeitadas independentemente da interface utilizada. Devem ser aplicadas no backend e protegidas por testes automatizados.

### 6.1. Instituições e subscrições

- RN-001: Uma instituição só pode utilizar as funcionalidades operacionais depois de aprovada e activada.
- RN-002: A aprovação deve ser realizada por um administrador autorizado.
- RN-003: A aprovação e a situação comercial da subscrição são estados independentes.
- RN-004: Uma instituição suspensa não pode executar operações incompatíveis com o estado da sua conta.
- RN-005: Cada instituição deve ter uma subscrição identificável e um plano aplicável.
- RN-006: Os limites do plano devem ser aplicados no backend.
- RN-007: Alterações de plano não podem apagar o histórico da instituição.
- RN-008: A suspensão por falta de pagamento da subscrição deve seguir a política comercial definida e não deve eliminar os dados do cliente.

### 6.2. Isolamento e acesso

- RN-009: Todos os recursos institucionais devem pertencer a uma instituição identificável.
- RN-010: Um utilizador só pode operar sobre recursos institucionais aos quais tenha acesso autorizado.
- RN-011: O identificador de uma instituição enviado pelo frontend nunca deve ser considerado prova suficiente de autorização.
- RN-012: Os documentos, exportações e downloads devem respeitar as mesmas regras de isolamento dos endpoints normais.
- RN-013: Acesso excepcional de suporte deve ser controlado e auditado.

### 6.3. Alunos e matrículas

- RN-014: O cadastro de uma pessoa deve ser independente das suas matrículas.
- RN-015: Uma matrícula deve estar associada a uma instituição, ano lectivo, classe e turma válidos.
- RN-016: A renovação da matrícula deve criar um novo registo histórico, sem substituir o anterior.
- RN-017: Uma matrícula não pode ser considerada aprovada apenas porque os dados foram submetidos.
- RN-018: A atribuição de uma turma deve respeitar a capacidade e as regras definidas pela instituição.
- RN-019: A eliminação física de matrículas com histórico académico ou financeiro associado deve ser restringida.

### 6.4. Cursos e formandos

- RN-020: Cada edição de curso pertence a um curso definido pela instituição.
- RN-021: Cada inscrição deve estar associada a uma edição concreta.
- RN-022: Uma edição encerrada não deve aceitar novas inscrições, salvo autorização explícita.
- RN-023: A emissão de certificado depende dos critérios de conclusão definidos para a formação.
- RN-024: A alteração de um curso não deve modificar silenciosamente o histórico das edições já realizadas.

### 6.5. Operações financeiras

- RN-025: Os valores monetários devem ser armazenados sem erros de arredondamento associados a números de vírgula flutuante.
- RN-026: Uma cobrança deve pertencer a uma instituição e estar associada ao beneficiário e ao contexto educativo aplicável.
- RN-027: Um pagamento confirmado deve ter um identificador único dentro do seu contexto.
- RN-028: A repetição de um pedido não pode provocar a duplicação de um pagamento.
- RN-029: O valor alocado a uma cobrança não pode exceder o saldo disponível do pagamento nem o saldo em dívida da cobrança, salvo se existir uma política explícita para créditos ou adiantamentos.
- RN-030: Pagamentos parciais devem manter o saldo restante correctamente calculado.
- RN-031: Um pagamento confirmado não pode ser apagado silenciosamente.
- RN-032: Estornos e anulações devem exigir autorização e motivo.
- RN-033: Os relatórios financeiros devem distinguir cobranças emitidas, recebimentos confirmados, valores pendentes e operações estornadas.
- RN-034: O registo de um comprovativo não equivale à confirmação de recebimento.
- RN-035: Os recibos devem possuir identificadores únicos e preservar a ligação ao pagamento original.
- RN-036: Os pagamentos educativos e as subscrições comerciais do SaaS devem ser tratados como domínios financeiros separados.

### 6.6. Encarregados e certificados

- RN-037: Um encarregado só pode consultar os alunos cuja associação tenha sido validada.
- RN-038: A revogação de uma associação deve impedir novos acessos através dessa relação.
- RN-039: A emissão de um certificado deve ser registada e rastreável.
- RN-040: A anulação de um certificado não deve apagar o registo da emissão.
- RN-041: A verificação pública de certificados deve disponibilizar apenas os dados estritamente necessários.
- RN-042: Um certificado emitido pelo sistema não deve ser apresentado como oficialmente reconhecido sem fundamento aplicável.

## 7. Modelo conceptual de dados

A modelação deve começar pelas entidades do domínio e pelas suas relações, antes da criação das migrations.

### 7.1. Entidades centrais

| Entidade                             | Finalidade                                                                  |
| ------------------------------------ | --------------------------------------------------------------------------- |
| `institutions`                       | Identificação e configuração das instituições.                              |
| `institution_applications`           | Pedidos de adesão e decisões administrativas.                               |
| `users`                              | Contas de autenticação.                                                     |
| `institution_user`                   | Associação de utilizadores a instituições.                                  |
| `roles` e `permissions`              | Papéis e permissões.                                                        |
| `plans`                              | Planos comerciais do SaaS.                                                  |
| `subscriptions`                      | Subscrições de cada instituição.                                            |
| `people` ou entidades equivalentes   | Dados comuns de pessoas, conforme o desenho adoptado.                       |
| `students`                           | Perfil educativo dos alunos e formandos, se for separado do cadastro comum. |
| `guardians`                          | Perfil dos encarregados de educação.                                        |
| `student_guardian`                   | Associação entre alunos e encarregados.                                     |
| `school_years`                       | Anos lectivos.                                                              |
| `classes`                            | Classes escolares.                                                          |
| `classrooms`                         | Turmas e grupos lectivos.                                                   |
| `subjects`                           | Disciplinas.                                                                |
| `enrollments`                        | Matrículas escolares.                                                       |
| `courses`                            | Catálogo de cursos.                                                         |
| `course_editions`                    | Realizações concretas dos cursos.                                           |
| `training_enrollments`               | Inscrições em edições de cursos.                                            |
| `attendance_records`                 | Registos de presença.                                                       |
| `assessments` e `assessment_results` | Avaliações e resultados.                                                    |
| `charges`                            | Cobranças emitidas.                                                         |
| `payments`                           | Pagamentos registados.                                                      |
| `payment_allocations`                | Distribuição dos pagamentos pelas cobranças.                                |
| `receipts`                           | Recibos emitidos.                                                           |
| `cash_registers`                     | Caixas e sessões de caixa.                                                  |
| `certificates`                       | Certificados emitidos.                                                      |
| `documents`                          | Metadados de documentos armazenados.                                        |
| `notifications`                      | Avisos e notificações.                                                      |
| `audit_logs`                         | Registos de auditoria.                                                      |

Esta lista é conceptual. Não significa que cada item deva corresponder obrigatoriamente a uma tabela independente. A modelação final deve evitar duplicação desnecessária e relações polimórficas difíceis de proteger.

### 7.2. Relações principais

\#chatgpt-mermaid-\_r_h3\_{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;fill:rgb(237, 237, 237);}@keyframes edge-animation-frame{from{stroke-dashoffset:0;}}@keyframes dash{to{stroke-dashoffset:0;}}#chatgpt-mermaid-\_r_h3\_ .edge-animation-slow{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 50s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_h3\_ .edge-animation-fast{stroke-dasharray:9,5!important;stroke-dashoffset:900;animation:dash 20s linear infinite;stroke-linecap:round;}#chatgpt-mermaid-\_r_h3\_ .error-icon{fill:rgb(48, 48, 48);}#chatgpt-mermaid-\_r_h3\_ .error-text{fill:rgb(237, 237, 237);stroke:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_h3\_ .edge-thickness-normal{stroke-width:1px;}#chatgpt-mermaid-\_r_h3\_ .edge-thickness-thick{stroke-width:3.5px;}#chatgpt-mermaid-\_r_h3\_ .edge-pattern-solid{stroke-dasharray:0;}#chatgpt-mermaid-\_r_h3\_ .edge-thickness-invisible{stroke-width:0;fill:none;}#chatgpt-mermaid-\_r_h3\_ .edge-pattern-dashed{stroke-dasharray:3;}#chatgpt-mermaid-\_r_h3\_ .edge-pattern-dotted{stroke-dasharray:2;}#chatgpt-mermaid-\_r_h3\_ .marker{fill:rgb(175, 175, 175);stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_h3\_ .marker.cross{stroke:rgb(175, 175, 175);}#chatgpt-mermaid-\_r_h3\_ svg{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";font-size:16px;}#chatgpt-mermaid-\_r_h3\_ p{margin:0;}#chatgpt-mermaid-\_r_h3\_ .entityBox{fill:rgb(9, 23, 44);stroke:rgb(31, 78, 148);}#chatgpt-mermaid-\_r_h3\_ .relationshipLabelBox{fill:rgb(48, 48, 48);opacity:0.7;background-color:rgb(48, 48, 48);}#chatgpt-mermaid-\_r_h3\_ .relationshipLabelBox rect{opacity:0.5;}#chatgpt-mermaid-\_r_h3\_ .labelBkg{background-color:rgba(48, 48, 48, 0.5);}#chatgpt-mermaid-\_r_h3\_ .edgeLabel{background-color:rgb(0, 0, 0);}#chatgpt-mermaid-\_r_h3\_ .edgeLabel .label rect{fill:rgb(0, 0, 0);}#chatgpt-mermaid-\_r_h3\_ .edgeLabel .label text{fill:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_h3\_ .edgeLabel .label{fill:rgb(31, 78, 148);font-size:14px;}#chatgpt-mermaid-\_r_h3\_ .label{font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";color:rgb(237, 237, 237);}#chatgpt-mermaid-\_r_h3\_ .edge-pattern-dashed{stroke-dasharray:8,8;}#chatgpt-mermaid-\_r_h3\_ .node rect,#chatgpt-mermaid-\_r_h3\_ .node circle,#chatgpt-mermaid-\_r_h3\_ .node ellipse,#chatgpt-mermaid-\_r_h3\_ .node polygon{fill:rgb(9, 23, 44);stroke:rgb(31, 78, 148);stroke-width:1px;}#chatgpt-mermaid-\_r_h3\_ .relationshipLine{stroke:rgb(175, 175, 175);stroke-width:1px;fill:none;}#chatgpt-mermaid-\_r_h3\_ .marker{fill:none!important;stroke:rgb(175, 175, 175)!important;stroke-width:1;}#chatgpt-mermaid-\_r_h3\_ [data-look=neo].labelBkg{background-color:rgba(48, 48, 48, 0.5);}#chatgpt-mermaid-\_r_h3\_ .node .neo-node{stroke:rgb(31, 78, 148);}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].node rect,#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].cluster rect,#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].node polygon{stroke:url(#chatgpt-mermaid-\_r_h3\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].swimlane.cluster rect{filter:none;}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].node path{stroke:url(#chatgpt-mermaid-\_r_h3\_-gradient);stroke-width:1px;}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].node .outer-path{filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].node .neo-line path{stroke:rgb(31, 78, 148);filter:none;}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].node circle{stroke:url(#chatgpt-mermaid-\_r_h3\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].node circle .state-start{fill:#000000;}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].icon-shape .icon{fill:url(#chatgpt-mermaid-\_r_h3\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_h3\_ [data-look="neo"].icon-shape .icon-neo path{stroke:url(#chatgpt-mermaid-\_r_h3\_-gradient);filter:drop-shadow( 1px 2px 2px rgba(185,185,185,1));}#chatgpt-mermaid-\_r_h3\_ :root{--mermaid-font-family:-apple-system-body,ui-sans-serif,-apple-system,system-ui,"Segoe UI",Helvetica,"Apple Color Emoji",Arial,sans-serif,"Segoe UI Emoji","Segoe UI Symbol";}INSTITUTIONINSTITUTION_USERUSERSUBSCRIPTIONPLANSTUDENTENROLLMENTSCHOOL_YEARCLASSROOMSTUDENT_GUARDIANGUARDIANCOURSECOURSE_EDITIONTRAINING_ENROLLMENTCHARGEPAYMENT_ALLOCATIONPAYMENTCERTIFICATEhasbelongs_tohasdefinesregistershascontainsreceiveslinkedresponsible_foroffershasacceptsjoinsbilledsettled_byallocatesmay_generate

O diagrama representa relações conceptuais. A implementação deverá considerar as chaves estrangeiras, os limites institucionais, a unicidade dos identificadores e os diferentes ciclos de vida.

### 7.3. Decisões de modelação importantes

Separação entre pessoa, utilizador e aluno. Uma pessoa pode ter um registo académico sem possuir uma conta para iniciar sessão. Um encarregado pode ter uma conta e estar associado a vários alunos. A conta de autenticação não deve ser confundida com o perfil académico.

Separação entre cobranças, pagamentos e alocações. Uma cobrança pode ser paga em várias parcelas. Um pagamento pode, de acordo com as regras da instituição, liquidar várias cobranças. A tabela de alocações permite representar esta relação sem duplicar os valores recebidos.

Separação entre curso e edição. O catálogo de cursos não deve ser usado como se fosse uma turma concreta.

Separação entre matrícula e inscrição em formação. Embora partilhem algumas informações, os dois processos possuem regras e ciclos de vida distintos.

Isolamento institucional. Todas as relações relevantes devem permitir demonstrar que os registos relacionados pertencem ao mesmo tenant. A aplicação deve verificar essa consistência no servidor e, quando apropriado, também através de restrições da base de dados.

## 8. Arquitectura técnica proposta

A arquitectura inicial será um monólito modular: uma API Laravel organizada por domínios, uma aplicação React e uma base de dados PostgreSQL.

Esta opção é adequada a um projecto desenvolvido individualmente, porque reduz a complexidade de implantação e permite manter as fronteiras de negócio claras.

### 8.1. Tecnologias

| Componente                   | Tecnologia                                                      |
| ---------------------------- | --------------------------------------------------------------- |
| Backend                      | Laravel API                                                     |
| Linguagem do backend         | PHP                                                             |
| Frontend                     | React + TypeScript                                              |
| Ferramenta de build frontend | Vite                                                            |
| Base de dados                | PostgreSQL                                                      |
| Autenticação                 | Laravel Sanctum, conforme o modelo de sessão escolhido          |
| Autorização                  | Policies, Gates e permissões por papel                          |
| Filas                        | Laravel Queue; Redis quando necessário                          |
| Testes backend               | Pest ou PHPUnit                                                 |
| Testes frontend              | Vitest e React Testing Library                                  |
| Testes de fluxos críticos    | Playwright, numa fase adequada                                  |
| Estilos                      | Tailwind CSS ou alternativa equivalente                         |
| API                          | REST, com documentação OpenAPI                                  |
| Infraestrutura               | Docker e serviços de produção geridos                           |
| Armazenamento documental     | Object storage privado compatível com S3 ou solução equivalente |

As versões exactas deverão ser fixadas no início da implementação, com base na compatibilidade e no suporte disponível.

### 8.2. Estrutura sugerida do backend

```

app/
├── Modules/
│   ├── Platform/
│   │   ├── Institutions/
│   │   ├── Applications/
│   │   └── Subscriptions/
│   ├── Identity/
│   │   ├── Authentication/
│   │   ├── Users/
│   │   └── Permissions/
│   ├── Education/
│   │   ├── Students/
│   │   ├── Guardians/
│   │   ├── SchoolYears/
│   │   ├── Enrollments/
│   │   └── Attendance/
│   ├── Schools/
│   │   ├── Classes/
│   │   ├── Subjects/
│   │   └── Assessments/
│   ├── Training/
│   │   ├── Courses/
│   │   ├── Editions/
│   │   └── Certificates/
│   ├── Finance/
│   │   ├── Charges/
│   │   ├── Payments/
│   │   ├── Receipts/
│   │   └── CashRegisters/
│   ├── Reporting/
│   ├── Communication/
│   └── Audit/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
└── Providers/

```

A estrutura é indicativa. No Laravel, não precisas de criar uma camada ou classe para cada conceito. Controllers devem coordenar pedidos; Form Requests validam a entrada; Policies autorizam operações; Actions ou Services implementam operações de negócio que justifiquem essa separação.

### 8.3. Estrutura sugerida do frontend React

```

src/
├── app/
│   ├── router/
│   ├── providers/
│   └── layouts/
├── modules/
│   ├── auth/
│   ├── platform-admin/
│   ├── institutions/
│   ├── subscriptions/
│   ├── students/
│   ├── guardians/
│   ├── enrollments/
│   ├── schools/
│   ├── training/
│   ├── finance/
│   ├── certificates/
│   └── reports/
├── components/
│   ├── ui/
│   ├── forms/
│   └── data-display/
├── services/
│   ├── api/
│   └── files/
├── hooks/
├── types/
└── utils/

```

Cada módulo deve agrupar páginas, componentes, tipos, validações e chamadas de API relacionados ao seu domínio. Os componentes partilhados devem conter elementos realmente reutilizáveis, como tabelas, formulários, modais e indicadores de estado.

Para a gestão do estado remoto, uma ferramenta como TanStack Query pode ser útil. Não é necessário introduzir uma biblioteca global de estado para todos os dados se os dados do servidor e o estado local forem suficientes.

### 8.4. Multi-tenancy

A estratégia inicial recomendada é uma base de dados partilhada, com isolamento lógico por `institution_id`.

Regras de implementação:

1. Determinar o contexto institucional a partir de uma associação autorizada do utilizador.
2. Aplicar o contexto no backend, não apenas através de filtros no frontend.
3. Utilizar Policies e consultas limitadas ao tenant.
4. Validar as relações entre recursos antes de alterar ou eliminar dados.
5. Aplicar o isolamento também em tarefas de fila, relatórios, documentos e exportações.
6. Implementar testes que tentem aceder a recursos de outras instituições.
7. Avaliar Row-Level Security no PostgreSQL como defesa adicional, sem a tratar como substituto de uma autorização correcta na aplicação.

### 8.5. Autenticação e comunicação entre React e Laravel

Para uma aplicação web própria, com frontend e backend sob domínios devidamente configurados, Laravel Sanctum com autenticação por sessão e cookies seguros é uma opção adequada.

Se a implantação exigir origens diferentes, deverá ser configurado correctamente o CORS, o domínio dos cookies, a protecção CSRF e os atributos `Secure`, `HttpOnly` e `SameSite`, conforme o cenário.

Não se deve armazenar tokens de longa duração em `localStorage` sem uma avaliação de segurança apropriada.

### 8.6. Operações financeiras e concorrência

As operações financeiras devem utilizar transacções da base de dados, restrições de integridade e mecanismos contra repetição de pedidos.

Por exemplo, ao confirmar um pagamento, o sistema deverá:

1. Validar a instituição e as permissões do operador.
2. Validar o pagamento e as cobranças associadas.
3. Verificar a chave de idempotência ou outro mecanismo contra duplicação.
4. Bloquear ou proteger as linhas relevantes quando necessário.
5. Registar o pagamento e as alocações numa transacção.
6. Confirmar a operação apenas quando todas as alterações forem consistentes.
7. Emitir o recibo e desencadear tarefas posteriores sem criar duplicações.

## 9. Experiência de utilização e navegação

A aplicação deverá ter uma interface comum, adaptada ao tipo de instituição e ao perfil do utilizador.

### 9.1. Áreas da aplicação

Área pública

- Página de apresentação.
- Planos e funcionalidades.
- Pedido de adesão.
- Consulta do estado do pedido, quando autenticado.
- Autenticação e recuperação de acesso.

Administração SaaS

- Dashboard global.
- Pedidos de adesão.
- Instituições.
- Planos e subscrições.
- Registos de auditoria.
- Suporte.

Área institucional

- Dashboard.
- Alunos e encarregados.
- Matrículas ou inscrições.
- Turmas, classes ou cursos.
- Presenças e avaliações.
- Financeiro.
- Certificados, quando aplicável.
- Relatórios.
- Utilizadores e configurações.

Portal do encarregado

- Alunos associados.
- Notas e presenças.
- Cobranças e recibos.
- Avisos.
- Documentos autorizados.

### 9.2. Requisitos de usabilidade

- A navegação deve adaptar-se às permissões.
- Os formulários devem apresentar validações claras.
- As tabelas devem permitir pesquisa, paginação e filtragem.
- As operações críticas devem exigir confirmação adequada.
- Os erros financeiros devem explicar o problema sem expor detalhes internos.
- As telas devem ser utilizáveis em ecrãs pequenos.
- Os estados de carregamento, sucesso, ausência de dados e erro devem ser consistentes.

## 10. MVP e critérios de conclusão

O MVP deve permitir que uma escola e um centro de formação utilizem a plataforma em processos reais, sem depender de funcionalidades futuras para concluir as operações principais.

### 10.1. Entregas do MVP

| Entrega            | Incluído                                                  |
| ------------------ | --------------------------------------------------------- |
| Administração SaaS | Registo, aprovação e gestão de instituições               |
| Subscrições        | Planos, limites e registo manual de pagamentos comerciais |
| Identidade         | Autenticação, utilizadores e permissões                   |
| Isolamento         | Separação dos dados por instituição                       |
| Escolas            | Anos lectivos, classes, turmas, alunos e matrículas       |
| Formação           | Cursos, edições, formandos e inscrições                   |
| Presenças          | Registo e consulta de assiduidade                         |
| Financeiro         | Cobranças, pagamentos manuais, recibos e dívidas          |
| Encarregados       | Portal de consulta com acesso controlado                  |
| Certificados       | Emissão e verificação de certificados de formação         |
| Relatórios         | Indicadores básicos e exportações essenciais              |
| Operação           | Auditoria, backups e monitorização                        |

### 10.2. Critérios de aceitação do MVP

O MVP estará pronto para um piloto quando:

- Uma instituição puder solicitar adesão e ser aprovada.
- Uma instituição aprovada puder configurar os seus dados e utilizadores.
- Os utilizadores não conseguirem aceder aos dados de outra instituição.
- Uma escola puder matricular um aluno e manter o histórico dos anos lectivos.
- Um centro puder criar um curso, uma edição e inscrever formandos.
- Os dois módulos puderem registar presenças.
- O sistema puder gerar cobranças e registar pagamentos parciais e totais.
- Os recibos e saldos forem consistentes após pagamentos e estornos.
- Um encarregado puder consultar apenas os alunos que lhe estão associados.
- Um centro puder emitir e verificar um certificado autorizado.
- As operações críticas tiverem testes automatizados.
- A recuperação de backups tiver sido testada.
- A instituição piloto puder concluir os processos principais sem intervenção técnica diária do desenvolvedor.

### 10.3. Estratégia de implementação

Recomendo desenvolver primeiro o núcleo comum e, depois, implementar os dois módulos educativos em incrementos. Embora o lançamento comercial abranja os dois segmentos, não é necessário terminar todos os módulos antes de começar a testar os fluxos mais importantes.

Uma sequência possível:

1. Fundação do projecto, autenticação e multi-tenancy.
2. Adesão, aprovação, planos e subscrições.
3. Cadastros, permissões e gestão documental.
4. Matrículas escolares e inscrições em cursos.
5. Cobranças, pagamentos e recibos.
6. Presenças e avaliações essenciais.
7. Portal dos encarregados.
8. Certificados de formação.
9. Relatórios, testes de carga, segurança e piloto.

## 11. Riscos e medidas de mitigação

| Risco                              | Medida de mitigação                                          |
| ---------------------------------- | ------------------------------------------------------------ |
| Acesso cruzado entre instituições  | Policies, contexto institucional e testes de isolamento      |
| Pagamentos duplicados              | Idempotência, transacções e restrições de integridade        |
| Crescimento excessivo do MVP       | Prioridades explícitas e exclusão de funcionalidades futuras |
| Complexidade dos dois segmentos    | Núcleo comum com módulos de negócio separados                |
| Dados pessoais de menores expostos | Permissões restritas, armazenamento privado e auditoria      |
| Perda de dados                     | Backups automáticos e testes de recuperação                  |
| Subscrições mal aplicadas          | Estados separados e regras explícitas de acesso              |
| Baixa adesão comercial             | Entrevistas, piloto e validação de preços                    |
| Dependência do desenvolvedor       | Testes, documentação e automatização da implantação          |
| Requisitos legais desconhecidos    | Validação jurídica e operacional antes do lançamento         |

## 12. Indicadores de sucesso

O sucesso do produto deverá ser medido por indicadores de negócio e de operação, e não apenas pelo número de funcionalidades implementadas.

### Indicadores comerciais

- Número de instituições que solicitaram adesão.
- Percentagem de pedidos aprovados que se tornam clientes activos.
- Número de instituições com subscrição paga.
- Taxa de renovação e cancelamento.
- Receita recorrente mensal.
- Custo de aquisição e custo de suporte por instituição.

### Indicadores operacionais

- Número de matrículas e inscrições processadas.
- Percentagem de pagamentos registados sem correcção posterior.
- Tempo necessário para concluir uma matrícula.
- Tempo necessário para apurar cobranças e dívidas.
- Frequência de utilização do portal dos encarregados.
- Número de incidentes de segurança ou inconsistências financeiras.

As metas numéricas devem ser definidas depois de existir uma linha de base obtida com instituições piloto.

## 13. Decisões ainda por fechar

Antes de começar a implementação, convém decidir os seguintes pontos:

1. Se uma pessoa pode ter um único perfil comum de aluno/formando ou se os perfis serão totalmente separados.
2. Se uma instituição pode ter várias unidades, filiais ou instalações.
3. Se o registo de pagamentos será sempre confirmado manualmente ou se haverá estados de validação e aprovação.
4. Como serão definidos os limites dos planos comerciais.
5. Se o portal dos encarregados permitirá apenas consulta ou também pedidos de matrícula e carregamento de documentos.
6. Quais serão os documentos e critérios de aprovação de cada tipo de instituição.
7. Quais os requisitos legais e fiscais aplicáveis aos recibos e certificados emitidos.
8. Qual será a política de conservação, exportação e eliminação de dados quando uma instituição terminar a subscrição.

Estas decisões devem ser documentadas antes de se tornarem dependências estruturais do código.

## 14. Recomendação final

A decisão mais importante nesta fase é construir uma plataforma com um núcleo comum robusto e dois domínios educativos independentes, mantendo o isolamento dos dados e a integridade financeira como requisitos fundamentais.

Para o desenvolvimento individual, eu priorizaria nesta ordem:

1. Modelação do domínio: entidades, relações, estados e regras de negócio.
2. Multi-tenancy e permissões: garantir que a estrutura é segura antes de introduzir dados reais.
3. Adesão e subscrições: estabelecer o modelo comercial.
4. Matrículas e inscrições: implementar os processos específicos de cada segmento.
5. Financeiro: cobranças, pagamentos, alocações, recibos e estornos.
6. Encarregados e certificados: implementar os dois diferenciais que definiste.
7. Piloto: validar os fluxos com instituições reais antes de expandir.

