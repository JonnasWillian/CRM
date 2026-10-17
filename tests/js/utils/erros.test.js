import { describe, expect, it } from 'vitest';
import { ehErroDeValidacao, errosDeValidacao, mensagemDeErro, primeiroErro } from '@/utils/erros';

const resposta = (status, data) => ({ response: { status, data } });

describe('errosDeValidacao', () => {
    it('lê errors (padrão Laravel) e, na transição, erros', () => {
        expect(errosDeValidacao(resposta(422, { errors: { nome: ['Obrigatório'] } }))).toEqual({ nome: ['Obrigatório'] });
        expect(errosDeValidacao(resposta(422, { erros: { nome: ['Obrigatório'] } }))).toEqual({ nome: ['Obrigatório'] });
    });

    it('devolve objeto vazio quando não há validação', () => {
        expect(errosDeValidacao(resposta(500, { message: 'x' }))).toEqual({});
        expect(errosDeValidacao(new Error('rede'))).toEqual({});
    });
});

describe('primeiroErro e ehErroDeValidacao', () => {
    it('pega a primeira mensagem do primeiro campo', () => {
        expect(primeiroErro({ email: ['Inválido', 'Curto'], nome: ['Obrigatório'] })).toBe('Inválido');
        expect(primeiroErro({})).toBeNull();
    });

    it('só é validação com 422 e campos', () => {
        expect(ehErroDeValidacao(resposta(422, { errors: { a: ['x'] } }))).toBe(true);
        expect(ehErroDeValidacao(resposta(422, { message: 'Regra de domínio' }))).toBe(false);
        expect(ehErroDeValidacao(resposta(500, { errors: { a: ['x'] } }))).toBe(false);
    });
});

describe('mensagemDeErro', () => {
    it('prefere error, depois o primeiro erro de campo, depois message, depois o padrão', () => {
        expect(mensagemDeErro(resposta(422, { error: 'Último estágio aberto.' }), 'padrão')).toBe('Último estágio aberto.');
        expect(mensagemDeErro(resposta(422, { errors: { nome: ['Obrigatório'] } }), 'padrão')).toBe('Obrigatório');
        expect(mensagemDeErro(resposta(409, { message: 'Já restaurado.' }), 'padrão')).toBe('Já restaurado.');
        expect(mensagemDeErro(resposta(500, {}), 'padrão')).toBe('padrão');
        expect(mensagemDeErro(new Error('Network Error'), 'padrão')).toBe('padrão');
    });

    it('ignora a mensagem genérica de validação do framework', () => {
        expect(mensagemDeErro(resposta(422, { message: 'The given data was invalid.', errors: {} }), 'padrão')).toBe('padrão');
    });
});
