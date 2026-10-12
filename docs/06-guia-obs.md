# Guia: overlays no OBS

Os overlays são páginas web com **fundo transparente** que o OBS carrega como *Fonte de navegador*. Cada **canal de transmissão** tem URLs fixas; durante o jogo você só escolhe o jogo no ar e liga as camadas, sem tocar no OBS.

## 1. Criar o canal (organizador)
Painel → **Transmissão** → *Novo canal*. Aparecem quatro URLs, todas no formato `https://seu-dominio/overlay/{token}/{tela}`:

| Tela | URL termina em | Quando aparece |
|---|---|---|
| Placar e cronômetro | `/placar` | sempre que há jogo no ar |
| Lances (gol, cartão, substituição) | `/eventos` | sozinha, ~7 s, quando a súmula é lançada |
| Escalação | `/escalacao` | quando o operador clica em *Mostrar escalação* |
| Tabela de classificação | `/tabela` | quando o operador clica em *Mostrar tabela* (fases de pontos corridos e grupos) |

O **token é a senha do canal**: quem tem a URL vê o canal. Se vazar, use *Renovar token* (as URLs antigas param de funcionar e é preciso colar as novas no OBS).

## 2. Configurar o OBS (uma vez)
1. Em *Fontes*, adicione uma **Fonte de navegador** para cada tela e cole a URL.
2. **Largura 1920 e altura 1080** (a tela é desenhada para esse quadro). Não precisa de CSS personalizado: o fundo já é transparente.
3. Deixe **todas as fontes sempre visíveis**. As telas só mostram algo quando há o que mostrar.
4. Marque *Desativar fonte quando não estiver visível* **desligado** e, se quiser, *Atualizar o navegador quando a cena ficar ativa* **desligado** (a tela se atualiza sozinha).

## 3. Durante o jogo (operador)
1. Painel → Transmissão → escolha o **Jogo no ar** (as telas passam a acompanhá-lo).
2. Na **súmula**, inicie o 1º tempo e lance os eventos: placar, cronômetro e as faixas de lance atualizam sozinhos.
3. Para a escalação ou a tabela, clique em **Mostrar…** e depois em **Ocultar…**.
4. Ao terminar, escolha outro jogo ou *Nenhum*.

## 4. Se algo falhar
- **Sem WebSocket** (Reverb fora do ar ou bloqueado): as telas consultam `/overlay/{token}/estado` a cada 3 s e continuam atualizando, com um pequeno atraso.
- **Nada aparece**: confirme que há um *Jogo no ar* no canal e abra a URL num navegador comum para ver a tela.
- **Escalação/tabela vazias**: a escalação só existe depois de salva na súmula; mata-mata não tem tabela.
- Em produção, o endereço público do Reverb vem de `REVERB_PUBLIC_HOST/PORT/SCHEME` (ver `docs/03-infra-cicd.md`).

## 5. Privacidade
Os overlays mostram **o que o operador coloca no ar**: placar, relógio, gols, lances e, sob demanda, escalação (nome/apelido e camisa) e tabela. Fotos, documentos e disciplina acumulada nunca são enviados. A decisão de exibir atletas menores de idade na transmissão é da organização.
