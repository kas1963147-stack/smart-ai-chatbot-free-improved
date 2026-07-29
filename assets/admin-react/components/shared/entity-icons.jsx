/**
 * Entity Icon System
 *
 * Uses the same SVG icons as the Preset Selector cards.
 * Assigns an icon to agents/widgets based on name keywords,
 * with a deterministic hash fallback.
 */

/* ──────────────────────────────────────────────
   Same icons used in PresetSelector ICONS map
   ────────────────────────────────────────────── */

const ICONS = {
    cart: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
        </svg>
    ),
    target: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
        </svg>
    ),
    headset: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
        </svg>
    ),
    briefcase: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
        </svg>
    ),
    wave: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M10.05 4.575a1.575 1.575 0 10-3.15 0v3m3.15-3v-1.5a1.575 1.575 0 013.15 0v1.5m-3.15 0l.075 5.925m3.075-5.925a1.575 1.575 0 20-3.15 0v3m3.15-3v1.5m0 6v-6a1.575 1.575 0 113.15 0v5.85l-2.925 8.925h-9.9l-1-7.2-2.1-.9a1.575 1.575 0 01.9-3l2.85 1.2 1.35 6" />
        </svg>
    ),
    diamond: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 2L2 9.5 12 22 22 9.5 12 2z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M2 9.5h20" />
        </svg>
    ),
    cpu: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 9h6v6H9V9z" />
        </svg>
    ),
    maximize: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9m11.25-5.25v4.5m0-4.5h-4.5m4.5 0L15 9m-11.25 11.25v-4.5m0 4.5h4.5m-4.5 0L9 15m11.25 5.25v-4.5m0 4.5h-4.5m4.5 0L15 15" />
        </svg>
    ),
    camera: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
        </svg>
    ),
    chatBubble: (size) => (
        <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
    ),
};

/* ──────────────────────────────────────────────
   Keyword → Icon mapping
   ────────────────────────────────────────────── */

const KEYWORD_MAP = [
    { keywords: ['ecommerce', 'e-commerce', 'shop', 'store', 'woo', 'product', 'order', 'cart'], icon: 'cart' },
    { keywords: ['lead', 'market', 'campaign', 'promot', 'growth', 'engag', 'landing', 'target', 'convert'], icon: 'target' },
    { keywords: ['support', 'help', 'helpdesk', 'service', 'ticket', 'customer', 'faq'], icon: 'headset' },
    { keywords: ['sale', 'business', 'corporate', 'profession', 'consult', 'advisor', 'briefcase'], icon: 'briefcase' },
    { keywords: ['greet', 'welcome', 'hello', 'hi ', 'wave', 'simple', 'basic', 'general', 'default'], icon: 'wave' },
    { keywords: ['vip', 'premium', 'luxury', 'exclusive', 'gold', 'elite', 'concierge', 'diamond'], icon: 'diamond' },
    { keywords: ['tech', 'cyber', 'code', 'dev', 'program', 'engineer', 'neon', 'cpu', 'bot', 'robot', 'ai', 'smart'], icon: 'cpu' },
    { keywords: ['minimal', 'mono', 'clean', 'expand', 'full', 'maximize', 'distraction'], icon: 'maximize' },
    { keywords: ['social', 'photo', 'camera', 'image', 'visual', 'media', 'video', 'instagram', 'creative', 'influencer', 'gradient', 'style', 'design'], icon: 'camera' },
    { keywords: ['chat', 'message', 'convers', 'talk', 'messenger', 'widget', 'assistant'], icon: 'chatBubble' },
];

/* ──────────────────────────────────────────────
   Icon keys in stable order for hash fallback
   ────────────────────────────────────────────── */

const ICON_KEYS = Object.keys(ICONS);

/* ──────────────────────────────────────────────
   Deterministic hash (djb2)
   ────────────────────────────────────────────── */

function hashString(str) {
    let hash = 5381;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) + hash + str.charCodeAt(i)) & 0xffffffff;
    }
    return Math.abs(hash);
}

/* ──────────────────────────────────────────────
   Public API
   ────────────────────────────────────────────── */

/**
 * Get the icon key for a given name.
 */
export function getIconKeyForName(name = '') {
    const lower = name.toLowerCase().trim();

    for (const entry of KEYWORD_MAP) {
        for (const kw of entry.keywords) {
            if (lower.includes(kw)) {
                return entry.icon;
            }
        }
    }

    // Deterministic fallback – same name always gets same icon
    return ICON_KEYS[hashString(lower) % ICON_KEYS.length];
}

/**
 * Render the SVG icon for the given entity name.
 */
export function EntityIcon({ name = '', size = 'w-6 h-6' }) {
    const key = getIconKeyForName(name);
    const renderIcon = ICONS[key] || ICONS.chatBubble;
    return renderIcon(size);
}

export default EntityIcon;
