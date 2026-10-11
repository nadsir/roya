import { homeVisuals } from './home-visuals.js';

// Home campaign photographs. Existing local artwork is the offline fallback.
export const heroSlideImages = homeVisuals.map((image, index) => ({
    ...image,
    fallbackSrc: `/images/slider/slide-0${index + 1}.png`,
    srcset: undefined,
    sizes: '(max-width: 600px) 100vw, (max-width: 900px) 70vw, 52vw',
    width: 500,
    height: 750,
    allowProductImage: false,
}));

export function heroSlideImage(index) {
    return heroSlideImages[index] ?? heroSlideImages[0];
}
