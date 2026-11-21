import { usePage } from '@inertiajs/react';

/** Apakah pengguna saat ini memiliki permission (Owner membawa seluruh permission dari server). */
export function useCan(): (permission: string) => boolean {
    const permissions = usePage().props.auth?.user.permissions ?? [];

    return (permission) => permissions.includes(permission);
}
