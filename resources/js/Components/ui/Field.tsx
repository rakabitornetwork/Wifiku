import { type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes, forwardRef } from 'react';

import { cn } from '@/lib/utils';

const controlClass =
    'w-full rounded-lg border border-input bg-card px-3 text-sm text-foreground shadow-xs transition placeholder:text-muted-foreground/70 focus:border-ring focus:ring-2 focus:ring-ring/25 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Input({ className, ...props }, ref) {
    return <input ref={ref} className={cn(controlClass, 'h-9', className)} {...props} />;
});

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function Textarea(
    { className, ...props },
    ref,
) {
    return <textarea ref={ref} rows={3} className={cn(controlClass, 'py-2', className)} {...props} />;
});

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(function Select({ className, children, ...props }, ref) {
    return (
        <select ref={ref} className={cn(controlClass, 'h-9 pr-8', className)} {...props}>
            {children}
        </select>
    );
});

type FieldProps = {
    label: string;
    error?: string;
    hint?: string;
    className?: string;
    children: ReactNode;
};

export function Field({ label, error, hint, className, children }: FieldProps) {
    return (
        <label className={cn('flex flex-col gap-1.5', className)}>
            <span className="text-xs font-medium text-foreground/80">{label}</span>
            {children}
            {hint && !error && <span className="text-xs text-muted-foreground">{hint}</span>}
            {error && <span className="text-xs text-destructive">{error}</span>}
        </label>
    );
}

export function Checkbox({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <label className="flex cursor-pointer items-center gap-2 text-sm">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="size-4 rounded border-input accent-[var(--primary)]"
            />
            {label}
        </label>
    );
}
