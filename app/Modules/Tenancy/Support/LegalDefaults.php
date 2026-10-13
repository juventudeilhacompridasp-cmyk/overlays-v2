<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\LegalType;

/** Textos-modelo (pt-BR) usados até a organização publicar a sua versão. Devem ser revisados com assessoria jurídica. */
final class LegalDefaults
{
    public static function body(LegalType $type, string $organization, ?string $controller, ?string $contact): string
    {
        $controller = $controller !== null && $controller !== '' ? $controller : $organization;
        $contactLine = $contact !== null && $contact !== '' ? $contact : 'o canal de contato divulgado pela organização';

        return match ($type) {
            LegalType::Terms => self::terms($organization, $controller),
            LegalType::Privacy => self::privacy($organization, $controller, $contactLine),
        };
    }

    private static function terms(string $organization, string $controller): string
    {
        return <<<TEXT
# 1. Sobre este serviço
Este serviço é operado por {$controller} ("{$organization}") para organizar competições esportivas: inscrição de equipes e atletas, tabelas, súmulas, resultados e transmissões.

# 2. Quem pode usar
O painel é restrito a pessoas autorizadas pela organização, cada uma com um papel (administrador, organizador, operador de jogo ou consulta). Mantenha sua senha em sigilo: o que for feito com o seu acesso é de sua responsabilidade.

# 3. Uso adequado
Informe dados verdadeiros, não insira dados de terceiros sem autorização e não use o serviço para fins ilícitos, nem tente acessar áreas ou dados que não são do seu papel.

# 4. Inscrições
Quem inscreve uma equipe declara ter autorização dos atletas e, no caso de menores de 18 anos, dos seus responsáveis. A organização pode devolver ou recusar inscrições que não cumpram o regulamento.

# 5. Conteúdo público
Resultados, classificações e nomes de atletas podem aparecer no portal público conforme a política de privacidade. Nomes de menores são abreviados.

# 6. Disponibilidade
Buscamos manter o serviço disponível, sem garantia de funcionamento ininterrupto, principalmente durante transmissões ao vivo; existem recursos de contingência, mas falhas de internet ou de terceiros podem ocorrer.

# 7. Mudanças
Estes termos podem ser atualizados. Quando houver nova versão, usuários do painel precisarão aceitá-la para continuar.
TEXT;
    }

    private static function privacy(string $organization, string $controller, string $contact): string
    {
        return <<<TEXT
# 1. Quem somos
{$controller} ("{$organization}") é a controladora dos dados tratados neste serviço, nos termos da Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018). Contato para assuntos de privacidade: {$contact}.

# 2. Quais dados tratamos
- Equipes e inscrições: nome da equipe, cidade, responsável (nome, e-mail e telefone).
- Atletas e comissão: nome, apelido, data de nascimento, função, número de camisa, foto e documentos enviados na inscrição (como documento de identificação, atestado médico e autorização do responsável).
- CPF: não guardamos o número; guardamos apenas um código irreversível (hash) usado para localizar cadastros e evitar duplicidade.
- Jogos: escalações, gols, cartões e suspensões.
- Usuários do painel: nome, e-mail e papel.

# 3. Para que usamos
Para organizar a competição: validar inscrições, montar elencos e tabelas, registrar súmulas, aplicar regras de disciplina, emitir carteirinhas e divulgar resultados. Não vendemos dados.

# 4. Base legal
Execução da relação com a competição, cumprimento do regulamento e, quando necessário, consentimento — que você pode revogar. Para menores de 18 anos o tratamento depende do consentimento específico de pelo menos um dos pais ou do responsável legal (art. 14 da LGPD).

# 5. O que é público
O portal mostra equipes, placares, classificação e artilharia. Nomes de adultos aparecem como apelido ou nome; nomes de menores aparecem abreviados (ex.: "João S."). Fotos, documentos, data de nascimento, CPF, cartões e dados de disciplina individuais não são exibidos publicamente.

# 6. Compartilhamento e segurança
Documentos e fotos ficam em armazenamento privado e só são acessados por pessoas autorizadas, por links temporários. O acesso ao painel exige login e é limitado por papel. Podemos usar prestadores de infraestrutura (hospedagem e e-mail) que tratam dados em nosso nome.

# 7. Por quanto tempo
Mantemos os dados enquanto forem necessários para a competição e para obrigações legais ou de histórico esportivo. Pedidos de inscrição recusados ou não concluídos podem ser excluídos.

# 8. Seus direitos
Você pode pedir confirmação do tratamento, acesso, correção, anonimização ou eliminação de dados desnecessários, portabilidade, informação sobre compartilhamento e a revogação do consentimento. Para exercer seus direitos, fale com: {$contact}.
TEXT;
    }
}
