/**
 * Tooltip global bergaya satu gaya untuk seluruh aplikasi, tanpa mengubah tiap tombol.
 * Teksnya diambil dari atribut yang sudah ada: data-tip, lalu title, lalu aria-label (khusus tombol/tautan).
 * Memakai event delegation dan elemen `position: fixed`, jadi tidak terpotong oleh tabel yang bisa digulir.
 */
const SELECTOR = '[data-tip], button[aria-label], a[aria-label], [role="button"][aria-label], [title]';
const SHOW_DELAY_MS = 120;
const GAP = 8;
const EDGE = 6;

let tip: HTMLDivElement | null = null;
let current: HTMLElement | null = null;
let stashedTitle: string | null = null;
let showTimer: number | undefined;
let watchTimer: number | undefined;
let installed = false;

function textOf(element: HTMLElement): string {
    return (element.dataset.tip || element.getAttribute('title') || element.getAttribute('aria-label') || '').trim();
}

function ensureTip(): HTMLDivElement {
    if (!tip) {
        tip = document.createElement('div');
        tip.className = 'app-tooltip';
        tip.setAttribute('role', 'tooltip');
        tip.dataset.visible = 'false';
        document.body.append(tip);
    }

    return tip;
}

function position(element: HTMLElement, box: HTMLDivElement): void {
    const target = element.getBoundingClientRect();
    const size = box.getBoundingClientRect();

    // Di atas tombol; bila tidak muat, pindah ke bawah.
    let top = target.top - size.height - GAP;

    if (top < EDGE) {
        top = target.bottom + GAP;
    }

    const left = Math.min(Math.max(target.left + target.width / 2 - size.width / 2, EDGE), window.innerWidth - size.width - EDGE);

    box.style.top = `${Math.round(top)}px`;
    box.style.left = `${Math.round(left)}px`;
}

function hide(): void {
    window.clearTimeout(showTimer);
    window.clearInterval(watchTimer);

    // Kembalikan title bawaan yang sempat dilepas agar tidak muncul dobel dengan tooltip native.
    if (current && stashedTitle !== null && current.isConnected) {
        current.setAttribute('title', stashedTitle);
    }

    stashedTitle = null;
    current = null;

    if (tip) {
        tip.dataset.visible = 'false';
    }
}

function show(element: HTMLElement): void {
    const text = textOf(element);

    // Tombol yang teks tampilannya sudah sama dengan keterangannya tidak perlu tooltip.
    if (!text || element.textContent?.trim() === text || !element.isConnected) {
        return;
    }

    if (element.hasAttribute('title')) {
        stashedTitle = element.getAttribute('title');
        element.removeAttribute('title');
    }

    current = element;
    const box = ensureTip();
    box.textContent = text;
    position(element, box);
    box.dataset.visible = 'true';

    // Elemen yang hilang saat re-render (mis. setelah aksi) tidak boleh meninggalkan tooltip menggantung.
    watchTimer = window.setInterval(() => {
        if (!current?.isConnected) {
            hide();
        }
    }, 300);
}

function schedule(element: HTMLElement): void {
    window.clearTimeout(showTimer);
    showTimer = window.setTimeout(() => show(element), SHOW_DELAY_MS);
}

export function installTooltips(): void {
    if (installed || typeof document === 'undefined') {
        return;
    }

    installed = true;

    document.addEventListener(
        'pointerover',
        (event) => {
            // Layar sentuh tidak punya hover; tooltip hanya untuk mouse dan pena.
            if (event.pointerType === 'touch') {
                return;
            }

            const element = (event.target instanceof Element ? event.target.closest<HTMLElement>(SELECTOR) : null) ?? null;

            if (element === current) {
                return;
            }

            hide();

            if (element) {
                schedule(element);
            }
        },
        true,
    );

    document.addEventListener(
        'focusin',
        (event) => {
            const element = event.target instanceof Element ? event.target.closest<HTMLElement>(SELECTOR) : null;

            // Hanya fokus lewat keyboard; klik mouse sudah ditangani hover.
            if (element && element.matches(':focus-visible')) {
                hide();
                show(element);
            }
        },
        true,
    );

    document.addEventListener('focusout', hide, true);
    document.addEventListener('pointerdown', hide, true);
    document.addEventListener('scroll', hide, true);
    document.addEventListener('keydown', (event) => event.key === 'Escape' && hide());
    document.documentElement.addEventListener('pointerleave', hide);
    window.addEventListener('blur', hide);
    document.addEventListener('inertia:start', hide);
}
