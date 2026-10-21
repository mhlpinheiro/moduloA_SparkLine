# SENN AI-X — Gestão Logística

Aplicação PHP + MySQL para gestão de operações logísticas, desenvolvida para o SENAI DEV EXPERIENCE — Módulo A 2026. Esta versão consolida correções e funcionalidades operacionais **sem incluir a implementação da integração externa de IA/API**.

## Requisitos locais

- XAMPP com Apache, PHP 8+ e MySQL/MariaDB.
- Extensão PHP `pdo_mysql` habilitada.
- Navegador atualizado.

## Instalação no XAMPP

1. Faça uma cópia da pasta atual e dos dados do banco.
2. Extraia `moduloA_SENN_AI_X` para `C:\xampp\htdocs\moduloA_SENN_AI_X`.
3. Inicie Apache e MySQL no painel do XAMPP.
4. Abra `http://localhost/phpmyadmin`.
5. Se o banco `senn_logistica_db` ainda não existir, importe `database.sql`. **O script SQL contém DROP DATABASE e apaga o banco existente. Nunca o reimporte sobre dados reais sem backup.**
6. Confira usuário, senha e banco em `conexao.php`.
7. Abra `http://localhost/moduloA_SENN_AI_X/login.php`.
8. O SQL de demonstração inclui contas de teste. Se você quiser começar sem os dados de demonstração, use um banco vazio e crie o primeiro administrador pela tela de cadastro.

## Contas de demonstração

O script SQL informa que as contas de teste usam a senha `password`. Altere/remova essas contas antes de qualquer implantação pública.

## Funcionalidades incluídas nesta entrega

- Autenticação por sessão, perfis e cadastro de usuários.
- Gestão de frota, motoristas, pedidos/entregas, roteirização e comprovantes POD.
- Rastreamento operacional e alertas.
- Proteção CSRF adicionada aos formulários POST de páginas tradicionais.
- Relatórios operacionais com exportação CSV de entregas.
- Exportação manual de backup JSON restrita ao administrador.
- Documentação de instalação, teste e checklist de validação.

## Integração de IA/API

**Fora do escopo desta versão**, conforme solicitado. As páginas que já fazem referência à integração anterior podem precisar de configuração ou ajuste para funcionar sem provedor externo. Não há garantia de respostas de IA nesta entrega.

## Teste rápido

1. Execute `php -l nome_do_arquivo.php` nos arquivos PHP ou use o checklist em `CHECKLIST_TESTES.md`.
2. Confirme login, logout e permissões por perfil.
3. Cadastre um veículo, motorista e entrega de teste.
4. Teste importação CSV com uma cópia de arquivo de exemplo.
5. Gere alertas, abra relatórios e baixe um backup JSON.
6. Verifique no phpMyAdmin se os registros foram gravados corretamente.

## Segurança e limites

Esta entrega foi verificada quanto à sintaxe PHP, mas não foi possível executar uma bateria de testes de integração contra o seu MySQL local. Revise especialmente permissões por perfil, dados reais, fluxos de upload, configuração HTTPS e recuperação do backup antes de disponibilizar publicamente. Os backups contêm dados pessoais e devem ser armazenados com acesso restrito.
