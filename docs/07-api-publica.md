# API pública (v1)

Somente leitura, **sem autenticação**, em `/api/v1`. Mesmo recorte de dados do portal público: só edições **publicadas** (rascunho retorna 404) e **nunca** cartões, disciplina, escalação ou dados pessoais. Nomes de atletas seguem a regra do portal (menores e idade desconhecida saem abreviados: "João S."). Limite: 60 requisições por minuto por IP.

| Rota | Retorna |
|---|---|
| `GET /api/v1/competitions` | Competições com suas edições publicadas |
| `GET /api/v1/seasons/{id}` | Edição: divisões, fases (classificação, rodadas, mata-mata) e artilharia |
| `GET /api/v1/seasons/{id}/stats` | Estatísticas da edição por divisão: ataque, defesa, forma (últimos 5), maiores vitórias e jogos mais movimentados (só jogos encerrados em campo; sem cartões nem atletas) |
| `GET /api/v1/games/{id}` | Retrato público do jogo: placar, relógio, gols, escudos |

Respostas em `{ "data": ... }`. Mudanças que quebram o formato entram em `/api/v2`; a v1 continua funcionando.

O portal também publica `/sitemap.xml` e `/robots.txt` (bloqueia `/painel`, `/overlay` e `/api`).
