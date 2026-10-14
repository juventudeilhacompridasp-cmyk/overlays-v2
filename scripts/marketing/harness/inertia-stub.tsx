import React from 'react';

// Substituto mínimo do @inertiajs/react só para gerar os prints: as telas reais pedem Head/Link/router/usePage/useForm.
export const Head = () => null;
export const Link = ({ href, children, className }: { href: string; children?: React.ReactNode; className?: string }) => <a href={href} className={className}>{children}</a>;
export const router = { patch: () => undefined, post: () => undefined, delete: () => undefined, reload: () => undefined };
export const usePage = () => ({ props: { flash: {}, auth: { can: {} }, features: [] } });
export const useForm = (initial: Record<string, unknown>) => ({ data: initial, setData: () => undefined, post: () => undefined, processing: false, errors: {}, reset: () => undefined });
