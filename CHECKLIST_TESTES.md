# Checklist de validação manual — SENN AI-X

## Antes de testar
- [ ] Fazer backup do banco atual.
- [ ] Confirmar Apache/MySQL ativos no XAMPP.
- [ ] Confirmar `pdo_mysql` habilitado.
- [ ] Confirmar que `conexao.php` aponta para `senn_logistica_db`.
- [ ] Não executar `database.sql` sobre banco com dados importantes (o script recria o banco).

## Autenticação e segurança
- [ ] Entrar com conta válida e verificar redirecionamento ao painel.
- [ ] Testar senha incorreta.
- [ ] Sair pelo botão de logout.
- [ ] Verificar que visitante não abre páginas protegidas.
- [ ] Verificar que usuário não administrador não abre telas exclusivas de administrador.
- [ ] Enviar formulário sem token CSRF e confirmar rejeição.
- [ ] Confirmar que HTML inserido em campos aparece escapado, não executado.

## Operação logística
- [ ] Cadastrar veículo com placa única.
- [ ] Cadastrar motorista e associar veículo.
- [ ] Cadastrar entrega manual.
- [ ] Importar um CSV de teste e verificar duplicados/linhas inválidas.
- [ ] Executar roteirização com dados de teste.
- [ ] Registrar comprovante de entrega.
- [ ] Abrir rastreamento e conferir status das entregas.
- [ ] Marcar veículo em manutenção e gerar alertas como administrador.

## Relatórios e backup
- [ ] Abrir `relatorios.php` e comparar totais com o banco.
- [ ] Exportar CSV e abrir no Excel/LibreOffice.
- [ ] Baixar backup JSON como administrador.
- [ ] Guardar o backup fora da pasta pública e verificar se contém dados sensíveis.

## Não incluído nesta entrega
- [ ] Integração externa de IA/API (explicitamente excluída pelo solicitante).
- [ ] Teste automatizado de integração com o banco local do usuário.
- [ ] Validação de implantação em HTTPS/servidor de produção.
