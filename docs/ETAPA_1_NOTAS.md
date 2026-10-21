# Etapa 1 — Fundação e segurança

## Aplicado nesta versão
- Corrigido o nome do arquivo de segurança para `seguranca.php`, que era armazenado com nome incompatível com os `require_once` usados no código.
- Centralizada a inicialização de sessão com modo estrito, cookies HttpOnly e SameSite=Lax; o cookie Secure é ativado quando a aplicação roda em HTTPS.
- Adicionada validação CSRF ao login, cadastro inicial e logout; logout agora exige POST.
- Bloqueado o cadastro público depois que o primeiro administrador existe. Novas contas devem ser criadas pelo administrador.
- Elevada a senha mínima para 8 caracteres em novos cadastros.
- A chave da Hugging Face agora é lida apenas de `HF_API_TOKEN`; não há valor de token padrão no código.
- Criado `.gitignore` com nome correto e regras para segredos e arquivos locais.
- Criado `README.md` com instalação para XAMPP e configuração de ambiente.

## Validação executada
- `php -l` foi executado em todos os arquivos PHP da raiz: todos passaram na verificação de sintaxe.
- Não foram executados testes integrados contra o MySQL nem chamadas à API externa nesta sessão. Teste no XAMPP antes de substituir a pasta usada no dia a dia.

## Pendências de segurança para próxima etapa
- Adicionar proteção CSRF a todos os formulários que alteram dados (veículos, motoristas, pedidos, manutenções, movimentações, etc.).
- Revisar autorização por perfil e propriedade dos registros em cada endpoint.
- Rever validação/escape de saída e upload de arquivos em todos os módulos.
- Configurar HTTPS e testar fluxo completo de sessão em ambiente de produção.
- Implementar e testar backup e restauração do banco.

## Importante para o repositório GitHub já existente
O `.gitignore` impede que novos arquivos sejam adicionados por engano, mas não remove arquivos que já estão rastreados pelo Git. Depois de conferir a versão local, execute na raiz do repositório:

```bash
git rm --cached gitignore chave_ia.php
git add .gitignore README.md ETAPA_1_NOTAS.md seguranca.php
 git add -A
 git status
```

Confira cuidadosamente a saída de `git status` antes do commit. Se algum token real já tiver sido publicado no histórico do GitHub, revogue-o no provedor e gere outro; apagar o arquivo no commit atual não apaga o histórico anterior.
