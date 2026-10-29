/**
 * Central, maintainable source of truth for ROYA GALLERY social destinations.
 *
 * To enable a network, set its real `url` below — every surface (homepage
 * section, footer, future pages) picks it up automatically. Networks without
 * a real URL stay hidden/disabled; never point cards at fake addresses.
 */

// The store's real WhatsApp line (shared with the wholesale «Next Chapter» section).
export const WHATSAPP_NUMBER = '09126612898';
export const WHATSAPP_NUMBER_INTL = '989126612898';

const WHATSAPP_MESSAGE = 'سلام، برای ارتباط با رویا گالری پیام می‌دهم.';

export const SOCIAL_NETWORKS = [
    {
        key: 'telegram',
        label: 'تلگرام',
        description: 'جدیدترین محصولات و خبرهای رویا',
        action: 'عضویت در کانال',
        // TODO: set the real channel URL, e.g. 'https://t.me/royagallery'
        url: null,
        brand: '#229ED9',
    },
    {
        key: 'whatsapp',
        label: 'واتساپ',
        description: 'ارتباط مستقیم و دریافت راهنمایی',
        action: 'ارتباط با ما',
        url: `https://wa.me/${WHATSAPP_NUMBER_INTL}?text=${encodeURIComponent(WHATSAPP_MESSAGE)}`,
        brand: '#1FA855',
    },
    {
        key: 'youtube',
        label: 'یوتیوب',
        description: 'معرفی محصولات و ویدیوهای استایل',
        action: 'تماشای ویدیوها',
        // TODO: set the real channel URL, e.g. 'https://www.youtube.com/@royagallery'
        url: null,
        brand: '#E02B2B',
    },
];

/** Networks with a real, configured destination. */
export const ACTIVE_SOCIAL_NETWORKS = SOCIAL_NETWORKS.filter((network) => Boolean(network.url));
