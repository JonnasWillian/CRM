/**
 * Posição de um painel flutuante (tooltip, menu) em relação ao gatilho,
 * em coordenadas da viewport, com virada para o lado oposto quando não cabe
 * e limite nas bordas. Puro: recebe retângulos, devolve números.
 *
 * @param {{top:number,left:number,right:number,bottom:number,width:number,height:number}} gatilho
 * @param {{width:number,height:number}} painel
 * @param {{lado?:'top'|'bottom'|'left'|'right', alinhar?:'start'|'center'|'end', margem?:number, viewport?:{width:number,height:number}}} opcoes
 * @returns {{top:number,left:number,lado:string}}
 */
export function posicionar(gatilho, painel, opcoes = {}) {
    const {
        lado: ladoPedido = 'bottom',
        alinhar = 'start',
        margem = 6,
        viewport = { width: window.innerWidth, height: window.innerHeight },
    } = opcoes;

    const limitar = (v, min, max) => Math.min(Math.max(v, min), Math.max(min, max));

    let lado = ladoPedido;

    if (lado === 'bottom' && gatilho.bottom + margem + painel.height > viewport.height && gatilho.top - margem - painel.height >= 0) {
        lado = 'top';
    } else if (lado === 'top' && gatilho.top - margem - painel.height < 0 && gatilho.bottom + margem + painel.height <= viewport.height) {
        lado = 'bottom';
    } else if (lado === 'right' && gatilho.right + margem + painel.width > viewport.width && gatilho.left - margem - painel.width >= 0) {
        lado = 'left';
    } else if (lado === 'left' && gatilho.left - margem - painel.width < 0 && gatilho.right + margem + painel.width <= viewport.width) {
        lado = 'right';
    }

    let top;
    let left;

    if (lado === 'top' || lado === 'bottom') {
        top = lado === 'bottom' ? gatilho.bottom + margem : gatilho.top - margem - painel.height;
        if (alinhar === 'end') left = gatilho.right - painel.width;
        else if (alinhar === 'center') left = gatilho.left + gatilho.width / 2 - painel.width / 2;
        else left = gatilho.left;
    } else {
        top = gatilho.top + gatilho.height / 2 - painel.height / 2;
        left = lado === 'right' ? gatilho.right + margem : gatilho.left - margem - painel.width;
    }

    return {
        top: limitar(top, margem, viewport.height - painel.height - margem),
        left: limitar(left, margem, viewport.width - painel.width - margem),
        lado,
    };
}
