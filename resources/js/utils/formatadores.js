/**
 * Formatação compartilhada. Antes, cada página tinha a própria cópia de
 * formatBRL, iniciais, formatarDataHora… e as cópias já divergiam (duas
 * versões de iniciais, '—' ou 'R$ 0,00' para vazio dependendo da tela).
 */

const moedaBRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * @param {number|string|null|undefined} valor
 * @param {string|null} vazio  texto para null/undefined/''; null = formata como zero
 */
export function formatBRL(valor, vazio = null) {
    if (valor === null || valor === undefined || valor === '') {
        return vazio ?? moedaBRL.format(0);
    }
    const n = typeof valor === 'number' ? valor : parseFloat(String(valor).replace(',', '.'));
    return moedaBRL.format(Number.isFinite(n) ? n : 0);
}

const DATA_SO = /^\d{4}-\d{2}-\d{2}$/;

/** "2026-10-17" → Date local (new Date('2026-10-17') seria UTC e recuaria um dia no Brasil). */
export function parseDataLocal(iso) {
    if (!iso) return null;
    const texto = String(iso).substring(0, 10);
    if (!DATA_SO.test(texto)) return null;
    const [y, m, d] = texto.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function paraDate(valor) {
    if (!valor) return null;
    if (valor instanceof Date) return Number.isNaN(valor.getTime()) ? null : valor;
    const texto = String(valor);
    const d = DATA_SO.test(texto) ? parseDataLocal(texto) : new Date(texto);
    return d && !Number.isNaN(d.getTime()) ? d : null;
}

export function formatarData(valor) {
    const d = paraDate(valor);
    return d ? d.toLocaleDateString('pt-BR') : null;
}

export function formatarDataHora(valor) {
    const d = paraDate(valor);
    if (!d) return '';
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

/** Dias inteiros entre `valor` e `agora` (ms); null para vazio. */
export function diasDesde(valor, agora = Date.now()) {
    const d = paraDate(valor);
    if (!d) return null;
    return Math.floor((agora - d.getTime()) / 86400000);
}

export function formatarRelativo(valor, agora = Date.now()) {
    const dias = diasDesde(valor, agora);
    if (dias === null) return null;
    if (dias <= 0) return 'hoje';
    if (dias === 1) return 'ontem';
    if (dias < 30) return `há ${dias} dias`;
    if (dias < 365) {
        const meses = Math.floor(dias / 30);
        return meses === 1 ? 'há 1 mês' : `há ${meses} meses`;
    }
    const anos = Math.floor(dias / 365);
    return anos === 1 ? 'há 1 ano' : `há ${anos} anos`;
}

const umaCasa = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

export function formatarTamanho(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${umaCasa.format(bytes / 1024)} KB`;
    return `${umaCasa.format(bytes / 1048576)} MB`;
}

/** Primeira letra do nome e do sobrenome: "Ana Souza" → "AS", "Marina" → "M". */
export function iniciais(nome) {
    return (
        String(nome ?? '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((p) => p[0])
            .join('')
            .toUpperCase() || '?'
    );
}
