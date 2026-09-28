# Presença — identidade e isolamento

- Repositório oficial: `mskpoeira/presenca`
- Domínio: `presenca.mskpoeira.com.br`
- Diretório de produção: `/app/presenca`
- Contêiner: `presenca-app`
- Alias interno do proxy: `presenca`
- Armazenamento persistente: `/app/presenca/storage`

O projeto Presença é independente. Não incorpora código, banco, menus ou navegação de SGR, SIGDEC, Portal de Projetos ou Show de Prêmios.

O proxy reverso compartilhado apenas encaminha o domínio para `presenca:80`. A atualização da aplicação é feita pelo próprio ambiente do Presença, que sincroniza o repositório oficial e só promove uma nova imagem após validação.
