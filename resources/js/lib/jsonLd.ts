/**
 * JSON-LD para o <head>: serializa sem permitir que o texto feche o <script> (`</script>`), comente (`<!--`) ou quebre a
 * linha em motores antigos (U+2028/U+2029). Os dados vêm do servidor (nomes de equipes etc.), mas nunca confiamos no texto.
 */
const LINE_SEPARATOR = new RegExp(String.fromCharCode(0x2028), 'g');
const PARAGRAPH_SEPARATOR = new RegExp(String.fromCharCode(0x2029), 'g');

export function safeJsonLd(data: unknown): string {
    return JSON.stringify(data)
        .replace(/</g, '\\u003c')
        .replace(/>/g, '\\u003e')
        .replace(/&/g, '\\u0026')
        .replace(LINE_SEPARATOR, '\\u2028')
        .replace(PARAGRAPH_SEPARATOR, '\\u2029');
}