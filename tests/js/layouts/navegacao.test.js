import { describe, expect, it } from 'vitest';
import { gruposDeNavegacao } from '@/Layouts/navegacao';

const todas = () => true;

describe('gruposDeNavegacao', () => {
    it('admin com todas as rotas vê os grupos completos, na ordem', () => {
        const grupos = gruposDeNavegacao({ pode: todas, existe: todas });
        expect(grupos.map((g) => g.titulo ?? null)).toEqual([null, 'Análise', 'Configurações', 'Sistema']);
        expect(grupos[0].itens.map((i) => i.rotulo)).toEqual(['Dashboard', 'Leads', 'Pipeline', 'Tarefas']);
        expect(grupos[2].itens.map((i) => i.rotulo)).toEqual(['Funis', 'Motivos de perda', 'Modelos de tarefa', 'Equipe']);
        expect(grupos[3].itens.map((i) => i.rotulo)).toEqual(['Lixeira']);
    });

    it('vendedor não vê Relatórios nem o que exige configuracoes.manage/agentes.manage', () => {
        const pode = (p) => ['leads.manage', 'projetos.manage', 'tarefas.manage'].includes(p);
        const grupos = gruposDeNavegacao({ pode, existe: todas });
        expect(grupos.map((g) => g.titulo ?? null)).toEqual([null, 'Configurações', 'Sistema']);
        expect(grupos[1].itens.map((i) => i.rotulo)).toEqual(['Modelos de tarefa']);
    });

    it('itens cuja rota ainda não existe ficam de fora (páginas que nascem em fases seguintes)', () => {
        const existe = (r) => ['dashboard', 'kanban', 'configuracoes.modelosTarefa', 'relatorios.perdas'].includes(r);
        const grupos = gruposDeNavegacao({ pode: todas, existe });
        expect(grupos[0].itens.map((i) => i.rotulo)).toEqual(['Dashboard', 'Pipeline']);
        expect(grupos.find((g) => g.titulo === 'Sistema')).toBeUndefined();
        expect(grupos.find((g) => g.titulo === 'Análise').itens[0].rota).toBe('relatorios.perdas');
    });
});
