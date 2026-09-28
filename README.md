# Presença

Sistema independente de check-in para o evento de 28/09/2026.

## Produção

https://presenca.mskpoeira.com.br

## Repositório

Este repositório contém exclusivamente o projeto **Presença**.

Não há compartilhamento de código, navegação ou funcionalidades com Show de Prêmios, SGR, SIGDEC, KaraokeStudio ou outros projetos.

## Função atual

- Página pública responsiva
- Formulário com Nome, Telefone, E-mail e Bairro
- Registro de cada check-in em banco SQLite
- Data e hora do registro no fuso America/Sao_Paulo
- Banco persistente fora da pasta pública
- Container próprio
- Workflow próprio de deploy
- Publicação em `presenca.mskpoeira.com.br`

## Banco de dados

O banco é armazenado em:

`/var/www/storage/presenca.sqlite`

Tabela principal:

`presencas`

Campos: `id`, `evento`, `nome`, `telefone`, `email`, `bairro` e `registrado_em`.

O diretório de armazenamento é montado como volume persistente no VPS e não é servido publicamente pelo Apache.

## Deploy

O deploy utiliza GitHub Actions e requer o secret:

`SSH_PRIVATE_KEY`

O servidor padrão configurado é `77.37.40.81`.
