# Presença

Sistema independente de check-in em eventos.

## Funções

- página pública responsiva;
- formulário de Nome, Telefone, E-mail e Bairro;
- registro em SQLite próprio;
- registro de IP e data/hora;
- armazenamento persistente exclusivo;
- container e rede próprios.

## Persistência

Banco: `/var/www/storage/presenca.sqlite`

O diretório persistente do servidor é `/app/presenca/storage`.

## Isolamento

O projeto não compartilha código, banco, rede Docker, volume, proxy, autenticação, API ou workflow com qualquer outro projeto.

O runtime usa a rede `presenca` e o bind HTTP local `127.0.0.1:58080` por padrão.
