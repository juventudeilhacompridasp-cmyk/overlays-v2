/** Aplica a máscara 000.000.000-00 enquanto a pessoa digita (só dígitos, até 11). */
export const maskCpf = (value: string): string => {
    const digits = value.replace(/\D/g, '').slice(0, 11);

    return digits
        .replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1-$2');
};
