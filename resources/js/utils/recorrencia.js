/**
 * Datas de contrato recorrente, com a mesma regra do CronogramaDeRecorrencia
 * (PHP): o mês k começa no aniversário do início, sem transbordar (31/01 + 1
 * mês = 28/02), e o fim é a véspera do aniversário seguinte ao último mês.
 * Só para pré-visualizar no formulário; o valor oficial vem do servidor.
 */
const iso = (y, m, d) => `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

export function somarMeses(dataIso, meses) {
    const [y, m, d] = String(dataIso).slice(0, 10).split('-').map(Number);
    const total = m - 1 + Number(meses);
    const ano = y + Math.floor(total / 12);
    const mes = (total % 12 + 12) % 12 + 1;
    const ultimoDia = new Date(ano, mes, 0).getDate();
    return iso(ano, mes, Math.min(d, ultimoDia));
}

export function fimDoContrato(inicioIso, meses) {
    if (!inicioIso || !(Number(meses) > 0)) return null;
    const [y, m, d] = somarMeses(inicioIso, meses).split('-').map(Number);
    const vespera = new Date(y, m - 1, d - 1);
    return iso(vespera.getFullYear(), vespera.getMonth() + 1, vespera.getDate());
}
