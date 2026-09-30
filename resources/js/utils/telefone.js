// Exibição do telefone. O servidor guarda E.164 (+5511999998888) e é quem
// normaliza a entrada — as telas só mascaram e formatam.
export const MASCARA_TELEFONE = '["(##) ####-####", "(##) #####-####"]';

export function formatarTelefone(valor) {
    if (!valor) return '';
    const texto = String(valor);
    const m = texto.match(/^\+55(\d{2})(\d{4,5})(\d{4})$/);
    return m ? `(${m[1]}) ${m[2]}-${m[3]}` : texto;
}

// A máscara BR só serve para número nacional. Aplicada a um E.164 de fora
// ("+14155552671") ela descarta o "+" e o servidor, recebendo só dígitos,
// normaliza como brasileiro (+5514155552671). Vazio conta como BR: é o caso
// de quem vai digitar agora. Um +55 válido já chega aqui formatado como
// nacional por formatarTelefone; o que sobra com "+" fica sem máscara, para
// não ser reescrito.
export function ehTelefoneBrasileiro(valor) {
    const texto = String(valor ?? '').trim();
    return texto === '' || !texto.startsWith('+');
}
