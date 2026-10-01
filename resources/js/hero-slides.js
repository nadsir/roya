// Homepage hero slider imagery.
//
// This is the ONLY file you need to edit to change the three hero photos.
// Swap `src` (and optionally `srcset` / `sizes` / `width` / `height` / `alt`) below and
// the slider picks the change up immediately — no component, Blade, PHP or build step.
//
// Where to put the files:
//   project-root/public/images/slider/slide-01.jpg
//   project-root/public/images/slider/slide-02.jpg
//   project-root/public/images/slider/slide-03.jpg
// `public/` is served as-is, so no storage link, no upload form and no npm step is needed.
// Put the files in `storage/app/public/images/slider/` instead only if you also plan to
// point `src` at `/storage/images/slider/...` (requires `php artisan storage:link`).
//
// Keep `width` / `height` equal to the real pixel size of each file: the browser uses them
// to reserve the box before the image arrives, which is what keeps slide changes free of
// layout shift. `sizes` must describe the real widths you serve in `srcset`.
//
// Only slide 3 reads `allowProductImage`; slides 1 and 2 are always the campaign photos.
export const heroSlideImages = [
    {
        src: '/images/slider/slide-01.png',
        srcset: undefined,
        sizes: '(max-width: 600px) 100vw, (max-width: 900px) 70vw, 52vw',
        width: 1280,
        height: 1600,
        alt: 'جایگاه موقت تصویر اسلاید اول؛ عکس کمپین گالری را اینجا جایگزین کنید',
    },
    {
        src: '/images/slider/slide-02.png',
        srcset: undefined,
        sizes: '(max-width: 600px) 100vw, (max-width: 900px) 70vw, 52vw',
        width: 1280,
        height: 1600,
        alt: 'جایگاه موقت تصویر اسلاید دوم؛ عکس کمپین گالری را اینجا جایگزین کنید',
    },
    {
        src: '/images/slider/slide-03.png',
        srcset: undefined,
        sizes: '(max-width: 600px) 100vw, (max-width: 900px) 70vw, 52vw',
        width: 1280,
        height: 1600,
        alt: 'جایگاه موقت تصویر اسلاید سوم؛ عکس کمپین گالری را اینجا جایگزین کنید',
        // Slide 3 may show the API product photo instead of the campaign photo above.
        // It stays off by default: a product image record can point at a file that is
        // not on disk, and that silent 404 used to replace this slide with a remote
        // placeholder. The product name, price and "کشف این انتخاب" link below it still
        // come from the API either way. Turn this on only once every product image the
        // API returns is actually served.
        allowProductImage: false,
    },
];

/* One slide index, one image, in every render. The slider only ever asks for 0, 1 and 2,
   and any out-of-range index degrades to the first entry instead of rendering a broken <img>. */
export function heroSlideImage(index) {
    return heroSlideImages[index] ?? heroSlideImages[0];
}
