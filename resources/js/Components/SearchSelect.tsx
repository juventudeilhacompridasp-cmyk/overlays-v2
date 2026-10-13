import { KeyboardEvent, SelectHTMLAttributes, useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

/** Quantidade mínima de letras digitadas para a lista começar a ser filtrada. */
export const SEARCH_MIN_CHARS = 2;

const base =
    'w-full rounded-md border border-gray-300 bg-white text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100';

type Opt = { value: string; label: string; disabled: boolean };

// No SSR `useLayoutEffect` emite aviso; lá basta o efeito comum (nunca roda no servidor).
const useIsoLayoutEffect = typeof window === 'undefined' ? useEffect : useLayoutEffect;

const normalize = (text: string) =>
    text
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase();

/**
 * Select pesquisável (no estilo Select2): substitui o `<select>` nativo mantendo a mesma API.
 *
 * O `<select>` nativo continua no DOM (escondido) e é a fonte da verdade — valor, `required`, `name`,
 * `onChange` e as `<option>` filhas funcionam como antes. A interface é um botão que abre uma lista com
 * busca: com {@link SEARCH_MIN_CHARS} ou mais letras a lista é filtrada (sem acento e sem diferenciar
 * maiúsculas); com menos, mostra tudo. Antes de a página hidratar aparece o `<select>` nativo.
 */
export default function SearchSelect({ children, className = '', id, 'aria-label': ariaLabel, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    const selectRef = useRef<HTMLSelectElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const panelRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLUListElement>(null);

    const [ready, setReady] = useState(false);
    const [options, setOptions] = useState<Opt[]>([]);
    const [selected, setSelected] = useState('');
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const [pos, setPos] = useState<{ left: number; width: number; top?: number; bottom?: number }>({ left: 0, width: 0 });

    // Lê as opções e o valor do <select> nativo a cada renderização (as filhas podem mudar com o estado).
    useIsoLayoutEffect(() => {
        const el = selectRef.current;
        if (!el) return;
        const read = Array.from(el.options).map((o) => ({ value: o.value, label: o.text, disabled: o.disabled }));
        setOptions((prev) => (JSON.stringify(prev) === JSON.stringify(read) ? prev : read));
        setSelected(el.value);
        setReady(true);
    });

    const term = query.trim();
    const filtering = term.length >= SEARCH_MIN_CHARS;
    const visible = filtering ? options.filter((o) => normalize(o.label).includes(normalize(term))) : options;
    const current = options.find((o) => o.value === selected);

    const place = useCallback(() => {
        const rect = buttonRef.current?.getBoundingClientRect();
        if (!rect) return;
        const width = Math.max(rect.width, 180);
        const left = Math.min(rect.left, Math.max(8, window.innerWidth - width - 8));
        const below = window.innerHeight - rect.bottom;
        setPos(below < 300 && rect.top > below ? { left, width, bottom: window.innerHeight - rect.top + 4 } : { left, width, top: rect.bottom + 4 });
    }, []);

    const close = useCallback((refocus = false) => {
        setOpen(false);
        setQuery('');
        if (refocus) buttonRef.current?.focus();
    }, []);

    const openList = () => {
        if (props.disabled) return;
        place();
        const index = options.findIndex((o) => o.value === selected);
        setActive(Math.max(index, 0));
        setOpen(true);
    };

    useEffect(() => {
        if (!open) return;
        inputRef.current?.focus();
        const onDown = (event: MouseEvent) => {
            const target = event.target as Node;
            if (!panelRef.current?.contains(target) && !buttonRef.current?.contains(target)) close();
        };
        const onScroll = (event: Event) => {
            if (!panelRef.current?.contains(event.target as Node)) close();
        };
        document.addEventListener('mousedown', onDown);
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', place);
        return () => {
            document.removeEventListener('mousedown', onDown);
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', place);
        };
    }, [open, close, place]);

    // Ao filtrar, volta o destaque para o primeiro resultado.
    useEffect(() => {
        setActive(0);
    }, [filtering ? term : '']);

    useEffect(() => {
        listRef.current?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
    }, [active, open]);

    const choose = (opt: Opt) => {
        if (opt.disabled) return;
        const el = selectRef.current;
        if (el && el.value !== opt.value) {
            el.value = opt.value;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        close(true);
    };

    const onInputKey = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((i) => Math.min(i + 1, visible.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const opt = visible[active];
            if (opt) choose(opt);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            close(true);
        } else if (event.key === 'Tab') {
            close();
        }
    };

    const onButtonKey = (event: KeyboardEvent<HTMLButtonElement>) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            openList();
        }
    };

    return (
        <div className="relative">
            <select
                {...props}
                ref={selectRef}
                // Antes do mount é o campo de verdade; depois fica só como armazenamento do valor.
                className={ready ? 'sr-only' : `${base} ${className}`}
                tabIndex={ready ? -1 : props.tabIndex}
                aria-hidden={ready ? true : undefined}
                id={ready ? undefined : id}
                aria-label={ready ? undefined : ariaLabel}
            >
                {children}
            </select>

            {ready && (
                <button
                    ref={buttonRef}
                    type="button"
                    id={id}
                    aria-label={ariaLabel}
                    aria-haspopup="listbox"
                    aria-expanded={open}
                    disabled={props.disabled}
                    onClick={() => (open ? close() : openList())}
                    onKeyDown={onButtonKey}
                    className={`${base} flex items-center justify-between gap-2 px-3 py-2 text-left ${className}`}
                >
                    <span className={`truncate ${current && current.value !== '' ? 'text-gray-900' : 'text-gray-500'}`}>{current?.label || ' '}</span>
                    <svg className="h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth={1.8} aria-hidden>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 8l4 4 4-4" />
                    </svg>
                </button>
            )}

            {open &&
                createPortal(
                    <div
                        ref={panelRef}
                        style={{ position: 'fixed', left: pos.left, width: pos.width, top: pos.top, bottom: pos.bottom }}
                        className="z-[70] rounded-md bg-white shadow-lg ring-1 ring-gray-200"
                    >
                        <div className="border-b border-gray-100 p-2">
                            <input
                                ref={inputRef}
                                type="text"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                onKeyDown={onInputKey}
                                placeholder={`Digite ao menos ${SEARCH_MIN_CHARS} letras para buscar…`}
                                autoComplete="off"
                                role="combobox"
                                aria-expanded
                                aria-controls="search-select-list"
                                className="w-full rounded border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                            />
                        </div>
                        <ul ref={listRef} id="search-select-list" role="listbox" className="max-h-60 overflow-y-auto py-1 text-sm">
                            {visible.length === 0 && <li className="px-3 py-2 text-gray-500">Nenhum resultado</li>}
                            {visible.map((opt, index) => (
                                <li
                                    key={`${opt.value}-${index}`}
                                    role="option"
                                    aria-selected={opt.value === selected}
                                    aria-disabled={opt.disabled}
                                    data-active={index === active}
                                    onMouseEnter={() => setActive(index)}
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => choose(opt)}
                                    className={`cursor-pointer px-3 py-1.5 ${
                                        opt.disabled ? 'cursor-not-allowed text-gray-400' : index === active ? 'bg-emerald-50 text-emerald-800' : 'text-gray-800'
                                    } ${opt.value === selected ? 'font-semibold' : ''}`}
                                >
                                    {opt.label || ' '}
                                </li>
                            ))}
                        </ul>
                    </div>,
                    document.body,
                )}
        </div>
    );
}
