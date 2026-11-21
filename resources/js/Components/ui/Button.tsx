import { type ButtonHTMLAttributes, forwardRef } from 'react';

import { cn } from '@/lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'outline' | 'edit' | 'info' | 'success' | 'warning' | 'remove';
type Size = 'sm' | 'md' | 'icon';

const variants: Record<Variant, string> = {
    primary: 'bg-primary text-primary-foreground shadow-sm hover:opacity-90',
    secondary: 'bg-muted text-foreground hover:bg-border',
    outline: 'border border-input bg-card text-foreground hover:bg-muted',
    ghost: 'text-muted-foreground hover:bg-muted hover:text-foreground',
    danger: 'bg-destructive text-white shadow-sm hover:opacity-90',
    edit: 'bg-primary/10 text-primary hover:bg-primary hover:text-primary-foreground',
    info: 'bg-sky-500/10 text-sky-600 hover:bg-sky-500 hover:text-white dark:text-sky-400 dark:hover:text-white',
    success: 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500 hover:text-white dark:text-emerald-400 dark:hover:text-white',
    warning: 'bg-amber-500/10 text-amber-600 hover:bg-amber-500 hover:text-white dark:text-amber-400 dark:hover:text-white',
    remove: 'bg-destructive/10 text-destructive hover:bg-destructive hover:text-white',
};

const sizes: Record<Size, string> = {
    sm: 'h-8 gap-1.5 px-3 text-xs',
    md: 'h-9 gap-2 px-4 text-sm',
    icon: 'size-8',
};

export const buttonClass = (variant: Variant = 'primary', size: Size = 'md', className?: string): string =>
    cn(
        'inline-flex shrink-0 items-center justify-center rounded-lg font-medium whitespace-nowrap transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:ring-offset-background focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50',
        variants[variant],
        sizes[size],
        className,
    );

type Props = ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant; size?: Size };

export const Button = forwardRef<HTMLButtonElement, Props>(function Button(
    { variant = 'primary', size = 'md', className, type = 'button', ...props },
    ref,
) {
    return <button ref={ref} type={type} className={buttonClass(variant, size, className)} {...props} />;
});
